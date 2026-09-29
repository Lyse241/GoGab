<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    /** Statuts d'une course en cours pour le livreur assigné. */
    private const IN_PROGRESS = [OrderStatus::CourierAssigned, OrderStatus::Delivering, OrderStatus::Arrived];

    public function __construct(private readonly OrderWorkflow $workflow) {}

    /**
     * Tableau de bord livreur : annonces de sa zone (recherche de livreur) + ses courses en cours.
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user()->load('deliveryProfile.baseNeighborhood');
        $zone = $user->deliveryProfile?->baseNeighborhood?->zone;
        $isAvailable = (bool) $user->deliveryProfile?->is_available;
        $relations = ['store:id,name,neighborhood_id', 'store.neighborhood:id,name,zone', 'neighborhood:id,name', 'client:id,name,phone', 'items.product:id,name'];

        // Annonces : commerces de la même zone que le livreur (pas de GPS), s'il est disponible.
        $available = $zone && $isAvailable
            ? Order::with($relations)
                ->where('status', OrderStatus::SearchingCourier)
                ->whereNull('delivery_id')
                ->whereHas('store.neighborhood', fn (Builder $query) => $query->where('zone', $zone))
                ->oldest()
                ->get()
            : collect();

        $mine = Order::with($relations)
            ->where('delivery_id', $user->id)
            ->whereIn('status', self::IN_PROGRESS)
            ->oldest()
            ->get();

        return Inertia::render('Delivery/Dashboard', [
            // Le téléphone du client n'est visible qu'une fois la course acceptée.
            'available' => $available->map(fn (Order $order) => $this->present($order, $user, withClient: false)),
            'mine' => $mine->map(fn (Order $order) => $this->present($order, $user, withClient: true)),
            'zone' => $zone,
            'isAvailable' => $isAvailable,
            'deliveredToday' => $user->deliveries()
                ->where('status', OrderStatus::Delivered)
                ->whereDate('updated_at', today())
                ->count(),
        ]);
    }

    /**
     * Le livreur prend une course annoncée dans sa zone (un seul livreur : OrderWorkflow).
     */
    public function accept(Request $request, Order $order): RedirectResponse
    {
        $this->workflow->transition($order, OrderStatus::CourierAssigned, $request->user());

        return back()->with('success', "Course {$order->reference} acceptée. Direction le commerce !");
    }

    /**
     * Données d'une commande pour l'affichage livreur.
     *
     * @return array<string, mixed>
     */
    private function present(Order $order, $courier, bool $withClient): array
    {
        // Étape suivante proposée au livreur (hors annulation, réservée à l'admin).
        $next = collect($this->workflow->allowedTransitions($order, $courier))
            ->first(fn (OrderStatus $status) => $status !== OrderStatus::Cancelled);

        return [
            'id' => $order->id,
            'number' => $order->reference,
            'status' => $order->status->value,
            'next_status' => $withClient ? $next?->value : null,
            'created_at' => $order->created_at->format('d/m à H:i'),
            'store' => $order->store->name,
            'store_neighborhood' => $order->store->neighborhood?->name,
            'neighborhood' => $order->neighborhood->name,
            'address_landmarks' => $order->address_landmarks,
            'payment_method_label' => $order->payment_method->label(),
            'cash_given' => $order->cash_given,
            'client_note' => $order->client_note,
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
