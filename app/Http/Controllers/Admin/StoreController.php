<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Store;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Stores/Index', [
            'stores' => Store::withCount('products')
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'name', 'category', 'image']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Stores/Form', [
            'store' => null,
            'products' => [],
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $request->hasFile('image')
            ? ImageStorage::store($request->file('image'), 'stores')
            : null;

        $store = Store::create($data);

        return redirect()
            ->route('admin.stores.edit', $store)
            ->with('success', "Boutique « {$store->name} » créée. Ajoutez maintenant ses produits.");
    }

    /**
     * Édition de la boutique + liste de ses produits.
     */
    public function edit(Store $store): Response
    {
        return Inertia::render('Admin/Stores/Form', [
            'store' => $store->only(['id', 'name', 'category', 'image']),
            'products' => $store->products()
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'price', 'image']),
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = ImageStorage::replace($request->file('image'), $store->image, 'stores');

        $store->update($data);

        return back()->with('success', "Boutique « {$store->name} » mise à jour.");
    }

    public function destroy(Store $store): RedirectResponse
    {
        // Les commandes gardent une référence vers les produits : on refuse plutôt
        // que de casser l'historique des commandes.
        $hasOrders = OrderItem::whereIn('product_id', $store->products()->select('id'))->exists();

        if ($hasOrders) {
            return back()->with('error', "Impossible de supprimer « {$store->name} » : certains de ses produits figurent dans des commandes.");
        }

        $images = $store->products()->pluck('image')->push($store->image);

        DB::transaction(fn () => $store->delete()); // produits supprimés en cascade

        $images->each(fn (?string $path) => ImageStorage::delete($path));

        return redirect()
            ->route('admin.stores.index')
            ->with('success', "Boutique « {$store->name} » supprimée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'image.max' => "L'image ne doit pas dépasser 2 Mo.",
        ]);
    }

    /**
     * Catégories existantes, proposées en suggestion dans le formulaire.
     */
    private function categories(): array
    {
        return Store::distinct()->orderBy('category')->pluck('category')->all();
    }
}
