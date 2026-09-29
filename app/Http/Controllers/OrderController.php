<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
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
        abort_unless($request->user()->can('view', $order), 404);

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

        $order->load(['store:id,name', 'neighborhood:id,name', 'items.product:id,name,image', 'statusHistories']);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->reference,
                'status' => $order->status->value,
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
                // Étapes déjà franchies (heure de Libreville).
                'history' => $order->statusHistories->map(fn ($entry) => [
                    'status' => $entry->status->value,
                    'label' => $entry->status->label(),
                    'at' => $entry->created_at->setTimezone(config('gogab.timezone'))->format('d/m à H\hi'),
                ]),
                'neighborhood' => $order->neighborhood->name,
                'store' => $order->store->name,
                'created_at' => $order->created_at->setTimezone(config('gogab.timezone'))->format('d/m/Y à H\hi'),
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
