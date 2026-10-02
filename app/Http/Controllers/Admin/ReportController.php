<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\ModerationReason;
use App\Enums\ModerationType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HandleReportRequest;
use App\Models\ModerationAction;
use App\Models\OrderStatusHistory;
use App\Models\Report;
use App\Models\User;
use App\Services\ModerationService;
use App\Services\ReportService;
use App\Support\ModerationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Signalements des utilisateurs (/admin/reports) : liste filtrable (fraudes à traiter en tête),
 * détail avec la commande, son historique et les antécédents du compte, traitement.
 */
class ReportController extends Controller
{
    /** Types de compte filtrables (rôle de la personne signalée). */
    private const ACCOUNT_TYPES = [Role::Client, Role::Delivery, Role::Business];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'all', ...array_column(ReportStatus::options(), 'value')])],
            'reason' => ['nullable', Rule::enum(ReportReason::class)],
            'type' => ['nullable', Rule::in(array_map(fn (Role $role) => $role->value, self::ACCOUNT_TYPES))],
        ]);
        $status = $filters['status'] ?? 'pending';

        $reports = Report::query()
            ->with(['reporter:id,name,role', 'reportedUser:id,name,role,flagged_at', 'order:id,reference'])
            ->when($status === 'pending', fn (Builder $query) => $query->pending())
            ->when(! in_array($status, ['pending', 'all'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($filters['reason'] ?? null, fn (Builder $query, string $reason) => $query->where('reason', $reason))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->whereHas('reportedUser', fn (Builder $user) => $user->where('role', $type)))
            ->urgentFirst()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Report $report) => $this->presentRow($report));

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'filters' => ['status' => $status, 'reason' => $filters['reason'] ?? null, 'type' => $filters['type'] ?? null],
            'statuses' => [
                ['value' => 'pending', 'label' => 'À traiter'],
                ...ReportStatus::options(),
                ['value' => 'all', 'label' => 'Tous'],
            ],
            'reasons' => ReportReason::options(),
            'types' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], self::ACCOUNT_TYPES),
        ]);
    }

    public function show(Request $request, Report $report, ModerationService $moderation): Response
    {
        $this->reports->markInReview($request->user(), $report);

        $report->load([
            'reporter:id,name,role,phone,email',
            'reportedUser:id,name,role,phone,email,account_status,flagged_at,blocked_until',
            'handler:id,name',
            'order.store:id,name',
            'order.client:id,name',
            'order.delivery:id,name',
            'order.statusHistories.author:id,name,role',
        ]);
        $reported = $report->reportedUser;
        $order = $report->order;

        return Inertia::render('Admin/Reports/Show', [
            'report' => [
                ...$this->presentRow($report),
                'description' => $report->description,
                'admin_note' => $report->admin_note,
                'handled_by' => $report->handler?->name,
                'handled_at' => $report->handled_at ? $moderation->localDate($report->handled_at) : null,
                'can_handle' => $request->user()->can('handle', $report),
                'default_moderation_reason' => $report->reason->moderationReason()->value,
            ],
            'reportedAccount' => [
                'id' => $reported->id,
                'name' => $reported->name,
                'role_label' => $reported->role->label(),
                'email' => $reported->email,
                'phone' => $reported->phone,
                'status' => $reported->account_status->value,
                'is_flagged' => $reported->isFlagged(),
                // Un blocage n'est possible que sur un compte validé (ModerationService::block).
                'can_block' => $reported->account_status === AccountStatus::Approved,
                'can_moderate' => $request->user()->can('moderate', $reported),
                'warnings_count' => $moderation->warningsCount($reported),
            ],
            'order' => $order ? [
                'id' => $order->id,
                'number' => $order->reference,
                'status' => $order->status->value,
                'store' => $order->store?->name,
                'client' => $order->client?->name,
                'courier' => $order->delivery?->name,
                'total_price' => $order->total_price,
                'created_at' => $moderation->localDate($order->created_at),
                'cancel_reason' => $order->cancel_reason,
                'history' => $order->statusHistories->map(fn (OrderStatusHistory $entry) => [
                    'id' => $entry->id,
                    'status' => $entry->status->value,
                    'label' => $entry->status->label(),
                    'author' => $entry->author?->name,
                    'author_role' => $entry->author?->role?->label(),
                    'note' => $entry->note,
                    'at' => $moderation->localDate($entry->created_at),
                ]),
            ] : null,
            // Antécédents de la personne signalée : autres signalements et avertissements.
            'otherReports' => Report::query()
                ->with(['reporter:id,name,role', 'reportedUser:id,name,role,flagged_at', 'order:id,reference'])
                ->where('reported_user_id', $reported->id)
                ->whereKeyNot($report->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Report $other) => $this->presentRow($other)),
            'warnings' => $reported->moderationActions()
                ->with('admin:id,name')
                ->where('type', ModerationType::Warning)
                ->limit(20)
                ->get()
                ->map(fn (ModerationAction $action) => ModerationPresenter::present($action)),
            'moderationReasons' => ModerationReason::options(),
            'durations' => collect(ModerationService::DURATIONS)->map(fn (array $duration, string $key) => ['value' => $key, 'label' => $duration['label']])->values(),
        ]);
    }

    public function handle(HandleReportRequest $request, Report $report): RedirectResponse
    {
        $action = $request->validated('action');
        $this->reports->handle($request->user(), $report, $action, $request->validated('admin_note'), $request->sanction());

        return back()->with('success', match ($action) {
            'warn' => 'Avertissement envoyé ; signalement traité.',
            'block' => 'Compte bloqué ; signalement traité.',
            'dismiss' => 'Signalement classé sans suite.',
            default => 'Signalement marqué comme traité.',
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(Report $report): array
    {
        $person = fn (?User $user) => $user ? ['id' => $user->id, 'name' => $user->name, 'role_label' => $user->role->label()] : null;

        return [
            'id' => $report->id,
            'reason' => $report->reason->value,
            'reason_label' => $report->reason->label(),
            'is_urgent' => $report->isUrgent(),
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'status_color' => $report->status->color(),
            'reporter' => $person($report->reporter),
            'reported' => [...($person($report->reportedUser) ?? []), 'is_flagged' => (bool) $report->reportedUser?->isFlagged()],
            'order' => $report->order ? ['id' => $report->order->id, 'number' => $report->order->reference] : null,
            'excerpt' => str($report->description)->squish()->limit(140)->toString(),
            'at' => app(ModerationService::class)->localDate($report->created_at),
        ];
    }
}
