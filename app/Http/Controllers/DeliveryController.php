<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use App\Services\CourierProfileService;
use App\Services\OrderWorkflow;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Courses du livreur : offres de sa zone, prise de course, course en cours, historique.
 * L'accueil et le profil sont dans App\Http\Controllers\Delivery.
 */
class DeliveryController extends Controller
{
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
     * Course en cours (une seule à la fois) : étapes guidées, commerce, client, articles,
     * encaissement. Sans course active : état vide qui renvoie vers les offres.
     */
    public function current(Request $request, CourierProfileService $courier, ReportService $reports): Response
    {
        $user = $request->user();
        $order = $courier->activeOrders($user)->first()?->load([
            'store:id,name,phone,neighborhood_id,address_landmarks,owner_id',
            'store.neighborhood:id,name',
            'neighborhood:id,name',
            'client:id,name,phone',
            'items.product:id,name',
        ]);

        return Inertia::render('Delivery/Current', [
            'order' => $order ? $this->presentCurrent($order, $user) : null,
            // « Signaler un problème » : le client ou le commerce de la course.
            'reporting' => $order ? $reports->formFor($order, $user) : null,
        ]);
    }

    /**
     * Historique : courses livrées (les plus récentes d'abord) et gains du jour, de la semaine
     * et du mois (journée de Libreville).
     */
    public function history(Request $request, CourierProfileService $courier, ReportService $reports): Response
    {
        $user = $request->user();
        $timezone = config('gogab.timezone');

        $deliveries = Order::query()
            ->where('delivery_id', $user->id)
            ->where('status', OrderStatus::Delivered)
            ->with(['store:id,name,owner_id', 'store.owner:id,name,role', 'client:id,name,role', 'neighborhood:id,name'])
            ->latest('updated_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->reference,
                'date' => $order->updated_at->setTimezone($timezone)->format('d/m/Y'),
                'time' => $order->updated_at->setTimezone($timezone)->format('H\hi'),
                'store' => $order->store?->name,
                'neighborhood' => $order->neighborhood?->name,
                'earning' => $order->delivery_fee,
                'payment_method_label' => $order->payment_method->label(),
                'reporting' => $reports->formFor($order, $user),
            ]);

        return Inertia::render('Delivery/History', [
            'deliveries' => $deliveries,
            'earnings' => $courier->earnings($user),
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
     * Écran de course : étape, action suivante (OrderWorkflow), commerce, client, articles,
     * encaissement (cash : montant remis par le client et monnaie à rendre).
     *
     * @return array<string, mixed>
     */
    private function presentCurrent(Order $order, User $courier): array
    {
        // Étape suivante proposée au livreur (hors annulation, réservée à l'admin).
        $next = collect($this->workflow->allowedTransitions($order, $courier))
            ->first(fn (OrderStatus $status) => $status !== OrderStatus::Cancelled);

        return [
            'id' => $order->id,
            'number' => $order->reference,
            'status' => $order->status->value,
            'next_status' => $next?->value,
            'store' => [
                'name' => $order->store->name,
                'neighborhood' => $order->store->neighborhood?->name,
                'address_landmarks' => $order->store->address_landmarks,
                'phone' => $order->store->phone,
            ],
            'client' => [
                // Prénom seulement : le livreur n'a pas besoin du nom complet.
                'first_name' => Str::before(trim((string) $order->client?->name), ' ') ?: $order->client?->name,
                'phone' => $order->client?->phone,
                'neighborhood' => $order->neighborhood?->name,
                'address_landmarks' => $order->address_landmarks,
            ],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
            ]),
            'item_count' => (int) $order->items->sum('quantity'),
            'client_note' => $order->client_note,
            'payment_method_label' => $order->payment_method->label(),
            'is_cash' => $order->payment_method === PaymentMethod::Cash,
            'total_price' => $order->total_price,
            'cash_given' => $order->cash_given,
            'change_due' => $order->change_due,
            'earning' => $order->delivery_fee,
        ];
    }
}
