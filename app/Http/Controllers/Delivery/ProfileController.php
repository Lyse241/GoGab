<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\UpdateBaseNeighborhoodRequest;
use App\Models\Document;
use App\Models\Neighborhood;
use App\Services\CourierProfileService;
use App\Support\DocumentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil du livreur (/delivery/profile) : informations personnelles, véhicule, quartier de base
 * modifiable (zone des offres), statut des documents ; interrupteur de disponibilité.
 * Nom, téléphone, e-mail et mot de passe se modifient sur /profile (Breeze).
 */
class ProfileController extends Controller
{
    public function __construct(private readonly CourierProfileService $courier) {}

    public function show(Request $request): Response
    {
        $user = $request->user()->load(['deliveryProfile.baseNeighborhood', 'documents.reviewer:id,name']);
        $profile = $user->deliveryProfile ?? abort(404, 'Aucun profil livreur n’est rattaché à ce compte.');
        $required = DocumentType::requiredFor($user->role, $profile->vehicle_type);
        $documents = $user->documents->keyBy(fn (Document $document) => $document->type->value);

        return Inertia::render('Delivery/Profile', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->account_status->value,
                'approved_at' => $user->approved_at?->setTimezone(config('gogab.timezone'))->format('d/m/Y'),
            ],
            'vehicle' => [
                'type' => $profile->vehicle_type?->label(),
                'brand' => $profile->vehicle_brand,
                'plate_number' => $profile->plate_number,
                'license_number' => $profile->license_number,
            ],
            'baseNeighborhoodId' => $profile->base_neighborhood_id,
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            // Documents obligatoires (envoyés ou manquants), puis les autres documents envoyés.
            'documents' => [
                ...collect($required)->map(fn (DocumentType $type) => $documents->has($type->value)
                    ? DocumentPresenter::present($documents[$type->value])
                    : ['id' => null, 'type' => $type->value, 'label' => $type->label(), 'required' => true, 'status' => null]),
                ...$documents->reject(fn (Document $document) => in_array($document->type, $required, true))
                    ->map(fn (Document $document) => DocumentPresenter::present($document, required: false))
                    ->values(),
            ],
        ]);
    }

    /**
     * « Je suis disponible / indisponible ».
     */
    public function availability(Request $request): RedirectResponse
    {
        $validated = $request->validate(['is_available' => ['required', 'boolean']]);
        $profile = $this->courier->setAvailability($request->user(), (bool) $validated['is_available']);

        return back()->with('success', $profile->is_available
            ? 'Vous êtes disponible : les offres de votre zone vous sont proposées.'
            : 'Vous êtes indisponible : vous ne recevez plus d’offres.');
    }

    /**
     * Quartier de base : change la zone des offres.
     */
    public function updateBaseNeighborhood(UpdateBaseNeighborhoodRequest $request): RedirectResponse
    {
        $profile = $this->courier->setBaseNeighborhood(
            $request->user(),
            Neighborhood::findOrFail($request->validated('base_neighborhood_id')),
        );

        return back()->with('success', "Quartier de base : {$profile->baseNeighborhood->name}. Vous recevez maintenant les offres de la zone {$profile->baseNeighborhood->zone}.");
    }
}
