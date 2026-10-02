<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\ReplaceDocumentRequest;
use App\Models\Document;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\AccountValidationService;
use App\Support\DocumentPresenter;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mon profil » pour tous les rôles : informations (nom, téléphone, e-mail, quartier, repères),
 * mot de passe, documents (livreur, entreprise) et suppression du compte (anonymisation).
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AccountValidationService $validation) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'neighborhood_id' => $user->neighborhood_id,
                'address_landmarks' => $user->address_landmarks,
                'address_required' => $user->isClient(),
            ],
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            'documents' => $user->isDelivery() || $user->isBusiness() ? $this->documents($user) : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profil enregistré.');
    }

    /**
     * Remplace (ou renvoie) un document : obligatoire sur un compte validé → revalidation.
     */
    public function replaceDocument(ReplaceDocumentRequest $request): RedirectResponse
    {
        $type = $request->type();
        $revalidation = $this->validation->replaceDocument($request->user(), $type, $request->file('file'));

        return $revalidation
            ? Redirect::route('account.pending')->with('warning', "« {$type->label()} » envoyé : votre compte doit être revalidé par l’équipe Gogab. Vous serez prévenu dès que c’est fait.")
            : Redirect::route('profile.edit')->with('success', "« {$type->label()} » envoyé : il sera vérifié par l’équipe Gogab.");
    }

    /**
     * Supprime le compte : anonymisation (l'historique des commandes des autres parties reste).
     */
    public function destroy(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Saisissez votre mot de passe pour confirmer.',
            'password.current_password' => 'Mot de passe incorrect.',
        ]);

        $user = $request->user();
        $deletion->delete($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('success', 'Votre compte a été supprimé. Merci d’avoir utilisé Gogab.');
    }

    /**
     * Documents du compte : obligatoires (envoyés ou manquants), puis facultatifs ; chacun avec
     * son statut, son motif de refus et la description de l'emplacement d'envoi.
     *
     * @return array<string, mixed>
     */
    private function documents(User $user): array
    {
        $user->load('documents.reviewer:id,name');
        $sent = $user->documents->keyBy(fn (Document $document) => $document->type->value);
        $required = $this->validation->requiredDocuments($user);
        $approved = $user->account_status === AccountStatus::Approved;

        return [
            'items' => collect($this->validation->resendableDocuments($user))
                ->map(function (DocumentType $type) use ($sent, $required) {
                    $isRequired = in_array($type, $required, true);
                    $document = $sent->get($type->value);

                    return [
                        ...($document ? DocumentPresenter::present($document, $isRequired) : [
                            'id' => null,
                            'type' => $type->value,
                            'label' => $type->label(),
                            'required' => $isRequired,
                            'status' => null,
                        ]),
                        'uploader' => $type->toUploader(),
                        // Remplacer un document obligatoire d'un compte validé le renvoie en validation.
                        'triggers_revalidation' => $isRequired,
                    ];
                })
                ->values(),
            'account_approved' => $approved,
            'can_replace' => $user->account_status !== AccountStatus::Rejected,
        ];
    }
}
