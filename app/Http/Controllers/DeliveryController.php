<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    /**
     * Tableau de bord livreur : commandes disponibles + livraisons en cours.
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $relations = ['neighborhood:id,name', 'client:id,name,phone', 'items.product:id,store_id,name', 'items.product.store:id,name'];

        $available = Order::with($relations)
            ->where('status', Order::STATUS_PENDING)
            ->whereNull('delivery_id')
            ->oldest()
            ->get();

        $mine = Order::with($relations)
            ->where('delivery_id', $user->id)
            ->whereIn('status', array_keys(Order::NEXT_STATUS))
            ->oldest()
            ->get();

        return Inertia::render('Delivery/Dashboard', [
            // Le téléphone du client n'est visible qu'une fois la commande acceptée.
            'available' => $available->map(fn (Order $order) => $this->present($order, withClient: false)),
            'mine' => $mine->map(fn (Order $order) => $this->present($order, withClient: true)),
            'deliveredToday' => $user->deliveries()
                ->where('status', Order::STATUS_DELIVERED)
                ->whereDate('updated_at', today())
                ->count(),
        ]);
    }

    /**
     * Le livreur connecté prend en charge une commande disponible.
     */
    public function accept(Request $request, Order $order): RedirectResponse
    {
        // Mise à jour conditionnelle : si deux livreurs cliquent en même temps,
        // un seul UPDATE trouve encore la commande libre.
        $taken = Order::whereKey($order->id)
            ->where('status', Order::STATUS_PENDING)
            ->whereNull('delivery_id')
            ->update([
                'delivery_id' => $request->user()->id,
                'status' => Order::STATUS_ACCEPTED,
                'updated_at' => now(),
            ]);

        if (! $taken) {
            return back()->with('error', "La commande {$order->number} a déjà été prise par un autre livreur.");
        }

        return back()->with('success', "Commande {$order->number} acceptée. Direction la boutique !");
    }

    /**
     * Données d'une commande pour l'affichage livreur.
     *
     * @return array<string, mixed>
     */
    private function present(Order $order, bool $withClient): array
    {
        $next = Order::NEXT_STATUS[$order->status] ?? null;

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'status_label' => Order::STATUSES[$order->status],
            'next_status' => $next,
            'created_at' => $order->created_at->format('d/m à H:i'),
            'store' => $order->items->first()?->product->store->name,
            'neighborhood' => $order->neighborhood->name,
            'address_landmarks' => $order->address_landmarks,
            'payment_method_label' => Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method,
            'total_price' => $order->total_price,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
            ]),
            'client' => $withClient ? [
                'name' => $order->client->name,
                'phone' => $order->client->phone,
            ] : null,
        ];
    }
}
