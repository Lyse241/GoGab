<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\ModerationReason;
use App\Enums\ModerationType;
use App\Enums\OrderStatus;
use App\Models\ModerationAction;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Modération des comptes : avertissement, blocage (temporaire ou jusqu'à nouvel ordre), déblocage,
 * signalement interne. Chaque action vérifie UserPolicy::moderate, est tracée (moderation_actions)
 * et notifiée à l'utilisateur (sauf le signalement, strictement interne).
 */
class ModerationService
{
    /**
     * Durées de blocage proposées (null = jusqu'à nouvel ordre).
     */
    public const DURATIONS = [
        '24h' => ['label' => '24 heures', 'hours' => 24],
        '7d' => ['label' => '7 jours', 'hours' => 24 * 7],
        '30d' => ['label' => '30 jours', 'hours' => 24 * 30],
        'indefinite' => ['label' => 'Jusqu’à nouvel ordre', 'hours' => null],
    ];

    /**
     * À partir de ce nombre d'avertissements, l'admin voit une alerte suggérant un blocage.
     */
    public const WARNING_ALERT_THRESHOLD = 3;

    // --- Avertissement ---

    public function warn(User $admin, User $account, ModerationReason $reason, string $message): ModerationAction
    {
        Gate::forUser($admin)->authorize('moderate', $account);

        $action = $this->record($account, $admin, ModerationType::Warning, $reason, $message);

        Notifier::send(
            $account,
            'Vous avez reçu un avertissement',
            "{$reason->label()} : {$message}",
            route('account.warnings'),
            'warning',
        );

        return $action;
    }

    /**
     * L'utilisateur accuse réception (« J'ai compris »).
     */
    public function acknowledge(User $user, ModerationAction $warning): void
    {
        abort_unless($warning->user_id === $user->id && $warning->type === ModerationType::Warning, 404);

        if ($warning->acknowledged_at === null) {
            $warning->update(['acknowledged_at' => now()]);
        }
    }

    public function warningsCount(User $account): int
    {
        return $account->moderationActions()->where('type', ModerationType::Warning)->count();
    }

    /**
     * Plus ancien avertissement non lu (affiché jusqu'à ce que l'utilisateur l'accuse).
     */
    public function pendingWarning(User $user): ?ModerationAction
    {
        return ModerationAction::query()
            ->where('user_id', $user->id)
            ->where('type', ModerationType::Warning)
            ->whereNull('acknowledged_at')
            ->oldest('created_at')
            ->oldest('id')
            ->first();
    }

    // --- Blocage ---

    /**
     * Bloque un compte validé : statut suspended, blocked_until (null = sans limite), livreur indisponible ;
     * le commerce d'une entreprise disparaît des listes publiques (Store::visible()).
     *
     * @param  string  $duration  clé de self::DURATIONS
     *
     * @throws ValidationException
     */
    public function block(User $admin, User $account, ModerationReason $reason, string $message, string $duration): ModerationAction
    {
        Gate::forUser($admin)->authorize('moderate', $account);

        if ($account->account_status !== AccountStatus::Approved) {
            throw ValidationException::withMessages([
                'moderation' => $account->isBlocked()
                    ? 'Ce compte est déjà bloqué.'
                    : 'Seul un compte validé peut être bloqué : pour une inscription en attente, refusez-la.',
            ]);
        }

        $hours = self::DURATIONS[$duration]['hours'] ?? null;
        $endsAt = $hours ? CarbonImmutable::now()->addHours($hours) : null;

        $action = DB::transaction(function () use ($admin, $account, $reason, $message, $endsAt) {
            $account->update([
                'account_status' => AccountStatus::Suspended,
                'blocked_until' => $endsAt,
            ]);

            // Un livreur bloqué ne reçoit plus de courses.
            $account->deliveryProfile?->update(['is_available' => false]);

            return $this->record($account, $admin, ModerationType::Block, $reason, $message, endsAt: $endsAt);
        });

        Notifier::send(
            $account,
            'Votre compte est bloqué',
            $endsAt
                ? "{$reason->label()} : {$message} Fin du blocage le {$this->localDate($endsAt)}."
                : "{$reason->label()} : {$message} Blocage jusqu’à nouvel ordre.",
            route('account.suspended'),
            'warning',
        );

        return $action;
    }

    /**
     * Débloque un compte (manuellement avec un motif, ou automatiquement quand le blocage a expiré).
     *
     * @throws ValidationException
     */
    public function unblock(?User $admin, User $account, ?ModerationReason $reason, ?string $message = null): ModerationAction
    {
        if ($admin) {
            Gate::forUser($admin)->authorize('moderate', $account);
        }

        if (! $account->isBlocked()) {
            throw ValidationException::withMessages(['moderation' => 'Ce compte n’est pas bloqué.']);
        }

        $action = DB::transaction(function () use ($admin, $account, $reason, $message) {
            $account->update([
                'account_status' => AccountStatus::Approved,
                'blocked_until' => null,
            ]);

            return $this->record(
                $account,
                $admin,
                ModerationType::Unblock,
                $reason,
                $message ?? ($admin ? null : 'Fin du blocage temporaire.'),
            );
        });

        Notifier::send(
            $account,
            'Votre compte est débloqué',
            $admin
                ? 'Vous pouvez de nouveau utiliser Gogab.'.($message ? " {$message}" : '')
                : 'Votre blocage temporaire est terminé : vous pouvez de nouveau utiliser Gogab.',
            route($account->fresh()->homeRoute()),
            'success',
        );

        return $action;
    }

    /**
     * Tâche planifiée : débloque les comptes dont le blocage temporaire est terminé.
     *
     * @return int nombre de comptes débloqués
     */
    public function unblockExpired(): int
    {
        $expired = User::query()
            ->where('account_status', AccountStatus::Suspended)
            ->whereNotNull('blocked_until')
            ->where('blocked_until', '<=', now())
            ->get();

        $expired->each(fn (User $account) => $this->unblock(null, $account, null));

        return $expired->count();
    }

    /**
     * Commandes en cours liées au compte (client, livreur ou commerce de l'entreprise),
     * affichées avant de confirmer un blocage.
     *
     * @return Collection<int, Order>
     */
    public function activeOrders(User $account): Collection
    {
        $finished = [OrderStatus::Delivered, OrderStatus::Refused, OrderStatus::Cancelled];

        return Order::query()
            ->with(['store:id,name', 'client:id,name'])
            ->whereNotIn('status', $finished)
            ->where(fn (Builder $query) => $query
                ->where('client_id', $account->id)
                ->orWhere('delivery_id', $account->id)
                ->orWhereHas('store', fn (Builder $store) => $store->where('owner_id', $account->id)))
            ->latest()
            ->get();
    }

    // --- Signalement interne ---

    /**
     * Pose un drapeau visible des admins seulement (aucune notification à l'utilisateur).
     */
    public function flag(User $admin, User $account, ModerationReason $reason, string $note): ModerationAction
    {
        Gate::forUser($admin)->authorize('moderate', $account);

        return DB::transaction(function () use ($admin, $account, $reason, $note) {
            $account->update(['flagged_at' => now()]);

            return $this->record($account, $admin, ModerationType::Flag, $reason, internalNote: $note);
        });
    }

    /**
     * @throws ValidationException
     */
    public function unflag(User $admin, User $account, ?string $note = null): ModerationAction
    {
        Gate::forUser($admin)->authorize('moderate', $account);

        if (! $account->isFlagged()) {
            throw ValidationException::withMessages(['moderation' => 'Ce compte n’est pas signalé.']);
        }

        return DB::transaction(function () use ($admin, $account, $note) {
            $account->update(['flagged_at' => null]);

            return $this->record($account, $admin, ModerationType::Unflag, internalNote: $note);
        });
    }

    // --- Outils ---

    private function record(
        User $account,
        ?User $admin,
        ModerationType $type,
        ?ModerationReason $reason = null,
        ?string $message = null,
        ?string $internalNote = null,
        ?CarbonImmutable $endsAt = null,
    ): ModerationAction {
        return $account->moderationActions()->create([
            'admin_id' => $admin?->id,
            'type' => $type,
            'reason' => $reason,
            'message' => $message,
            'internal_note' => $internalNote,
            'ends_at' => $endsAt,
        ]);
    }

    /**
     * Date lisible à l'heure de Libreville.
     */
    public function localDate(\DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone(StoreHours::timezone())->format('d/m/Y à H\hi');
    }
}
