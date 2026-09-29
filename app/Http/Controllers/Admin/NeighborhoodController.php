<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NeighborhoodRequest;
use App\Models\Neighborhood;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quartiers de Libreville et leur zone. La zone définit « les livreurs autour » d'un commerce :
 * la changer modifie immédiatement les offres reçues par les livreurs (pas de GPS).
 */
class NeighborhoodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Neighborhoods/Index', [
            'neighborhoods' => Neighborhood::query()
                ->withCount(['stores', 'users', 'deliveryProfiles'])
                ->orderBy('name')
                ->get(['id', 'name', 'zone']),
            'zones' => Neighborhood::ZONES,
        ]);
    }

    public function store(NeighborhoodRequest $request): RedirectResponse
    {
        $neighborhood = Neighborhood::create($request->validated());

        return back()->with('success', "Quartier « {$neighborhood->name} » ajouté (zone {$neighborhood->zone}).");
    }

    public function update(NeighborhoodRequest $request, Neighborhood $neighborhood): RedirectResponse
    {
        $previousZone = $neighborhood->zone;
        $neighborhood->update($request->validated());

        return back()->with('success', $previousZone !== $neighborhood->zone
            ? "« {$neighborhood->name} » passe de la zone {$previousZone} à la zone {$neighborhood->zone}."
            : "Quartier « {$neighborhood->name} » mis à jour.");
    }
}
