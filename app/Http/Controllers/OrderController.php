<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Support\OrderTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Crée la commande et ses lignes à partir du panier envoyé par le client (prix relus en base).
     */
    public function store(StoreOrderRequest $request, OrderWorkflow $workflow): RedirectResponse
    {
        // Prix, frais et total recalculés depuis la base par la validation (OrderPricing) :
        // le montant affiché dans le navigateur n'est jamais utilisé.
        $quote = $request->quote();
        $paymentMethod = PaymentMethod::from($request->validated('payment_method'));

        // Statut initial, lignes à prix figés, historique et notification de l'entreprise, dans
        // une transaction : OrderWorkflow::place().
        $order = $workflow->place($request->user(), [
            'store_id' => $request->store()->id,
            'neighborhood_id' => $request->validated('neighborhood_id'),
            'address_landmarks' => trim($request->validated('address_landmarks')),
            'subtotal' => $quote['subtotal'],
            'delivery_fee' => $quote['delivery_fee'],
            'total_price' => $quote['total'],
            'payment_method' => $paymentMethod,
            'cash_given' => $paymentMethod === PaymentMethod::Cash ? $request->validated('cash_given') : null,
            'client_note' => $request->validated('client_note'),
        ], $quote['items']);

        return redirect()->route('orders.confirmation', $order);
    }

    /**
     * Confirmation juste après la commande : numéro et « Suivre ma commande ».
     */
    public function confirmation(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load('store:id,name');

        return Inertia::render('Orders/Confirmation', [
            'order' => [
                'id' => $order->id,
                'number' => $order->reference,
                'store_id' => $order->store_id,
                'store' => $order->store->name,
                'total_price' => $order->total_price,
                'payment_method' => $order->payment_method->value,
                'payment_method_label' => $order->payment_method->label(),
                'cash_given' => $order->cash_given,
                'change_due' => $order->change_due,
            ],
        ]);
    }

    /**
     * Changement de statut, pour tous les rôles (entreprise, livreur, client, admin).
     * Les règles (rôle, statut, acteur concerné, motif) sont dans OrderWorkflow : une transition
     * interdite lève OrderTransitionException, rendue en message d'erreur.
     */
    public function updateStatus(Request $request, Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
            // Remise d'une commande cash : case « Montant encaissé » cochée par le livreur.
            'cash_collected' => ['sometimes', 'boolean'],
        ], [
            'status.required' => 'Indiquez le nouveau statut.',
            'note.max' => '500 caractères maximum.',
        ]);

        $to = OrderStatus::from($validated['status']);
        $workflow->transition($order, $to, $request->user(), $validated['note'] ?? null, (bool) ($validated['cash_collected'] ?? false));

        return back()->with('success', "Commande {$order->reference} : {$to->label()}.");
    }

    /**
     * « Mes commandes » : onglets En cours / Terminées, liste paginée (plus récentes d'abord).
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'finished' ? 'finished' : 'ongoing';
        $finished = array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->isFinal());
        $mine = fn () => Order::where('client_id', $request->user()->id);

        $orders = $mine()
            ->when($tab === 'finished',
                fn ($query) => $query->whereIn('status', $finished),
                fn ($query) => $query->whereNotIn('status', $finished))
            ->with('store:id,name,logo')
            ->withSum('items as items_count', 'quantity')
            ->latest()
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->reference,
                'store' => $order->store?->only(['id', 'name', 'logo']),
                'status' => $order->status->value,
                'total_price' => $order->total_price,
                'items_count' => (int) $order->items_count,
                'created_at' => $order->created_at->setTimezone(config('gogab.timezone'))->format('d/m/Y à H\hi'),
            ]);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'tab' => $tab,
            'counts' => [
                'ongoing' => $mine()->whereNotIn('status', $finished)->count(),
                'finished' => $mine()->whereIn('status', $finished)->count(),
            ],
        ]);
    }

    /**
     * Suivi d'une commande : récapitulatif, timeline (order_status_histories), livreur assigné,
     * annulation tant qu'elle est en attente. Rafraîchi toutes les 10 s par la page.
     */
    public function show(Request $request, Order $order, OrderWorkflow $workflow): Response
    {
        // OrderPolicy::view : 403 si la commande n'est pas la sienne.
        Gate::authorize('view', $order);

        $order->load(['store:id,name,logo,phone', 'neighborhood:id,name', 'items.product:id,name,image', 'statusHistories', 'delivery.deliveryProfile']);
        $courier = $order->delivery;

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->reference,
                'status' => $order->status->value,
                'is_final' => $order->status->isFinal(),
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'total_price' => $order->total_price,
                'address_landmarks' => $order->address_landmarks,
                'payment_method' => $order->payment_method->value,
                'payment_method_label' => $order->payment_method->label(),
                'cash_given' => $order->cash_given,
                'change_due' => $order->change_due,
                'client_note' => $order->client_note,
                'cancel_reason' => $order->cancel_reason,
                'neighborhood' => $order->neighborhood->name,
                'store' => $order->store->only(['id', 'name', 'logo']),
                'created_at' => $order->created_at->setTimezone(config('gogab.timezone'))->format('d/m/Y à H\hi'),
                'timeline' => OrderTimeline::for($order),
                // Livreur : prénom, véhicule et téléphone une fois assigné (pas de nom complet).
                'courier' => $courier ? [
                    'first_name' => Str::before(trim($courier->name), ' ') ?: $courier->name,
                    'vehicle' => $courier->deliveryProfile?->vehicle_type->label(),
                    'vehicle_brand' => $courier->deliveryProfile?->vehicle_brand,
                    'phone' => $order->status->isFinal() ? null : $courier->phone,
                ] : null,
                'can_cancel' => in_array(OrderStatus::Cancelled, $workflow->allowedTransitions($order, $request->user()), true),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product->name,
                    'image' => $item->product->image,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]),
            ],
        ]);
    }
}
