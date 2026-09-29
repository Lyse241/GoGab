<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationReason;
use App\Enums\ModerationType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateAccountRequest;
use App\Models\ModerationAction;
use App\Models\User;
use App\Services\ModerationService;
use App\Support\ModerationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modération des comptes. Toute la logique est dans ModerationService (qui revérifie la Policy) ;
 * les routes sont aussi protégées par le middleware "can:moderate,user".
 */
class ModerationController extends Controller
{
    public function __construct(private readonly ModerationService $moderation) {}

    /**
     * Dernières actions de modération, filtrables par type, motif et administrateur.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(ModerationType::class)],
            'reason' => ['nullable', Rule::enum(ModerationReason::class)],
            'admin' => ['nullable', 'integer'],
        ]);

        $actions = ModerationAction::query()
            ->with(['user:id,name,role', 'admin:id,name'])
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->when($filters['admin'] ?? null, fn ($query, $admin) => $query->where('admin_id', $admin))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ModerationAction $action) => ModerationPresenter::present($action) + [
                'account' => $action->user ? [
                    'id' => $action->user->id,
                    'name' => $action->user->name,
                    'role_label' => $action->user->role->label(),
                ] : null,
            ]);

        return Inertia::render('Admin/Moderation/Index', [
            'actions' => $actions,
            'filters' => [
                'type' => $filters['type'] ?? null,
                'reason' => $filters['reason'] ?? null,
                'admin' => isset($filters['admin']) ? (string) $filters['admin'] : null,
            ],
            'types' => array_map(fn (ModerationType $type) => ['value' => $type->value, 'label' => $type->label()], ModerationType::cases()),
            'reasons' => ModerationReason::options(),
            'admins' => User::where('role', Role::Admin)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $admin) => ['value' => (string) $admin->id, 'label' => $admin->name]),
        ]);
    }

    public function warn(ModerateAccountRequest $request, User $user): RedirectResponse
    {
        $this->moderation->warn($request->user(), $user, $request->reason(), $request->validated('message'));

        return back()->with('success', "Avertissement envoyé à {$user->name}.");
    }

    public function block(ModerateAccountRequest $request, User $user): RedirectResponse
    {
        $action = $this->moderation->block(
            $request->user(),
            $user,
            $request->reason(),
            $request->validated('message'),
            $request->validated('duration'),
        );

        return back()->with('success', $action->ends_at
            ? "Compte de {$user->name} bloqué jusqu’au {$this->moderation->localDate($action->ends_at)}."
            : "Compte de {$user->name} bloqué jusqu’à nouvel ordre.");
    }

    public function unblock(ModerateAccountRequest $request, User $user): RedirectResponse
    {
        $this->moderation->unblock($request->user(), $user, $request->reason(), $request->validated('message'));

        return back()->with('success', "Compte de {$user->name} débloqué.");
    }

    public function flag(ModerateAccountRequest $request, User $user): RedirectResponse
    {
        $this->moderation->flag($request->user(), $user, $request->reason(), $request->validated('internal_note'));

        return back()->with('success', "{$user->name} est signalé aux autres administrateurs.");
    }

    public function unflag(ModerateAccountRequest $request, User $user): RedirectResponse
    {
        $this->moderation->unflag($request->user(), $user, $request->validated('internal_note'));

        return back()->with('success', "Signalement de {$user->name} retiré.");
    }

}
