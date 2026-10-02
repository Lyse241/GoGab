<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\CourierProfileService;
use App\Services\OrderWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Courses du livreur : offres de sa zone, prise de course, courses en cours.
 * L'accueil et le profil sont dans App\Http\Controllers\Delivery.
 */
class DeliveryController extends Controller
{
    private const RELATIONS = ['store:id,name,neighborhood_id', 'store.neighborhood:id,name,zone', 'neighborhood:id,name', 'client:id,name,phone', 'items.product:id,name'];

    public function __construct(private readonly OrderWorkflow $workflow) {}

    /**
     * Offres : annonces des commerces de la même zone que le quartier de base du livreur
     * (pas de GPS), s'il est disponible, de la plus ancienne à la plus récente. Une seule
     * course active à la fois : `busy` désactive les boutons (OrderWorkflow refuse aussi).
     */
    public function offers(Request $request): Response
    {
        $user = $request->user()->load('deliveryProfile.baseNeighborhood');
        $zone = $user->deliveryProfile?->baseNeighborhood?->zone;
        $isAvailable = (bool) $user->deliveryProfile?->is_available;
        $active = $user->deliveries()->whereIn('status', CourierProfileService::ACTIVE_STATUSES)->oldest()->first();

        $offers = $zone && $isAvailable
            ? Order::with(['store:id,name,neighborhood_id', 'store.neighborhood:id,name,zone', 'neighborhood:id,name', 'items:id,order_id,quantity'])
                ->where('status', OrderStatus::SearchingCourier)
                ->whereNull('delivery_id')
                ->whereHas('store.neighborhood', fn (Builder $query) => $query->where('zone', $zone))
                ->orderByRaw('coalesce(announced_at, updated_at) asc')
                ->oldest('id')
                ->get()
            : collect();

        return Inertia::render('Delivery/Offers', [
            'offers' => $offers->map(fn (Order $order) => $this->presentOffer($order)),
            'zone' => $zone,
            'isAvailable' => $isAvailable,
            // Course déjà en cours : impossible d'en accepter une autre.
            'busy' => $active ? ['id' => $active->id, 'number' => $active->reference, 'message' => OrderWorkflow::BUSY_MESSAGE] : null,
        ]);
    }

    /**
     * Carte d'offre : ce qu'il faut savoir AVANT d'accepter (pas de nom ni de téléphone du client,
     * pas de repères d'adresse) ; gain = frais de livraison ; en cash, monnaie à prévoir.
     *
     * @return array<string, mixed>
     */
    private function presentOffer(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->reference,
            'store' => $order->store->name,
            'store_neighborhood' => $order->store->neighborhood?->name,
            'neighborhood' => $order->neighborhood?->name,
            'item_count' => (int) $order->items->sum('quantity'),
            'earning' => $order->delivery_fee,
            'total_price' => $order->total_price,
            'payment_method' => $order->payment_method->value,
            'payment_method_label' => $order->payment_method->label(),
            'is_cash' => $order->payment_method === PaymentMethod::Cash,
            'cash_given' => $order->cash_given,
            'change_due' => $order->change_due,
            // Ancienneté de l'annonce, calculée serveur (jamais l'horloge du téléphone).
            'announced_seconds' => (int) max(0, ($order->announced_at ?? $order->updated_at)->diffInSeconds(now())),
        ];
    }

    /**
     * Courses en cours du livreur, avec l'étape suivante proposée par le workflow.
     */
    public function current(Request $request, CourierProfileService $courier): Response
    {
        $user = $request->user();

        return Inertia::render('Delivery/Current', [
            'orders' => $courier->activeOrders($user)
                ->load(self::RELATIONS)
                ->map(fn (Order $order) => $this->present($order, $user, withClient: true)),
        ]);
    }

    /**
     * Le livreur prend une course annoncée dans sa zone (un seul livreur : OrderWorkflow).
     */
    public function accept(Request $request, Order $order): RedirectResponse
    {
        $this->workflow->transition($order, OrderStatus::CourierAssigned, $request->user());

        return redirect()->route('delivery.current')->with('success', "Course {$order->reference} acceptée. Direction le commerce !");
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
            'created_at' => $order->created_at->setTimezone(config('gogab.timezone'))->format('d/m à H\hi'),
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
