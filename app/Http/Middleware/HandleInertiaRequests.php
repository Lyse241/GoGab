<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                // Champs utiles à l'interface uniquement (jamais le modèle complet).
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at,
                    'role' => $user->role?->value,
                    'account_status' => $user->account_status?->value,
                    'account_status_label' => $user->account_status?->label(),
                    'initials' => $user->initials(),
                    // Quartier par défaut du sélecteur « Livrer à » tant que rien n'est choisi.
                    'neighborhood_id' => $user->neighborhood_id,
                ] : null,
                'role' => $request->user()?->role?->value,
                'role_label' => $request->user()?->role?->label(),
                // Badge de la cloche dès l'affichage (ensuite rafraîchi par /notifications/unread).
                'unread_notifications' => fn () => $request->user()?->unreadNotifications()->count(),
            ],
            // Affichés en toasts par le ToastProvider React.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            // Avertissements de la modération : le plus ancien non lu doit être accusé (« J'ai compris »).
            'moderation' => fn () => $user ? $this->moderation($user) : null,
            // Compteurs affichés dans le menu de l'espace connecté (clé `badge` de Layouts/navigation.js).
            'badges' => fn () => match (true) {
                $user?->isAdmin() && $user->isApproved() => ['pending_accounts' => User::awaitingValidation()->count()],
                // Entreprise : nouvelles commandes à accepter ou refuser.
                $user?->isBusiness() && $user->isApproved() && $user->store !== null => [
                    'new_orders' => $user->store->orders()->where('status', OrderStatus::Pending)->count(),
                ],
                default => [],
            },
            // Sélecteur de quartier du header public.
            'neighborhoods' => fn () => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            // Footer public : moyens de paiement et catégories les plus fournies.
            'footer' => fn () => [
                'payment_methods' => array_map(fn (PaymentMethod $method) => $method->label(), PaymentMethod::cases()),
                'popular_categories' => Category::query()
                    ->whereHas('stores', fn (Builder $query) => $query->visible())
                    ->withCount(['stores' => fn (Builder $query) => $query->visible()])
                    ->orderByDesc('stores_count')
                    ->orderBy('sort_order')
                    ->limit(5)
                    ->get(['id', 'name', 'slug'])
                    ->map(fn (Category $category) => ['name' => $category->name, 'slug' => $category->slug]),
            ],
            // Libellés et couleurs des statuts : source unique pour UI/StatusBadge.
            'statuses' => fn () => [
                'order' => $this->describe(OrderStatus::cases()),
                'account' => $this->describe(AccountStatus::cases()),
            ],
        ];
    }

    /**
     * @return array{warnings_count: int, pending_warning: array<string, mixed>|null}
     */
    private function moderation(User $user): array
    {
        $service = app(ModerationService::class);
        $warning = $service->pendingWarning($user);

        return [
            'warnings_count' => $service->warningsCount($user),
            'pending_warning' => $warning ? [
                'id' => $warning->id,
                'reason' => $warning->reason?->label(),
                'message' => $warning->message,
                'at' => $service->localDate($warning->created_at),
            ] : null,
        ];
    }

    /**
     * @param  list<OrderStatus|AccountStatus>  $cases
     * @return array<string, array{label: string, color: string}>
     */
    private function describe(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn ($case) => [$case->value => ['label' => $case->label(), 'color' => $case->color()]])
            ->all();
    }
}
