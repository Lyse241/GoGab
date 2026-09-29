<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateStoreRequest;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Services\StoreHours;
use App\Services\StoreProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Espace entreprise : tableau de bord (interrupteur d'ouverture) et « Mon commerce ».
 * Une entreprise ne gère que son propre commerce (StorePolicy::manage).
 */
class StoreController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $store = $this->store($request)->load(['category:id,name', 'neighborhood:id,name,zone', 'openingHours']);
        $today = StoreHours::now()->isoWeekday();

        return Inertia::render('Business/Dashboard', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'category' => $store->category?->name,
                'neighborhood' => $store->neighborhood?->name,
                'logo' => $store->logo,
                'cover_image' => $store->cover_image,
                'is_open' => $store->is_open,
                ...$store->openingStatus(),
            ],
            'today' => [
                'label' => ucfirst(StoreHours::DAYS[$today]),
                ...StoreHours::schedule($store)[$today - 1],
            ],
            'productsCount' => $store->products()->count(),
        ]);
    }

    /**
     * Interrupteur « Commerce ouvert / fermé ».
     */
    public function toggleOpen(Request $request, StoreProfileService $profile): RedirectResponse
    {
        $validated = $request->validate(['is_open' => ['required', 'boolean']]);
        $store = $profile->setOpen($this->store($request), (bool) $validated['is_open']);

        return back()->with('success', $store->is_open
            ? 'Commerce ouvert : les clients peuvent commander pendant vos horaires.'
            : 'Commerce fermé : plus aucune commande jusqu’à sa réouverture.');
    }

    public function edit(Request $request): Response
    {
        $store = $this->store($request)->load('openingHours');

        return Inertia::render('Business/Store/Edit', [
            'store' => $store->only(['id', 'name', 'category_id', 'description', 'phone', 'neighborhood_id', 'address_landmarks', 'logo', 'cover_image']),
            'openingHours' => StoreHours::schedule($store),
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            'limits' => ['logo' => UpdateStoreRequest::LOGO_MAX_KB * 1024, 'cover_image' => UpdateStoreRequest::COVER_MAX_KB * 1024],
        ]);
    }

    public function update(UpdateStoreRequest $request, StoreProfileService $profile): RedirectResponse
    {
        $profile->update(
            $this->store($request),
            $request->validated(),
            $request->file('logo'),
            $request->file('cover_image'),
        );

        return back()->with('success', 'Votre commerce a été mis à jour.');
    }

    /**
     * Commerce de l'entreprise connectée (créé à l'inscription).
     */
    private function store(Request $request): Store
    {
        $store = $request->user()->store;

        abort_if($store === null, 404, 'Aucun commerce n’est rattaché à ce compte.');
        Gate::authorize('manage', $store);

        return $store;
    }
}
