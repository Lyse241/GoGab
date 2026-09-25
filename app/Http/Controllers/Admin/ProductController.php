<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function create(Store $store): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'store' => $store->only(['id', 'name']),
            'product' => null,
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $request->hasFile('image')
            ? ImageStorage::store($request->file('image'), 'products')
            : null;

        $product = $store->products()->create($data);

        return redirect()
            ->route('admin.stores.edit', $store)
            ->with('success', "Produit « {$product->name} » ajouté.");
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'store' => $product->store->only(['id', 'name']),
            'product' => $product->only(['id', 'name', 'description', 'price', 'image']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = ImageStorage::replace($request->file('image'), $product->image, 'products');

        // Les commandes passées gardent leur prix : order_items.price est une copie.
        $product->update($data);

        return redirect()
            ->route('admin.stores.edit', $product->store_id)
            ->with('success', "Produit « {$product->name} » mis à jour.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->exists()) {
            return back()->with('error', "Impossible de supprimer « {$product->name} » : il figure dans des commandes.");
        }

        $product->delete();
        ImageStorage::delete($product->image);

        return back()->with('success', "Produit « {$product->name} » supprimé.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'price.min' => 'Le prix doit être d\'au moins 1 FCFA.',
            'image.max' => "L'image ne doit pas dépasser 2 Mo.",
        ]);
    }
}
