<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\ModerationReason;
use App\Enums\ModerationType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AccountDecision;
use App\Models\Document;
use App\Models\ModerationAction;
use App\Models\Order;
use App\Models\User;
use App\Services\AccountValidationService;
use App\Services\ModerationService;
use App\Services\StoreHours;
use App\Support\DocumentPresenter;
use App\Support\ModerationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comptes client / livreur / entreprise : file de validation, annuaires par rôle et fiche d'un compte.
 * Les administrateurs ne figurent pas dans cette liste.
 */
class AccountController extends Controller
{
    /** Commandes / courses récentes affichées sur la fiche. */
    private const RECENT_ORDERS = 10;

    /** Produits listés sur la fiche entreprise (le total est toujours indiqué). */
    private const LISTED_PRODUCTS = 20;

    /**
     * Types de comptes soumis à validation.
     */
    private const TYPES = [Role::Client, Role::Delivery, Role::Business];

    /**
     * Comptes à valider : onglet « En attente » par défaut.
     */
    public function index(Request $request): Response
    {
        return $this->listing($request, null, [
            'mode' => 'validation',
            'title' => 'Comptes à valider',
            'route' => 'admin.accounts.index',
            'default_status' => AccountStatus::Pending->value,
        ]);
    }

    /**
     * Annuaires Clients, Livreurs et Entreprises : tous les statuts, filtre « Comptes signalés ».
     */
    public function clients(Request $request): Response
    {
        return $this->listing($request, Role::Client, ['mode' => 'directory', 'title' => 'Clients', 'route' => 'admin.clients.index', 'default_status' => 'all']);
    }

    public function deliveries(Request $request): Response
    {
        return $this->listing($request, Role::Delivery, ['mode' => 'directory', 'title' => 'Livreurs', 'route' => 'admin.deliveries.index', 'default_status' => 'all']);
    }

    public function businesses(Request $request): Response
    {
        return $this->listing($request, Role::Business, ['mode' => 'directory', 'title' => 'Entreprises', 'route' => 'admin.businesses.index', 'default_status' => 'all']);
    }

    /**
     * Liste paginée commune : onglets de statut (+ « Tous » pour les annuaires), type, recherche,
     * filtre des comptes signalés ; les compteurs des onglets tiennent compte des autres filtres.
     *
     * @param  array{mode: string, title: string, route: string, default_status: string}  $page
     */
    private function listing(Request $request, ?Role $lockedRole, array $page): Response
    {
        $statuses = array_map(fn (AccountStatus $case) => $case->value, AccountStatus::cases());

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...$statuses, 'all'])],
            'type' => ['nullable', Rule::in(array_map(fn (Role $role) => $role->value, self::TYPES))],
            'q' => ['nullable', 'string', 'max:100'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        $status = $filters['status'] ?? $page['default_status'];
        $type = $lockedRole?->value ?? ($filters['type'] ?? null);
        $search = trim($filters['q'] ?? '');
        $flagged = (bool) ($filters['flagged'] ?? false);

        // Type, recherche et signalement s'appliquent aussi aux compteurs des onglets.
        $base = fn (): Builder => User::query()
            ->whereIn('role', self::TYPES)
            ->when($type, fn (Builder $query) => $query->where('role', $type))
            ->when($search !== '', fn (Builder $query) => $this->search($query, $search))
            ->when($flagged, fn (Builder $query) => $query->whereNotNull('flagged_at'));

        $accounts = $base()
            ->when($status !== 'all', fn (Builder $query) => $query->where('account_status', $status))
            ->withCount('documents')
            // En attente : les plus anciens d'abord (premier inscrit, premier validé).
            ->orderBy('created_at', $status === AccountStatus::Pending->value ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate(15, ['id', 'name', 'email', 'phone', 'role', 'account_status', 'flagged_at', 'blocked_until', 'created_at'])
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'account_status' => $user->account_status->value,
                'flagged' => $user->isFlagged(),
                'documents_count' => $user->documents_count,
                'registered_at' => $user->created_at?->format('d/m/Y'),
                'registered_at_iso' => $user->created_at?->toIso8601String(),
            ]);

        $counts = $base()
            ->selectRaw('account_status, COUNT(*) as total')
            ->groupBy('account_status')
            ->pluck('total', 'account_status');

        return Inertia::render('Admin/Accounts/Index', [
            'page' => [...$page, 'locked_type' => $lockedRole?->value],
            'accounts' => $accounts,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'q' => $search,
                'flagged' => $flagged,
            ],
            'counts' => [
                ...collect(AccountStatus::cases())->mapWithKeys(fn (AccountStatus $case) => [
                    $case->value => (int) ($counts[$case->value] ?? 0),
                ]),
                'all' => (int) $counts->sum(),
            ],
            'flaggedCount' => User::query()
                ->whereIn('role', self::TYPES)
                ->when($type, fn (Builder $query) => $query->where('role', $type))
                ->whereNotNull('flagged_at')
                ->count(),
            'types' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], self::TYPES),
        ]);
    }

    /**
     * Page de validation d'un compte : informations, documents, décision, historique.
     */
    public function show(Request $request, User $user, AccountValidationService $validation, ModerationService $moderation): Response
    {
        $user->load([
            'neighborhood:id,name,zone',
            'approver:id,name',
            'deliveryProfile.baseNeighborhood:id,name,zone',
            'store.category:id,name',
            'store.neighborhood:id,name,zone',
            'store.openingHours',
            'documents.reviewer:id,name',
            'decisions.actor:id,name',
            'moderationActions.admin:id,name',
        ]);
        $warningsCount = $user->moderationActions->where('type', ModerationType::Warning)->count();

        $required = $validation->requiredDocuments($user);
        $requiredValues = array_map(fn (DocumentType $type) => $type->value, $required);
        $documents = $user->documents->keyBy(fn (Document $document) => $document->type->value);
        $profile = $user->deliveryProfile;
        $store = $user->store;

        return Inertia::render('Admin/Accounts/Show', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'initials' => $user->initials(),
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'account_status' => $user->account_status->value,
                'rejection_reason' => $user->rejection_reason,
                'neighborhood' => $user->neighborhood?->name,
                'zone' => $user->neighborhood?->zone,
                'address_landmarks' => $user->address_landmarks,
                'registered_at' => $user->created_at?->format('d/m/Y à H:i'),
                'approved_at' => $user->approved_at?->format('d/m/Y à H:i'),
                'approved_by' => $user->approver?->name,
            ],
            'deliveryProfile' => $profile ? [
                'vehicle_type' => $profile->vehicle_type->value,
                'vehicle' => $profile->vehicle_type->label(),
                'vehicle_brand' => $profile->vehicle_brand,
                'plate_number' => $profile->plate_number,
                'license_number' => $profile->license_number,
                'base_neighborhood' => $profile->baseNeighborhood?->name,
                'base_zone' => $profile->baseNeighborhood?->zone,
            ] : null,
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
                'category' => $store->category?->name,
                'description' => $store->description,
                'phone' => $store->phone,
                'neighborhood' => $store->neighborhood?->name,
                'zone' => $store->neighborhood?->zone,
                'address_landmarks' => $store->address_landmarks,
                'logo' => $store->logo,
                'is_active' => $store->is_active,
                'opening_hours' => StoreHours::schedule($store),
            ] : null,
            // Obligatoires d'abord (même absents), puis les autres documents envoyés.
            'documents' => [
                ...array_map(fn (DocumentType $type) => isset($documents[$type->value])
                    ? DocumentPresenter::present($documents[$type->value])
                    : ['type' => $type->value, 'label' => $type->label(), 'required' => true, 'missing' => true], $required),
                // (filter et non except : sur une collection Eloquent, except() retire par clé primaire.)
                ...$documents->filter(fn (Document $document) => ! in_array($document->type->value, $requiredValues, true))
                    ->map(fn (Document $document) => DocumentPresenter::present($document, required: false))
                    ->values(),
            ],
            'decision' => [
                'can_approve' => $validation->canApprove($user),
                'missing' => array_map(fn (DocumentType $type) => $type->label(), $validation->missingApprovals($user)),
            ],
            'moderation' => [
                'can_moderate' => $request->user()->can('moderate', $user),
                'is_blocked' => $user->isBlocked(),
                'blocked_until' => $user->blocked_until ? $moderation->localDate($user->blocked_until) : null,
                'is_flagged' => $user->isFlagged(),
                'flagged_at' => $user->flagged_at ? $moderation->localDate($user->flagged_at) : null,
                'warnings_count' => $warningsCount,
                // Alerte (jamais de blocage automatique) à partir de 3 avertissements.
                'suggest_block' => $warningsCount >= ModerationService::WARNING_ALERT_THRESHOLD && ! $user->isBlocked(),
                'actions' => $user->moderationActions->map(fn (ModerationAction $action) => ModerationPresenter::present($action)),
                // Commandes en cours, montrées avant de confirmer un blocage.
                'active_orders' => $moderation->activeOrders($user)->map(fn (Order $order) => [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'status' => $order->status->value,
                    'store' => $order->store?->name,
                    'client' => $order->client?->name,
                    'total_price' => $order->total_price,
                    'created_at' => $moderation->localDate($order->created_at),
                ]),
                'reasons' => ModerationReason::options(),
                'durations' => collect(ModerationService::DURATIONS)->map(fn (array $duration, string $key) => ['value' => $key, 'label' => $duration['label']])->values(),
            ],
            'history' => $user->decisions->map(fn (AccountDecision $decision) => [
                'id' => $decision->id,
                'action' => $decision->action->value,
                'label' => $decision->action->label(),
                'color' => $decision->action->color(),
                'document' => $decision->document_type?->label(),
                'actor' => $decision->actor?->name,
                'by_owner' => $decision->actor_id === $user->id,
                'note' => $decision->note,
                'at' => $decision->created_at?->format('d/m/Y à H:i'),
                'at_iso' => $decision->created_at?->toIso8601String(),
            ]),
            'activity' => $this->activity($user, $moderation),
        ]);
    }

    /**
     * Activité métier de la fiche : produits et commandes reçues (entreprise), disponibilité
     * et courses (livreur), commandes passées (client).
     *
     * @return array<string, mixed>
     */
    private function activity(User $user, ModerationService $moderation): array
    {
        // Fabrique (et non instance) : chaque requête repart d'une relation neuve.
        $orders = match ($user->role) {
            Role::Business => $user->store ? fn () => $user->store->orders() : null,
            Role::Delivery => fn () => $user->deliveries(),
            Role::Client => fn () => $user->orders(),
            default => null,
        };

        $recentOrders = $orders
            ? $orders()->with(['store:id,name', 'client:id,name', 'delivery:id,name', 'neighborhood:id,name'])
                ->latest()->latest('id')->limit(self::RECENT_ORDERS)->get()
            : collect();

        $products = $user->store
            ? $user->store->products()->orderBy('name')->get(['id', 'store_id', 'name', 'price', 'image', 'is_available'])
            : collect();

        return [
            'orders_count' => $orders ? $orders()->count() : 0,
            'recent_orders' => $recentOrders->map(fn (Order $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_color' => $order->status->color(),
                'store' => $order->store?->name,
                'client' => $order->client?->name,
                'delivery' => $order->delivery?->name,
                'neighborhood' => $order->neighborhood?->name,
                'total_price' => $order->total_price,
                'created_at' => $moderation->localDate($order->created_at),
            ]),
            'products' => $user->store ? [
                'count' => $products->count(),
                'available' => $products->where('is_available', true)->count(),
                'items' => $products->take(self::LISTED_PRODUCTS)->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'image' => $product->image,
                    'is_available' => $product->is_available,
                ])->values(),
            ] : null,
            'is_available' => $user->deliveryProfile?->is_available,
        ];
    }

    /**
     * Nom, e-mail ou téléphone (le téléphone est comparé sans espaces : "077123" trouve "077 12 34 56").
     */
    private function search(Builder $query, string $search): Builder
    {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $digits = preg_replace('/\D+/', '', $search);

        return $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->when(strlen($digits) >= 3, fn (Builder $query) => $query
                ->orWhereRaw("REPLACE(phone, ' ', '') like ?", ['%'.$digits.'%'])));
    }
}
