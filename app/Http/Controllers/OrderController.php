<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Crée la commande et ses lignes à partir du panier envoyé par le client.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
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

        $order = DB::transaction(function () use ($request, $products, $items, $subtotal, $deliveryFee, $paymentMethod) {
            $order = Order::create([
                'store_id' => $products->first()->store_id, // une seule boutique (vérifié par StoreOrderRequest)
                'client_id' => $request->user()->id,
                'neighborhood_id' => $request->validated('neighborhood_id'),
                'address_landmarks' => trim($request->validated('address_landmarks')),
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_price' => $subtotal + $deliveryFee,
                'payment_method' => $paymentMethod,
                'cash_given' => $paymentMethod === PaymentMethod::Cash ? $request->validated('cash_given') : null,
                'client_note' => $request->validated('client_note'),
                'status' => OrderStatus::Pending,
            ]);

            $order->items()->createMany($items->all());
            $order->recordStatus(OrderStatus::Pending, $request->user());

            return $order;
        });

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Votre commande a bien été enregistrée.');
    }

    /**
     * Le livreur assigné fait avancer le statut d'une étape :
     * acceptee → en_livraison → livree.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->delivery()->is($request->user()), 403, "Cette commande n'est pas assignée à votre compte.");

        $validated = $request->validate([
            'status' => ['required', Rule::in(Order::NEXT_STATUS)],
        ]);

        // Le statut demandé doit être exactement l'étape suivante (évite les sauts
        // d'étape et les doubles clics).
        $current = $order->status;

        if ((Order::NEXT_STATUS[$current->value] ?? null) !== $validated['status']) {
            return back()->with('error', "La commande {$order->reference} est déjà au statut « {$current->label()} ».");
        }

        $updated = Order::whereKey($order->id)
            ->where('status', $current)
            ->update(['status' => $validated['status'], 'updated_at' => now()]);

        if (! $updated) {
            return back()->with('error', "Le statut de la commande {$order->reference} a changé entre-temps. Actualisez la page.");
        }

        $next = OrderStatus::from($validated['status']);
        $order->recordStatus($next, $request->user());

        return back()->with('success', "Commande {$order->reference} : {$next->label()}.");
    }

    /**
     * Page de confirmation / détail d'une commande du client connecté.
     */
    public function show(Request $request, Order $order): Response
    {
        // Un client ne peut voir que ses propres commandes.
        abort_unless($order->client()->is($request->user()), 404);

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
