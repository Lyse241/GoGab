<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Position du livreur pendant sa course (envoyée par le téléphone toutes les quelques secondes).
 */
class LocationController extends Controller
{
    public function store(Request $request, Order $order, TrackingService $tracking): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
        ]);

        $tracking->report(
            $request->user(),
            $order,
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['accuracy']) ? (int) round($data['accuracy']) : null,
            isset($data['heading']) ? (int) round($data['heading']) : null,
        );

        return response()->json(['shared' => true]);
    }

    /**
     * « Je suis arrivé au commerce » : prévient l'entreprise et le client (une seule fois par course).
     */
    public function arrivedAtStore(Request $request, Order $order, TrackingService $tracking): RedirectResponse
    {
        $tracking->markArrivedAtStore($request->user(), $order);

        return back()->with('success', 'Le commerce est prévenu de votre arrivée.');
    }
}
