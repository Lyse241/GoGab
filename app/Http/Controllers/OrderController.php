<?php

namespace App\Http\Controllers;

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

        $total = $items->sum(fn (array $item) => $item['price'] * $item['quantity']);

        $order = DB::transaction(function () use ($request, $items, $total) {
            $order = Order::create([
                'client_id' => $request->user()->id,
                'neighborhood_id' => $request->validated('neighborhood_id'),
                'address_landmarks' => trim($request->validated('address_landmarks')),
                'payment_method' => $request->validated('payment_method'),
                'total_price' => $total,
                'status' => Order::STATUS_PENDING,
            ]);

            $order->items()->createMany($items->all());

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

        if ((Order::NEXT_STATUS[$current] ?? null) !== $validated['status']) {
            return back()->with('error', "La commande {$order->number} est déjà au statut « ".Order::STATUSES[$current].' ».');
        }

        $updated = Order::whereKey($order->id)
            ->where('status', $current)
            ->update(['status' => $validated['status'], 'updated_at' => now()]);

        if (! $updated) {
            return back()->with('error', "Le statut de la commande {$order->number} a changé entre-temps. Actualisez la page.");
        }

        return back()->with('success', "Commande {$order->number} : ".Order::STATUSES[$validated['status']].'.');
    }

    /**
     * Page de confirmation / détail d'une commande du client connecté.
     */
    public function show(Request $request, Order $order): Response
    {
        // Un client ne peut voir que ses propres commandes.
        abort_unless($order->client()->is($request->user()), 404);

        $order->load(['neighborhood:id,name', 'items.product:id,store_id,name,image', 'items.product.store:id,name']);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'status_label' => Order::STATUSES[$order->status],
                'total_price' => $order->total_price,
                'address_landmarks' => $order->address_landmarks,
                'payment_method_label' => Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method,
                'neighborhood' => $order->neighborhood->name,
                'store' => $order->items->first()?->product->store->name,
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
