<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Store;
use App\Services\OrderSupervision;
use App\Services\OrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supervision de toutes les commandes (/admin/orders) : filtres, recherche par référence,
 * détail avec l'historique complet et les parties, annulation (motif obligatoire, via
 * PUT orders.status.update → OrderWorkflow), relance des annonces, export CSV filtré.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderSupervision $supervision,
        private readonly OrderWorkflow $workflow,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $timezone = config('gogab.timezone');

        $orders = $this->supervision->query($filters)
            ->with(['store:id,name', 'client:id,name', 'delivery:id,name', 'neighborhood:id,name'])
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->reference,
                'created_at' => $order->created_at->setTimezone($timezone)->format('d/m/Y H\hi'),
                'store' => $order->store?->name,
                'client' => $order->client?->name,
                'courier' => $order->delivery?->name,
                'neighborhood' => $order->neighborhood?->name,
                'total_price' => $order->total_price,
                'status' => $order->status->value,
                'is_stuck' => $this->supervision->isStuck($order),
                'searching_minutes' => $this->supervision->searchingMinutes($order),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => [
                ['value' => OrderSupervision::STUCK, 'label' => 'Bloquées (recherche de livreur)'],
                ...array_map(fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()], OrderStatus::cases()),
            ],
            'stores' => Store::orderBy('name')->get(['id', 'name'])->map(fn (Store $store) => ['value' => (string) $store->id, 'label' => $store->name]),
            'stuckCount' => $this->supervision->whereStuck(Order::query())->count(),
            'stuckMinutes' => $this->supervision->stuckMinutes(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load([
            'store:id,name,phone,owner_id,neighborhood_id',
            'store.owner:id,name,phone,email',
            'store.neighborhood:id,name,zone',
            'client:id,name,phone,email',
            'delivery:id,name,phone,email',
            'delivery.deliveryProfile',
            'neighborhood:id,name,zone',
            'items.product:id,name',
            'statusHistories.author:id,name,role',
        ]);
        $timezone = config('gogab.timezone');
        $person = fn ($user, array $extra = []) => $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            ...$extra,
        ] : null;

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->reference,
                'status' => $order->status->value,
                'is_final' => $order->status->isFinal(),
                'created_at' => $order->created_at->setTimezone($timezone)->format('d/m/Y à H\hi'),
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'total_price' => $order->total_price,
                'payment_method_label' => $order->payment_method->label(),
                'cash_given' => $order->cash_given,
                'change_due' => $order->change_due,
                'cash_collected_at' => $order->cash_collected_at?->setTimezone($timezone)->format('d/m/Y à H\hi'),
                'neighborhood' => $order->neighborhood?->name,
                'zone' => $order->neighborhood?->zone,
                'address_landmarks' => $order->address_landmarks,
                'client_note' => $order->client_note,
                'cancel_reason' => $order->cancel_reason,
                'announcement_count' => $order->announcement_count,
                'is_stuck' => $this->supervision->isStuck($order),
                'searching_minutes' => $this->supervision->searchingMinutes($order),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]),
                'history' => $order->statusHistories->map(fn (OrderStatusHistory $entry) => [
                    'id' => $entry->id,
                    'status' => $entry->status->value,
                    'label' => $entry->status->label(),
                    'author' => $entry->author?->name,
                    'author_role' => $entry->author?->role?->label(),
                    'note' => $entry->note,
                    'at' => $entry->created_at->setTimezone($timezone)->format('d/m/Y à H\hi'),
                ]),
            ],
            'parties' => [
                'client' => $person($order->client),
                'store' => $order->store ? [
                    'id' => $order->store->id,
                    'name' => $order->store->name,
                    'phone' => $order->store->phone,
                    'neighborhood' => $order->store->neighborhood?->name,
                    'zone' => $order->store->neighborhood?->zone,
                    'owner' => $person($order->store->owner),
                ] : null,
                'courier' => $person($order->delivery, [
                    'vehicle' => $order->delivery?->deliveryProfile?->vehicle_type?->label(),
                ]),
            ],
            'can' => [
                // Annulation admin : depuis tout statut non final (motif obligatoire).
                'cancel' => in_array(OrderStatus::Cancelled, $this->workflow->allowedTransitions($order, $request->user()), true),
                'relaunch' => $this->workflow->canRelaunch($order, $request->user()),
            ],
            'couriersInZone' => $order->status === OrderStatus::SearchingCourier
                ? $this->workflow->couriersForZone($order->store?->neighborhood?->zone)->count()
                : null,
        ]);
    }

    /**
     * Relance l'annonce d'une commande en recherche de livreur (nouvelle notification aux livreurs).
     */
    public function relaunch(Request $request, Order $order): RedirectResponse
    {
        $order = $this->workflow->relaunch($order, $request->user());

        return back()->with('success', "Annonce de la commande {$order->reference} relancée auprès des livreurs de la zone.");
    }

    /**
     * Export CSV des commandes filtrées (mêmes filtres que la liste), lisible par Excel
     * (UTF-8 avec BOM, séparateur « ; »).
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->supervision->query($this->filters($request))
            ->with(['store:id,name', 'client:id,name', 'delivery:id,name', 'neighborhood:id,name'])
            ->latest()
            ->latest('id');
        $timezone = config('gogab.timezone');
        $amount = fn ($value) => $value === null ? '' : number_format((float) $value, 0, '', '');

        return response()->streamDownload(function () use ($query, $timezone, $amount) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Référence', 'Date', 'Statut', 'Commerce', 'Client', 'Livreur', 'Quartier', 'Paiement', 'Sous-total (FCFA)', 'Frais de livraison (FCFA)', 'Total (FCFA)', 'Motif d’arrêt'], ';');

            $query->chunk(500, function ($orders) use ($out, $timezone, $amount) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->reference,
                        $order->created_at->setTimezone($timezone)->format('d/m/Y H:i'),
                        $order->status->label(),
                        $order->store?->name,
                        $order->client?->name,
                        $order->delivery?->name,
                        $order->neighborhood?->name,
                        $order->payment_method->label(),
                        $amount($order->subtotal),
                        $amount($order->delivery_fee),
                        $amount($order->total_price),
                        $order->cancel_reason,
                    ], ';');
                }
            });

            fclose($out);
        }, 'commandes-gogab-'.now()->setTimezone($timezone)->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{status: ?string, store: ?string, from: ?string, to: ?string, q: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([OrderSupervision::STUCK, ...array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases())])],
            'store' => ['nullable', 'integer', 'exists:stores,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:30'],
        ], [
            'status.in' => 'Statut inconnu.',
            'store.exists' => 'Commerce inconnu.',
            'from.date_format' => 'Date de début invalide.',
            'to.date_format' => 'Date de fin invalide.',
            'to.after_or_equal' => 'La date de fin doit suivre la date de début.',
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'store' => isset($validated['store']) ? (string) $validated['store'] : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'q' => filled($validated['q'] ?? null) ? trim($validated['q']) : null,
        ];
    }
}
