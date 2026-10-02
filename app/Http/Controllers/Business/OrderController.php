<?php

namespace App\Http\Controllers\Business;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Store;
use App\Services\OrderWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Réception et traitement des commandes du commerce (/business/orders), en direct.
 * Les actions (accepter, refuser, préparer, publier l'annonce, annuler une annonce restée sans
 * livreur) passent par PUT /orders/{order}/status → OrderWorkflow ; les boutons proposés viennent
 * de OrderWorkflow::allowedTransitions(). La relance de l'annonce : POST …/relaunch.
 */
class OrderController extends Controller
{
    /**
     * Onglets => statuts.
     *
     * @var array<string, list<OrderStatus>>
     */
    public const TABS = [
        'new' => [OrderStatus::Pending],
        'preparing' => [OrderStatus::Accepted, OrderStatus::Preparing],
        // Annonce publiée, puis livreur en route vers le commerce : la commande passe dans
        // « En livraison » quand le livreur confirme la récupération.
        'searching' => [OrderStatus::SearchingCourier, OrderStatus::CourierAssigned],
        'delivering' => [OrderStatus::Delivering, OrderStatus::Arrived],
        'finished' => [OrderStatus::Delivered, OrderStatus::Refused, OrderStatus::Cancelled],
    ];

    public function __construct(private readonly OrderWorkflow $workflow) {}

    public function index(Request $request): Response
    {
        $store = $this->currentStore($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'new';
        $orders = fn () => Order::where('store_id', $store->id);

        $list = $orders()
            ->whereIn('status', self::TABS[$tab])
            ->with(['items.product:id,name', 'neighborhood:id,name', 'client:id,name', 'delivery.deliveryProfile', 'store:id,owner_id'])
            // Nouvelles et en cours : la plus ancienne d'abord (à traiter) ; terminées : la plus récente.
            ->when($tab === 'finished', fn (Builder $query) => $query->latest('updated_at')->latest('id'), fn (Builder $query) => $query->oldest()->oldest('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->present($order, $request));

        return Inertia::render('Business/Orders/Index', [
            'orders' => $list,
            'tab' => $tab,
            'counts' => collect(self::TABS)->map(fn (array $statuses) => $orders()->whereIn('status', $statuses)->count()),
            // Identifiants des nouvelles commandes : la page détecte les arrivées (toast + son).
            'pendingIds' => $orders()->where('status', OrderStatus::Pending)->pluck('id'),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->currentStore($request);
        Gate::authorize('view', $order);

        $order->load(['items.product:id,name,image', 'neighborhood:id,name', 'client:id,name', 'delivery.deliveryProfile', 'store:id,owner_id', 'statusHistories.author:id,name,role']);

        return Inertia::render('Business/Orders/Show', [
            'order' => [
                ...$this->present($order, $request),
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'address_landmarks' => $order->address_landmarks,
                'cancel_reason' => $order->cancel_reason,
                // Historique complet : chaque changement, son auteur et sa note.
                'history' => $order->statusHistories->map(fn (OrderStatusHistory $entry) => [
                    'id' => $entry->id,
                    'status' => $entry->status->value,
                    'label' => $entry->status->label(),
                    'author' => $entry->author?->name,
                    'author_role' => $entry->author?->role?->label(),
                    'note' => $entry->note,
                    'at' => $entry->created_at->setTimezone(config('gogab.timezone'))->format('d/m à H\hi'),
                    'at_iso' => $entry->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Relance l'annonce restée sans livreur (nouvelle notification aux livreurs de la zone).
     */
    public function relaunch(Request $request, Order $order): RedirectResponse
    {
        $this->currentStore($request);
        $order = $this->workflow->relaunch($order, $request->user());

        return back()->with('success', "Annonce de la commande {$order->reference} relancée auprès des livreurs de la zone.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order, Request $request): array
    {
        $allowed = $this->workflow->allowedTransitions($order, $request->user());

        return [
            'id' => $order->id,
            'number' => $order->reference,
            'status' => $order->status->value,
            'created_at' => $order->created_at->setTimezone(config('gogab.timezone'))->format('H\hi'),
            'created_date' => $order->created_at->setTimezone(config('gogab.timezone'))->format('d/m/Y'),
            'client' => $order->client?->name,
            'neighborhood' => $order->neighborhood?->name,
            'payment_method_label' => $order->payment_method->label(),
            'change_due' => $order->change_due,
            'cash_given' => $order->cash_given,
            'total_price' => $order->total_price,
            'client_note' => $order->client_note,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ]),
            // Actions proposées à l'entreprise ; l'annulation (annonce sans livreur) a son propre bloc.
            'actions' => collect($allowed)
                ->reject(fn (OrderStatus $status) => $status === OrderStatus::Cancelled)
                ->map(fn (OrderStatus $status) => $status->value)
                ->values(),
            // Annonce de livraison : temps écoulé (calculé serveur, l'interface le fait avancer),
            // relance et annulation possibles après Order::ANNOUNCEMENT_RETRY_MINUTES.
            'announcement' => $order->status === OrderStatus::SearchingCourier ? [
                'elapsed_seconds' => (int) max(0, ($order->announced_at ?? $order->updated_at)->diffInSeconds(now())),
                'retry_after_seconds' => Order::ANNOUNCEMENT_RETRY_MINUTES * 60,
                'count' => max(1, $order->announcement_count),
                'can_relaunch' => $this->workflow->canRelaunch($order, $request->user()),
                'can_cancel' => in_array(OrderStatus::Cancelled, $allowed, true),
            ] : null,
            // Livreur assigné : nom, véhicule et téléphone (bouton Appeler), dès qu'il accepte.
            'courier' => $order->delivery ? [
                'name' => $order->delivery->name,
                'phone' => $order->delivery->phone,
                'vehicle' => $order->delivery->deliveryProfile?->vehicle_type->label(),
                'vehicle_brand' => $order->delivery->deliveryProfile?->vehicle_brand,
            ] : null,
        ];
    }

    private function currentStore(Request $request): Store
    {
        $store = $request->user()->store;
        abort_if($store === null, 404, 'Aucun commerce n’est rattaché à ce compte.');

        return $store;
    }
}
