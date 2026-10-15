<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Position du livreur et arrivée estimée d'une commande (interrogé toutes les 5 s par la carte).
 * Réservé aux personnes qui peuvent voir la commande (OrderPolicy::view) ; l'entreprise ne voit
 * que l'approche du livreur vers sa boutique (TrackingService::forOrder).
 */
class TrackingController extends Controller
{
    public function show(Request $request, Order $order, TrackingService $tracking): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json($tracking->forOrder($order, $request->user()))->header('Cache-Control', 'no-store');
    }
}
