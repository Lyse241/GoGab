<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStoreRequest;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Store;
use App\Services\StoreHours;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Stores/Index', [
            'stores' => Store::with(['category:id,name', 'openingHours'])
                ->withCount('products')
                ->orderBy('name')
                ->get(['id', 'name', 'category_id', 'cover_image', 'is_open', 'is_active'])
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'category' => $store->category->name,
                    'cover_image' => $store->cover_image,
                    'products_count' => $store->products_count,
                    ...$store->openingStatus(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Stores/Form', [
            'store' => null,
            'products' => [],
            'categories' => $this->categories(),
            // Proposition par défaut, modifiable dans le formulaire.
            'openingHours' => StoreHours::everyDay('08:00', '22:00'),
        ]);
    }

    public function store(SaveStoreRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['opening_hours', 'cover_image']);
        $data['cover_image'] = $request->hasFile('cover_image')
            ? ImageStorage::store($request->file('cover_image'), 'stores')
            : null;
        // Boutique créée par l'admin (sans compte entreprise) : visible immédiatement.
        $data['is_active'] = true;

        $store = DB::transaction(function () use ($request, $data) {
            $store = Store::create($data);

            if ($request->has('opening_hours')) {
                StoreHours::sync($store, $request->validated('opening_hours'));
            }

            return $store;
        });

        return redirect()
            ->route('admin.stores.edit', $store)
            ->with('success', "Boutique « {$store->name} » créée. Ajoutez maintenant ses produits.");
    }

    /**
     * Édition de la boutique + liste de ses produits.
     */
    public function edit(Store $store): Response
    {
        $store->load('openingHours');

        return Inertia::render('Admin/Stores/Form', [
            'store' => $store->only(['id', 'name', 'category_id', 'cover_image', 'is_open']) + $store->openingStatus(),
            'openingHours' => StoreHours::schedule($store),
            'products' => $store->products()
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'price', 'image']),
            'categories' => $this->categories(),
        ]);
    }

    public function update(SaveStoreRequest $request, Store $store): RedirectResponse
    {
        $data = $request->safe()->except(['opening_hours', 'cover_image']);
        $data['cover_image'] = ImageStorage::replace($request->file('cover_image'), $store->cover_image, 'stores');

        DB::transaction(function () use ($request, $store, $data) {
            $store->update($data);

            if ($request->has('opening_hours')) {
                StoreHours::sync($store, $request->validated('opening_hours'));
            }
        });

        return back()->with('success', "Boutique « {$store->name} » mise à jour.");
    }

    public function destroy(Store $store): RedirectResponse
    {
        // Les commandes gardent une référence vers les produits : on refuse plutôt
        // que de casser l'historique des commandes.
        $hasOrders = $store->orders()->exists()
            || OrderItem::whereIn('product_id', $store->products()->select('id'))->exists();

        if ($hasOrders) {
            return back()->with('error', "Impossible de supprimer « {$store->name} » : certains de ses produits figurent dans des commandes.");
        }

        $images = $store->products()->pluck('image')->push($store->cover_image, $store->logo);

        DB::transaction(fn () => $store->delete()); // produits supprimés en cascade

        $images->each(fn (?string $path) => ImageStorage::delete($path));

        return redirect()
            ->route('admin.stores.index')
            ->with('success', "Boutique « {$store->name} » supprimée.");
    }

    /**
     * Catégories proposées dans le formulaire.
     */
    private function categories(): array
    {
        return Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->all();
    }
}
