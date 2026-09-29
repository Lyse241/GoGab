<?php

namespace App\Http\Controllers\Auth;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Http\Requests\Auth\RegisterClientRequest;
use App\Http\Requests\Auth\RegisterDeliveryRequest;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Services\AccountRegistration;
use App\Services\StoreHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscription publique : choix du profil, puis formulaire propre à chaque profil.
 */
class RegisteredUserController extends Controller
{
    /**
     * « Choisissez votre profil » : client, livreur ou entreprise.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register/Index');
    }

    public function createClient(): Response
    {
        return Inertia::render('Auth/Register/Client', [
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
        ]);
    }

    /**
     * Crée le compte client (en attente de validation, sauf gogab.auto_approve_clients),
     * prévient les admins, connecte l'utilisateur puis l'envoie vers l'état de son compte.
     */
    public function storeClient(RegisterClientRequest $request, AccountRegistration $registration): RedirectResponse
    {
        $user = $registration->registerClient($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->isApproved()) {
            return redirect()->route('home')
                ->with('success', "Bienvenue sur Gogab, {$user->name} ! Vous pouvez commander dès maintenant.");
        }

        return redirect()->route('account.pending')
            ->with('success', 'Votre compte a bien été créé.')
            ->with('registered', true);
    }

    /**
     * Inscription livreur en 3 étapes (informations, véhicule, documents).
     */
    public function createDelivery(): Response
    {
        return Inertia::render('Auth/Register/Delivery', [
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            'vehicleTypes' => collect(VehicleType::cases())->map(fn (VehicleType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'requires_license' => $type->requiresLicense(),
            ]),
            // Documents obligatoires par véhicule : DocumentType::requiredFor() reste la seule source.
            'documentTypes' => collect(DocumentType::cases())->mapWithKeys(fn (DocumentType $type) => [$type->value => $type->toUploader()]),
            'requiredDocuments' => collect(VehicleType::cases())->mapWithKeys(fn (VehicleType $type) => [
                $type->value => array_map(fn (DocumentType $document) => $document->value, DocumentType::requiredFor(Role::Delivery, $type)),
            ]),
        ]);
    }

    /**
     * Vérifie une étape avant de passer à la suivante (e-mail ou plaque déjà utilisés…).
     * Répond 204 si tout est bon, 422 avec les erreurs sinon.
     */
    public function checkDelivery(Request $request): \Illuminate\Http\Response
    {
        $step = (int) $request->validate(['step' => ['required', 'integer', Rule::in([1, 2])]])['step'];
        $input = RegisterDeliveryRequest::normalize($request->except('step'));

        Validator::make($input, RegisterDeliveryRequest::stepRules($step, $input), RegisterDeliveryRequest::stepMessages())
            ->validate();

        return response()->noContent();
    }

    public function storeDelivery(RegisterDeliveryRequest $request, AccountRegistration $registration): RedirectResponse
    {
        $user = $registration->registerDelivery(
            $request->safe()->except('documents'),
            $request->file('documents', []),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.pending')
            ->with('success', 'Votre dossier de livreur a bien été envoyé.')
            ->with('registered', true);
    }

    /**
     * Inscription entreprise en 3 étapes (gérant, commerce, documents).
     */
    public function createBusiness(): Response
    {
        return Inertia::render('Auth/Register/Business', [
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'openingHours' => StoreHours::everyDay('08:00', '22:00'),
            'documentTypes' => collect(DocumentType::cases())->mapWithKeys(fn (DocumentType $type) => [$type->value => $type->toUploader()]),
            'requiredDocuments' => array_map(fn (DocumentType $type) => $type->value, DocumentType::requiredFor(Role::Business)),
            'optionalDocuments' => array_map(fn (DocumentType $type) => $type->value, DocumentType::optionalFor(Role::Business)),
        ]);
    }

    /**
     * Vérifie une étape de l'inscription entreprise (204 ou 422).
     */
    public function checkBusiness(Request $request): \Illuminate\Http\Response
    {
        $step = (int) $request->validate(['step' => ['required', 'integer', Rule::in([1, 2])]])['step'];
        $input = RegisterBusinessRequest::normalize($request->except(['step', 'logo']));
        $rules = RegisterBusinessRequest::stepRules($step);
        unset($rules['logo']); // le logo est vérifié à l'envoi final

        Validator::make($input, $rules, RegisterBusinessRequest::stepMessages())
            ->after(fn ($validator) => $step === 2 ? RegisterBusinessRequest::checkOpeningHoursConsistency($validator, $input['opening_hours'] ?? []) : null)
            ->validate();

        return response()->noContent();
    }

    public function storeBusiness(RegisterBusinessRequest $request, AccountRegistration $registration): RedirectResponse
    {
        $user = $registration->registerBusiness(
            $request->safe()->except(['documents', 'logo']),
            $request->file('logo'),
            $request->file('documents', []),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.pending')
            ->with('success', 'Votre demande d’inscription entreprise a bien été envoyée.')
            ->with('registered', true);
    }
}
