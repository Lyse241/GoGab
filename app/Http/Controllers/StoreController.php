<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    /**
     * Page d'accueil : liste des boutiques (filtrage par catégorie côté React).
     */
    public function index(): Response
    {
        $stores = Store::query()
            ->select(['id', 'name', 'category', 'image'])
            ->withCount('products')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return Inertia::render('Stores/Index', [
            'stores' => $stores,
            'categories' => $stores->pluck('category')->unique()->values(),
        ]);
    }

    /**
     * Page boutique : menu complet.
     */
    public function show(Store $store): Response
    {
        $store->load(['products' => fn ($query) => $query
            ->select(['id', 'store_id', 'name', 'description', 'price', 'image'])
            ->orderBy('name'),
        ]);

        return Inertia::render('Stores/Show', [
            'store' => $store->only(['id', 'name', 'category', 'image']),
            'products' => $store->products,
        ]);
    }
}
