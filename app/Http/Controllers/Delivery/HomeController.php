<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CourierProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accueil du livreur (/delivery) : disponibilité, zone d'activité, course en cours,
 * compteurs du jour. La disponibilité et la zone viennent de la prop partagée `courier`.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, CourierProfileService $courier): Response
    {
        $user = $request->user();
        $current = $courier->activeOrders($user)
            ->load(['store:id,name,neighborhood_id', 'store.neighborhood:id,name', 'neighborhood:id,name'])
            ->first();

        return Inertia::render('Delivery/Home', [
            'current' => $current ? $this->presentCurrent($current) : null,
            'stats' => $courier->todayStats($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCurrent(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->reference,
            'status' => $order->status->value,
            'store' => $order->store->name,
            'store_neighborhood' => $order->store->neighborhood?->name,
            'neighborhood' => $order->neighborhood?->name,
            'total_price' => $order->total_price,
            'payment_method_label' => $order->payment_method->label(),
        ];
    }
}
