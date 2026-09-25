<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Vue d'ensemble + liste de toutes les commandes (filtrable par statut).
     */
    public function __invoke(Request $request): Response
    {
        $status = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
        ])['status'] ?? null;

        $countsByStatus = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = Order::query()
            ->with(['client:id,name', 'delivery:id,name', 'neighborhood:id,name', 'items.product.store:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'client' => $order->client->name,
                'delivery' => $order->delivery?->name,
                'store' => $order->items->first()?->product->store->name,
                'neighborhood' => $order->neighborhood->name,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'status_label' => Order::STATUSES[$order->status],
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_orders' => Order::count(),
                'revenue' => (float) Order::sum('total_price'),
                'delivered_revenue' => (float) Order::where('status', Order::STATUS_DELIVERED)->sum('total_price'),
                // Tous les statuts, même à zéro, dans l'ordre du cycle de vie.
                'by_status' => collect(Order::STATUSES)->map(fn ($label, $value) => [
                    'value' => $value,
                    'label' => $label,
                    'count' => (int) ($countsByStatus[$value] ?? 0),
                ])->values(),
            ],
            'orders' => $orders,
            'filters' => ['status' => $status],
        ]);
    }
}
