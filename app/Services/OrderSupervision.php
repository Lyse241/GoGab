<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Report;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Supervision admin des commandes : filtres (liste et export CSV partagent la même requête),
 * commandes bloquées en recherche de livreur, chiffres du tableau de bord. Les jours sont ceux
 * de Libreville ; les changements de statut passent toujours par OrderWorkflow.
 */
class OrderSupervision
{
    /** Valeur spéciale du filtre de statut : commandes bloquées en recherche de livreur. */
    public const STUCK = 'stuck';

    /**
     * Délai sans livreur après l'annonce au-delà duquel une commande est « bloquée ».
     */
    public function stuckMinutes(): int
    {
        return max(1, (int) config('gogab.stuck_search_minutes', 15));
    }

    /**
     * Commandes filtrées : statut (ou « stuck »), commerce, période (jours de Libreville),
     * référence (« GG-000123 », « 000123 » ou « 123 »).
     *
     * @param  array{status?: ?string, store?: int|string|null, from?: ?string, to?: ?string, q?: ?string}  $filters
     */
    public function query(array $filters): Builder
    {
        $status = $filters['status'] ?? null;

        return Order::query()
            ->when($status === self::STUCK, fn (Builder $query) => $this->whereStuck($query))
            ->when($status && $status !== self::STUCK, fn (Builder $query) => $query->where('status', $status))
            ->when($filters['store'] ?? null, fn (Builder $query, $store) => $query->where('store_id', $store))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $this->dayStart($from)))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<', $this->dayStart($to)->addDay()))
            ->when($this->referenceId($filters['q'] ?? null), fn (Builder $query, int $id) => $query->whereKey($id))
            ->when(filled($filters['q'] ?? null) && $this->referenceId($filters['q']) === null, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * Commandes en recherche de livreur depuis plus de stuckMinutes() après l'annonce.
     */
    public function whereStuck(Builder $query): Builder
    {
        return $query
            ->where('status', OrderStatus::SearchingCourier)
            ->whereNull('delivery_id')
            ->whereRaw('coalesce(announced_at, updated_at) <= ?', [now()->subMinutes($this->stuckMinutes())->toDateTimeString()]);
    }

    public function isStuck(Order $order): bool
    {
        return $order->status === OrderStatus::SearchingCourier
            && $order->delivery_id === null
            && ($order->announced_at ?? $order->updated_at)?->lte(now()->subMinutes($this->stuckMinutes()));
    }

    /**
     * Minutes écoulées depuis l'annonce (commande en recherche de livreur), sinon null.
     */
    public function searchingMinutes(Order $order): ?int
    {
        if ($order->status !== OrderStatus::SearchingCourier) {
            return null;
        }

        return (int) floor(($order->announced_at ?? $order->updated_at)->diffInMinutes(now()));
    }

    /**
     * Chiffres du tableau de bord admin.
     *
     * Chiffre d'affaires du jour = total payé par les clients (articles + frais de livraison)
     * des commandes livrées aujourd'hui.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $todayStart = StoreHours::now()->startOfDay();
        $todayRange = [$todayStart->utc(), $todayStart->addDay()->utc()];

        return [
            'pending_accounts' => User::awaitingValidation()->count(),
            'open_reports' => Report::pending()->count(),
            'orders_today' => Order::where('created_at', '>=', $todayRange[0])->where('created_at', '<', $todayRange[1])->count(),
            'revenue_today' => (float) Order::where('status', OrderStatus::Delivered)
                ->where('updated_at', '>=', $todayRange[0])
                ->where('updated_at', '<', $todayRange[1])
                ->sum('total_price'),
            'available_couriers' => User::query()
                ->where('role', Role::Delivery)
                ->where('account_status', AccountStatus::Approved)
                ->whereHas('deliveryProfile', fn (Builder $query) => $query->where('is_available', true))
                ->count(),
            'active_stores' => Store::visible()->count(),
            'stuck_orders' => $this->whereStuck(Order::query())->count(),
            'total_orders' => Order::count(),
        ];
    }

    /**
     * Commandes créées par jour sur les 7 derniers jours (aujourd'hui compris), jours de Libreville.
     *
     * @return list<array{date: string, label: string, orders: int, delivered: int}>
     */
    public function lastSevenDays(): array
    {
        $today = StoreHours::now()->startOfDay();
        $start = $today->subDays(6);
        $orders = Order::query()
            ->where('created_at', '>=', $start->utc())
            ->get(['id', 'status', 'created_at']);

        $byDay = $orders->groupBy(fn (Order $order) => $order->created_at->setTimezone(StoreHours::timezone())->toDateString());

        return collect(range(0, 6))->map(function (int $offset) use ($start, $byDay) {
            $day = $start->addDays($offset);
            $ofDay = $byDay->get($day->toDateString(), collect());

            return [
                'date' => $day->toDateString(),
                'label' => Str::ucfirst($day->locale('fr')->isoFormat('ddd D')),
                'orders' => $ofDay->count(),
                'delivered' => $ofDay->where('status', OrderStatus::Delivered)->count(),
            ];
        })->all();
    }

    /**
     * Répartition des commandes par statut (tous les statuts, même à zéro, dans l'ordre du cycle).
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    public function byStatus(): array
    {
        $counts = Order::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ])->all();
    }

    /**
     * Derniers événements : changements de statut, nouveaux comptes, signalements (du plus récent).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function latestEvents(int $limit = 12): Collection
    {
        $timezone = StoreHours::timezone();
        $at = fn ($date) => CarbonImmutable::instance($date)->setTimezone($timezone);

        $statusChanges = OrderStatusHistory::query()
            ->with(['order:id,reference', 'author:id,name,role'])
            ->latest('created_at')->latest('id')->limit($limit)->get()
            ->map(fn (OrderStatusHistory $entry) => [
                'key' => "status-{$entry->id}",
                'type' => 'order',
                'title' => "{$entry->order?->reference} · {$entry->status->label()}",
                'detail' => $entry->author ? "{$entry->author->name} ({$entry->author->role?->label()})" : null,
                'status' => $entry->status->value,
                'url' => $entry->order ? route('admin.orders.show', $entry->order_id) : null,
                'sort' => $entry->created_at,
            ]);

        $accounts = User::query()
            ->where('role', '!=', Role::Admin)
            ->latest()->latest('id')->limit($limit)->get(['id', 'name', 'role', 'created_at'])
            ->map(fn (User $user) => [
                'key' => "account-{$user->id}",
                'type' => 'account',
                'title' => "Nouveau compte : {$user->name}",
                'detail' => $user->role->label(),
                'status' => null,
                'url' => route('admin.accounts.show', $user),
                'sort' => $user->created_at,
            ]);

        $reports = Report::query()
            ->with('reportedUser:id,name')
            ->latest()->latest('id')->limit($limit)->get()
            ->map(fn (Report $report) => [
                'key' => "report-{$report->id}",
                'type' => 'report',
                'title' => "Signalement : {$report->reason->label()}",
                'detail' => $report->reportedUser?->name,
                'status' => null,
                'url' => route('admin.reports.show', $report),
                'sort' => $report->created_at,
            ]);

        return $statusChanges->concat($accounts)->concat($reports)
            ->sortByDesc(fn (array $event) => $event['sort']?->getTimestamp() ?? 0)
            ->take($limit)
            ->map(fn (array $event) => [...collect($event)->except('sort')->all(), 'at' => $event['sort'] ? $at($event['sort'])->format('d/m H\hi') : null])
            ->values();
    }

    private function dayStart(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, StoreHours::timezone())->startOfDay()->utc();
    }

    /**
     * Identifiant de commande à partir d'une référence saisie, ou null si illisible.
     */
    private function referenceId(?string $search): ?int
    {
        if (blank($search) || ! preg_match('/^\s*(?:GG\s*-?\s*)?0*(\d{1,9})\s*$/i', $search, $matches)) {
            return null;
        }

        return (int) $matches[1] ?: null;
    }
}
