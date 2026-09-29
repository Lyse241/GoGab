<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\Auth\ResubmitAccountRequest;
use App\Models\Category;
use App\Models\Document;
use App\Models\Neighborhood;
use App\Services\AccountValidationService;
use App\Services\StoreHours;
use App\Support\DocumentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Correction d'un dossier refusé par l'utilisateur, puis renvoi pour validation.
 */
class AccountCorrectionController extends Controller
{
    public function __construct(private readonly AccountValidationService $validation) {}

    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (Gate::denies('resubmit', $user)) {
            return redirect()->route($user->homeRoute());
        }

        $user->load(['deliveryProfile', 'store.openingHours', 'documents.reviewer:id,name']);

        $mustResend = $this->validation->documentsToResend($user);
        $required = $this->validation->requiredDocuments($user);
        $documents = $user->documents->keyBy(fn (Document $document) => $document->type->value);
        $profile = $user->deliveryProfile;
        $store = $user->store;

        return Inertia::render('Account/Correction', [
            'rejectionReason' => $user->rejection_reason,
            'role' => $user->role->value,
            'values' => [
                'name' => $user->name,
                'phone' => $user->phone,
                // Entreprise : l'adresse est celle du commerce.
                'neighborhood_id' => (string) ($store?->neighborhood_id ?? $user->neighborhood_id ?? ''),
                'address_landmarks' => $store?->address_landmarks ?? $user->address_landmarks ?? '',
                'vehicle_brand' => $profile?->vehicle_brand ?? '',
                'plate_number' => $profile?->plate_number ?? '',
                'license_number' => $profile?->license_number ?? '',
                'base_neighborhood_id' => (string) ($profile?->base_neighborhood_id ?? ''),
                'store_name' => $store?->name ?? '',
                'category_id' => (string) ($store?->category_id ?? ''),
                'description' => $store?->description ?? '',
                'store_phone' => $store?->phone ?? '',
                'opening_hours' => $store ? StoreHours::schedule($store) : [],
            ],
            'vehicle' => $profile ? [
                'type' => $profile->vehicle_type->value,
                'label' => $profile->vehicle_type->label(),
                'requires_license' => $profile->vehicle_type->requiresLicense(),
            ] : null,
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            'categories' => $store ? Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : [],
            // Un emplacement par document possible : état actuel + obligation de renvoi.
            'documents' => array_map(fn (DocumentType $type) => [
                'spec' => $type->toUploader(),
                'required' => in_array($type, $required, true),
                'must_resend' => in_array($type, $mustResend, true),
                'current' => isset($documents[$type->value])
                    ? DocumentPresenter::present($documents[$type->value], in_array($type, $required, true))
                    : null,
            ], $this->validation->resendableDocuments($user)),
        ]);
    }

    public function update(ResubmitAccountRequest $request): RedirectResponse
    {
        $this->validation->resubmit(
            $request->user(),
            $request->safe()->except('documents'),
            $request->file('documents', []),
        );

        return redirect()->route('account.pending')
            ->with('success', 'Votre dossier corrigé a bien été renvoyé.')
            ->with('registered', true);
    }
}
