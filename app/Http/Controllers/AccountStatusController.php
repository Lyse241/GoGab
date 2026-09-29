<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\ModerationType;
use App\Models\Document;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages affichées aux comptes non validés : en attente, refusé, suspendu.
 * Chaque page n'est accessible qu'au statut correspondant (sinon, retour à l'espace du compte).
 */
class AccountStatusController extends Controller
{
    public function pending(Request $request): Response|RedirectResponse
    {
        return $this->render($request, AccountStatus::Pending, 'Account/Pending', fn () => [
            // Vrai juste après l'envoi du dossier : écran de confirmation.
            'justRegistered' => (bool) $request->session()->get('registered', false),
        ]);
    }

    public function rejected(Request $request): Response|RedirectResponse
    {
        return $this->render($request, AccountStatus::Rejected, 'Account/Rejected', fn (User $user) => [
            'rejection_reason' => $user->rejection_reason,
        ]);
    }

    public function suspended(Request $request, ModerationService $moderation): Response|RedirectResponse
    {
        return $this->render($request, AccountStatus::Suspended, 'Account/Suspended', function (User $user) use ($moderation) {
            $block = $user->moderationActions()->where('type', ModerationType::Block)->first();

            return [
                'block' => $block ? [
                    'reason' => $block->reason?->label(),
                    'message' => $block->message,
                    'since' => $moderation->localDate($block->created_at),
                ] : null,
                // Date de fin du blocage en cours (null = jusqu'à nouvel ordre).
                'blockedUntil' => $user->blocked_until ? $moderation->localDate($user->blocked_until) : null,
            ];
        });
    }


    /**
     * @param  (callable(User): array<string, mixed>)|null  $extra
     */
    private function render(Request $request, AccountStatus $expected, string $component, ?callable $extra = null): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->account_status !== $expected) {
            return redirect()->route($user->homeRoute());
        }

        return Inertia::render($component, [
            'submission' => $this->submission($user),
            ...($extra ? $extra($user) : []),
        ]);
    }

    /**
     * Récapitulatif de ce que l'utilisateur a envoyé à l'inscription.
     *
     * @return array<string, mixed>
     */
    private function submission(User $user): array
    {
        $user->load([
            'neighborhood:id,name',
            'deliveryProfile.baseNeighborhood:id,name',
            'store.category:id,name',
            'store.neighborhood:id,name',
            'documents' => fn ($query) => $query->latest(),
        ]);

        $profile = $user->deliveryProfile;
        $store = $user->store;

        return [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'neighborhood' => $user->neighborhood?->name,
                'registered_at' => $user->created_at?->format('d/m/Y'),
            ],
            'delivery_profile' => $profile ? [
                'vehicle' => $profile->vehicle_type->label(),
                'vehicle_brand' => $profile->vehicle_brand,
                'plate_number' => $profile->plate_number,
                'license_number' => $profile->license_number,
                'base_neighborhood' => $profile->baseNeighborhood?->name,
            ] : null,
            'store' => $store ? [
                'name' => $store->name,
                'category' => $store->category?->name,
                'neighborhood' => $store->neighborhood?->name,
                'phone' => $store->phone,
            ] : null,
            'documents' => $user->documents->map(fn (Document $document) => [
                'id' => $document->id,
                'type' => $document->type->label(),
                'original_name' => $document->original_name,
                'status' => $document->status->value,
                'status_label' => $document->status->label(),
                'rejection_reason' => $document->rejection_reason,
                'sent_at' => $document->created_at?->format('d/m/Y'),
            ]),
        ];
    }
}
