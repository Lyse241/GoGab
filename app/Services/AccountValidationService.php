<?php

namespace App\Services;

use App\Enums\AccountDecisionAction;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\AccountDecision;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validation des comptes par l'admin, et correction d'un dossier refusé par l'utilisateur.
 *
 * Chaque action vérifie la Policy (UserPolicy / DocumentPolicy) avant d'agir, et laisse une trace
 * dans l'historique du dossier (account_decisions).
 */
class AccountValidationService
{
    public function __construct(private readonly DocumentService $documents) {}

    /**
     * Documents obligatoires du compte (source unique : DocumentType::requiredFor()).
     *
     * @return list<DocumentType>
     */
    public function requiredDocuments(User $account): array
    {
        return DocumentType::requiredFor($account->role, $account->deliveryProfile?->vehicle_type);
    }

    /**
     * Documents obligatoires absents ou pas encore approuvés.
     *
     * @return list<DocumentType>
     */
    public function missingApprovals(User $account): array
    {
        $approved = $account->documents()
            ->where('status', DocumentStatus::Approved)
            ->pluck('type')
            ->map(fn ($type) => $type instanceof DocumentType ? $type : DocumentType::from($type))
            ->all();

        return array_values(array_filter(
            $this->requiredDocuments($account),
            fn (DocumentType $type) => ! in_array($type, $approved, true),
        ));
    }

    /**
     * La validation ne concerne que les dossiers en attente ou refusés ; un compte bloqué
     * se gère par la modération (déblocage motivé et tracé).
     */
    public function isUnderReview(User $account): bool
    {
        return in_array($account->account_status, [AccountStatus::Pending, AccountStatus::Rejected], true);
    }

    public function canApprove(User $account): bool
    {
        return $this->isUnderReview($account)
            && $this->missingApprovals($account) === [];
    }

    // --- Documents ---

    public function approveDocument(User $admin, Document $document): void
    {
        Gate::forUser($admin)->authorize('review', $document);

        $document->update([
            'status' => DocumentStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        AccountDecision::record($document->user, AccountDecisionAction::DocumentApproved, $admin, $document);
    }

    public function rejectDocument(User $admin, Document $document, string $reason): void
    {
        Gate::forUser($admin)->authorize('review', $document);

        $document->update([
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        AccountDecision::record($document->user, AccountDecisionAction::DocumentRejected, $admin, $document, $reason);
    }

    // --- Compte ---

    /**
     * Valide le compte : tous les documents obligatoires doivent être approuvés.
     * Active le commerce d'une entreprise et souhaite la bienvenue à l'utilisateur.
     *
     * @throws ValidationException
     */
    public function approveAccount(User $admin, User $account): void
    {
        Gate::forUser($admin)->authorize('review', $account);

        if ($account->account_status === AccountStatus::Approved) {
            throw ValidationException::withMessages(['account' => 'Ce compte est déjà validé.']);
        }

        if ($account->isBlocked()) {
            throw ValidationException::withMessages(['account' => 'Ce compte est bloqué : utilisez « Débloquer » dans la modération.']);
        }

        if ($missing = $this->missingApprovals($account)) {
            throw ValidationException::withMessages([
                'account' => 'Impossible de valider : documents obligatoires non approuvés ('
                    .implode(', ', array_map(fn (DocumentType $type) => $type->label(), $missing)).').',
            ]);
        }

        DB::transaction(function () use ($admin, $account) {
            $account->update([
                'account_status' => AccountStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
            ]);

            // Le commerce devient visible côté public.
            if ($account->isBusiness()) {
                $account->store?->update(['is_active' => true]);
            }

            AccountDecision::record($account, AccountDecisionAction::AccountApproved, $admin);
        });

        [$title, $message] = match ($account->role) {
            Role::Delivery => ['Bienvenue parmi les livreurs Gogab !', 'Votre compte est validé : activez votre disponibilité et acceptez vos premières courses.'],
            Role::Business => ['Votre commerce est en ligne !', "Votre compte est validé : « {$account->store?->name} » est désormais visible par les clients."],
            default => ['Bienvenue sur Gogab !', 'Votre compte est validé : vous pouvez maintenant commander auprès des commerces de votre quartier.'],
        };

        Notifier::send($account, $title, $message, route($account->fresh()->homeRoute()), 'success');
    }

    /**
     * Refuse l'inscription (motif obligatoire) : l'utilisateur pourra corriger et renvoyer son dossier.
     *
     * @throws ValidationException
     */
    public function rejectAccount(User $admin, User $account, string $reason): void
    {
        Gate::forUser($admin)->authorize('review', $account);

        if (! $this->isUnderReview($account)) {
            throw ValidationException::withMessages(['account' => $account->isBlocked()
                ? 'Ce compte est bloqué : sa situation se gère dans la modération.'
                : 'Ce compte est déjà validé : il ne peut plus être refusé.']);
        }

        DB::transaction(function () use ($admin, $account, $reason) {
            $account->update([
                'account_status' => AccountStatus::Rejected,
                'rejection_reason' => $reason,
                'approved_at' => null,
                'approved_by' => null,
            ]);

            if ($account->isBusiness()) {
                $account->store?->update(['is_active' => false]);
            }

            AccountDecision::record($account, AccountDecisionAction::AccountRejected, $admin, note: $reason);
        });

        Notifier::send(
            $account,
            'Votre inscription n’a pas été validée',
            "Motif : {$reason} Corrigez votre dossier puis renvoyez-le.",
            route('account.rejected'),
            'warning',
        );
    }

    // --- Correction par l'utilisateur ---

    /**
     * Documents que l'utilisateur doit renvoyer : obligatoires refusés ou absents.
     *
     * @return list<DocumentType>
     */
    public function documentsToResend(User $account): array
    {
        $documents = $account->documents()->get()->keyBy(fn (Document $document) => $document->type->value);

        return array_values(array_filter(
            $this->requiredDocuments($account),
            fn (DocumentType $type) => ! isset($documents[$type->value]) || $documents[$type->value]->status === DocumentStatus::Rejected,
        ));
    }

    /**
     * Documents que l'utilisateur peut (re)envoyer : obligatoires + facultatifs de son profil.
     *
     * @return list<DocumentType>
     */
    public function resendableDocuments(User $account): array
    {
        return [...$this->requiredDocuments($account), ...DocumentType::optionalFor($account->role)];
    }

    /**
     * Enregistre les corrections, remplace les documents renvoyés, repasse le compte en attente
     * et prévient les administrateurs.
     *
     * @param  array<string, mixed>  $data  données validées (ResubmitAccountRequest)
     * @param  array<string, UploadedFile>  $files  documents renvoyés, indexés par type
     */
    public function resubmit(User $account, array $data, array $files): void
    {
        Gate::forUser($account)->authorize('resubmit', $account);

        $stored = [];

        try {
            DB::transaction(function () use ($account, $data, $files, &$stored) {
                // Entreprise : quartier et repères sont ceux du commerce (le gérant suit le quartier).
                $userFields = $account->isBusiness()
                    ? ['name', 'phone', 'neighborhood_id']
                    : ['name', 'phone', 'neighborhood_id', 'address_landmarks'];
                $account->update(array_intersect_key($data, array_flip($userFields)));

                if ($account->isDelivery() && $account->deliveryProfile) {
                    $vehicle = $account->deliveryProfile->vehicle_type;
                    $account->deliveryProfile->update([
                        'vehicle_brand' => $data['vehicle_brand'] ?? null,
                        'plate_number' => $vehicle === VehicleType::Bicycle ? null : ($data['plate_number'] ?? null),
                        'license_number' => $vehicle === VehicleType::Bicycle ? null : ($data['license_number'] ?? null),
                        'base_neighborhood_id' => $data['base_neighborhood_id'],
                    ]);
                }

                if ($account->isBusiness() && $account->store) {
                    $account->store->update([
                        'name' => $data['store_name'],
                        'category_id' => $data['category_id'],
                        'description' => $data['description'] ?? null,
                        'phone' => $data['store_phone'],
                        'neighborhood_id' => $data['neighborhood_id'],
                        'address_landmarks' => $data['address_landmarks'],
                    ]);
                    StoreHours::sync($account->store, $data['opening_hours']);
                }

                // Un document renvoyé remplace le précédent du même type et repart en vérification
                // (l'ancien fichier n'est supprimé que si la correction est bien enregistrée).
                foreach ($this->resendableDocuments($account) as $type) {
                    if ($file = $files[$type->value] ?? null) {
                        $stored[] = $this->documents->store($account, $file, $type)->file_path;
                    }
                }

                $account->update([
                    'account_status' => AccountStatus::Pending,
                    'rejection_reason' => null,
                ]);

                AccountDecision::record($account, AccountDecisionAction::Resubmitted, $account);
            });
        } catch (Throwable $exception) {
            $this->documents->deleteFiles($stored);

            throw $exception;
        }

        Notifier::admins(
            'Dossier corrigé à revalider',
            "{$account->name} ({$account->role->label()}) a corrigé son dossier : il attend une nouvelle vérification.",
            route('admin.accounts.show', $account),
            'warning',
        );
    }

    // --- Documents depuis le profil (livreur, entreprise) ---

    /**
     * Remplace un document depuis « Mon profil ». Il repart en vérification ; le compte ne repasse
     * en revalidation que si le document est obligatoire et que le compte était validé.
     *
     * @return bool true si le compte repasse en attente de validation
     *
     * @throws ValidationException document non modifiable, commande en cours
     */
    public function replaceDocument(User $account, DocumentType $type, UploadedFile $file): bool
    {
        if (! in_array($type, $this->resendableDocuments($account), true)) {
            throw ValidationException::withMessages(['document' => 'Ce document ne concerne pas votre compte.']);
        }

        // Inscription refusée : la correction passe par la page dédiée (tout le dossier est revu).
        if ($account->account_status === AccountStatus::Rejected) {
            throw ValidationException::withMessages(['document' => 'Votre inscription a été refusée : renvoyez vos documents depuis la page « Corriger et renvoyer ».']);
        }

        $required = in_array($type, $this->requiredDocuments($account), true);
        $revalidation = $required && $account->account_status === AccountStatus::Approved;

        // Revalidation = accès suspendu jusqu'à la décision : pas au milieu d'une commande.
        if ($revalidation && app(ModerationService::class)->activeOrders($account)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'document' => 'Vous avez une commande en cours : remplacez ce document une fois qu’elle sera terminée (votre compte devra être revalidé).',
            ]);
        }

        $stored = null;

        try {
            DB::transaction(function () use ($account, $type, $file, $revalidation, &$stored) {
                $document = $this->documents->store($account, $file, $type);
                $stored = $document->file_path;

                if ($revalidation) {
                    $account->update(['account_status' => AccountStatus::Pending]);
                    // Plus d'annonces tant que le compte n'est pas revalidé.
                    $account->deliveryProfile?->update(['is_available' => false]);
                }

                AccountDecision::record($account, AccountDecisionAction::DocumentReplaced, $account, $document, $revalidation ? 'Document obligatoire : compte à revalider.' : null);
            });
        } catch (Throwable $exception) {
            $this->documents->deleteFiles($stored);

            throw $exception;
        }

        Notifier::admins(
            $revalidation ? 'Document à revalider' : 'Nouveau document à vérifier',
            $revalidation
                ? "{$account->name} ({$account->role->label()}) a remplacé « {$type->label()} » : le compte attend une nouvelle validation."
                : "{$account->name} ({$account->role->label()}) a envoyé « {$type->label()} ».",
            route('admin.accounts.show', $account),
            $revalidation ? 'warning' : 'info',
        );

        return $revalidation;
    }
}
