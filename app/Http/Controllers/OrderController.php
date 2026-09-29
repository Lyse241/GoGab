<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $lines = collect($request->validated('items'));
        $products = Product::whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

        // Prix relus en base : le prix affiché dans le navigateur n'est jamais utilisé.
        $items = $lines->map(fn (array $line) => [
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
            'price' => $products[$line['product_id']]->price,
        ]);

        $subtotal = $items->sum(fn (array $item) => $item['price'] * $item['quantity']);
        $deliveryFee = 0; // Frais de livraison pas encore définis.
        $paymentMethod = PaymentMethod::from($request->validated('payment_method'));

        // Statut initial, historique et notification de l'entreprise : OrderWorkflow.
        $order = $workflow->place($request->user(), [
            'store_id' => $products->first()->store_id, // une seule boutique (vérifié par StoreOrderRequest)
            'neighborhood_id' => $request->validated('neighborhood_id'),
            'address_landmarks' => trim($request->validated('address_landmarks')),
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total_price' => $subtotal + $deliveryFee,
            'payment_method' => $paymentMethod,
            'cash_given' => $paymentMethod === PaymentMethod::Cash ? $request->validated('cash_given') : null,
            'client_note' => $request->validated('client_note'),
        ], $items);

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Votre commande a bien été enregistrée.');
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
        ], [
            'status.required' => 'Indiquez le nouveau statut.',
            'note.max' => '500 caractères maximum.',
        ]);

        $to = OrderStatus::from($validated['status']);
        $workflow->transition($order, $to, $request->user(), $validated['note'] ?? null);

        return back()->with('success', "Commande {$order->reference} : {$to->label()}.");
    }

    /**
     * Page de confirmation / détail d'une commande du client connecté.
     */
    public function show(Request $request, Order $order): Response
    {
        // OrderPolicy::view ; 404 plutôt que 403 : on ne révèle pas l'existence de la commande.
        abort_unless($request->user()->can('view', $order), 404);

        $order->load(['store:id,name', 'neighborhood:id,name', 'items.product:id,name,image']);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->reference,
                'status' => $order->status->value,
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'total_price' => $order->total_price,
                'address_landmarks' => $order->address_landmarks,
                'payment_method_label' => $order->payment_method->label(),
                'cash_given' => $order->cash_given,
                'client_note' => $order->client_note,
                'neighborhood' => $order->neighborhood->name,
                'store' => $order->store->name,
                'created_at' => $order->created_at->format('d/m/Y à H:i'),
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
