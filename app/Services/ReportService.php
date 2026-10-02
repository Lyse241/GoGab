<?php

namespace App\Services;

use App\Enums\ModerationReason;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signalements entre utilisateurs, uniquement dans le cadre d'une commande réelle :
 * - client → entreprise ou livreur ; entreprise → client ou livreur ; livreur → client ou entreprise ;
 * - un seul signalement à traiter par signalant, personne signalée et commande ;
 * - les admins sont notifiés ; la personne signalée ne l'est jamais ;
 * - traitement admin (avertir, bloquer via ModerationService, classer, marquer traité),
 *   le signalant est notifié de l'issue sans détail de la sanction.
 */
class ReportService
{
    /**
     * Actions de traitement proposées à l'admin.
     */
    public const ACTIONS = ['warn', 'block', 'dismiss', 'resolve'];

    public function __construct(private readonly ModerationService $moderation) {}

    /**
     * Personnes que $reporter peut signaler pour cette commande (l'autre ou les autres parties).
     *
     * @return Collection<int, array{user: User, label: string}>
     */
    public function parties(Order $order, User $reporter): Collection
    {
        $order->loadMissing(['store.owner', 'client', 'delivery']);
        $owner = $order->store?->owner;

        $client = $order->client ? ['user' => $order->client, 'label' => 'Le client · '.$this->firstName($order->client)] : null;
        $business = $owner ? ['user' => $owner, 'label' => 'Le commerce · '.$order->store->name] : null;
        $courier = $order->delivery ? ['user' => $order->delivery, 'label' => 'Le livreur · '.$this->firstName($order->delivery)] : null;

        $parties = match (true) {
            $order->client_id === $reporter->id && $reporter->isClient() => [$business, $courier],
            $owner?->id === $reporter->id && $reporter->isBusiness() => [$client, $courier],
            $order->delivery_id === $reporter->id && $reporter->isDelivery() => [$client, $business],
            default => [],
        };

        return collect($parties)->filter()->reject(fn (array $party) => $party['user']->is($reporter))->values();
    }

    /**
     * Données du bouton « Signaler un problème » d'une page de commande (null : rien à signaler).
     *
     * @return array{order_id: int, parties: list<array<string, mixed>>, reasons: list<array{value: string, label: string}>}|null
     */
    public function formFor(Order $order, User $reporter): ?array
    {
        $parties = $this->parties($order, $reporter);
        if ($parties->isEmpty()) {
            return null;
        }

        $alreadyReported = Report::query()
            ->pending()
            ->where('order_id', $order->id)
            ->where('reporter_id', $reporter->id)
            ->pluck('reported_user_id')
            ->all();

        return [
            'order_id' => $order->id,
            'parties' => $parties->map(fn (array $party) => [
                'id' => $party['user']->id,
                'label' => $party['label'],
                'already_reported' => in_array($party['user']->id, $alreadyReported, true),
            ])->all(),
            'reasons' => ReportReason::options(),
        ];
    }

    /**
     * Crée le signalement et prévient les admins.
     *
     * @throws ValidationException personne étrangère à la commande ou doublon
     */
    public function create(User $reporter, Order $order, User $reported, ReportReason $reason, string $description): Report
    {
        if (! $this->parties($order, $reporter)->contains(fn (array $party) => $party['user']->is($reported))) {
            throw ValidationException::withMessages([
                'reported_user_id' => 'Vous ne pouvez signaler que l’autre partie de cette commande.',
            ]);
        }

        $report = DB::transaction(function () use ($reporter, $order, $reported, $reason, $description) {
            // Verrou sur la commande : deux envois simultanés ne créent pas deux signalements.
            Order::whereKey($order->id)->lockForUpdate()->first();

            $duplicate = Report::query()
                ->pending()
                ->where('order_id', $order->id)
                ->where('reporter_id', $reporter->id)
                ->where('reported_user_id', $reported->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reported_user_id' => 'Vous avez déjà signalé cette personne pour cette commande : notre équipe s’en occupe.',
                ]);
            }

            return Report::create([
                'reporter_id' => $reporter->id,
                'reported_user_id' => $reported->id,
                'order_id' => $order->id,
                'reason' => $reason,
                'description' => trim($description),
                'status' => ReportStatus::Open,
            ]);
        });

        Notifier::admins(
            $reason->isUrgent() ? 'Nouveau signalement (urgent)' : 'Nouveau signalement',
            "{$reporter->role->label()} {$reporter->name} signale {$reported->name} ({$reported->role->label()}) · {$reason->label()} · commande {$order->reference}.",
            route('admin.reports.show', $report),
            $reason->isUrgent() ? 'warning' : 'info',
        );

        return $report;
    }

    /**
     * Un admin ouvre un signalement : il passe « en cours d'examen ».
     */
    public function markInReview(User $admin, Report $report): void
    {
        Gate::forUser($admin)->authorize('view', $report);

        if ($report->status === ReportStatus::Open) {
            $report->update(['status' => ReportStatus::InReview]);
        }
    }

    /**
     * Traite le signalement : avertissement ou blocage de la personne signalée (ModerationService),
     * classement sans suite ou simple clôture ; note admin ; notification au signalant.
     *
     * @param  array{message?: ?string, duration?: ?string, moderation_reason?: ?ModerationReason}  $sanction
     *
     * @throws ValidationException signalement déjà clos, sanction impossible
     */
    public function handle(User $admin, Report $report, string $action, ?string $adminNote, array $sanction = []): Report
    {
        Gate::forUser($admin)->authorize('view', $report);

        if (! $report->status->isPending()) {
            throw ValidationException::withMessages(['report' => 'Ce signalement est déjà clos.']);
        }

        $report->loadMissing(['reportedUser', 'order']);
        $reason = $sanction['moderation_reason'] ?? $report->reason->moderationReason();

        // La sanction d'abord : si elle est impossible (compte déjà bloqué…), rien n'est clos.
        match ($action) {
            'warn' => $this->moderation->warn($admin, $report->reportedUser, $reason, (string) $sanction['message']),
            'block' => $this->moderation->block($admin, $report->reportedUser, $reason, (string) $sanction['message'], (string) $sanction['duration']),
            default => null,
        };

        $report->update([
            'status' => $action === 'dismiss' ? ReportStatus::Dismissed : ReportStatus::Resolved,
            'handled_by' => $admin->id,
            'handled_at' => now(),
            'admin_note' => filled($adminNote) ? trim($adminNote) : null,
        ]);

        // Le signalant connaît l'issue, jamais la sanction.
        Notifier::send(
            $report->reporter,
            'Votre signalement a été traité',
            'Merci : notre équipe a examiné votre signalement'.($report->order ? " concernant la commande {$report->order->reference}" : '').'.',
            null,
            'success',
        );

        return $report;
    }

    private function firstName(User $user): string
    {
        return Str::before(trim($user->name), ' ') ?: $user->name;
    }
}
