<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
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
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ])['status'] ?? null;

        $countsByStatus = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = Order::query()
            ->with(['store:id,name', 'client:id,name', 'delivery:id,name', 'neighborhood:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->reference,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'client' => $order->client->name,
                'delivery' => $order->delivery?->name,
                'store' => $order->store->name,
                'neighborhood' => $order->neighborhood->name,
                'total_price' => $order->total_price,
                'status' => $order->status->value,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pending_accounts' => User::awaitingValidation()->count(),
                'total_orders' => Order::count(),
                'revenue' => (float) Order::sum('total_price'),
                'delivered_revenue' => (float) Order::where('status', OrderStatus::Delivered)->sum('total_price'),
                // Tous les statuts, même à zéro, dans l'ordre du cycle de vie.
                'by_status' => collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'count' => (int) ($countsByStatus[$status->value] ?? 0),
                ]),
            ],
            'orders' => $orders,
            'filters' => ['status' => $status],
        ]);
    }
}
