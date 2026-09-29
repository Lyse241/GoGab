<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use App\Support\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Catalogue d'un commerce géré par son entreprise (espace entreprise).
 */
class ProductCatalogService
{
    private const IMAGE_FOLDER = 'products';

    /**
     * @param  array<string, mixed>  $data  données validées (ProductRequest)
     */
    public function create(Store $store, array $data, ?UploadedFile $image = null): Product
    {
        $path = $image ? ImageStorage::store($image, self::IMAGE_FOLDER) : null;

        try {
            return $store->products()->create([...$this->attributes($data), 'image' => $path]);
        } catch (\Throwable $e) {
            ImageStorage::delete($path);

            throw $e;
        }
    }

    /**
     * Les commandes passées gardent leur prix : order_items.price est une copie.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        $attributes = $this->attributes($data);
        $old = null;

        if ($image) {
            $attributes['image'] = ImageStorage::store($image, self::IMAGE_FOLDER);
            $old = $product->image;
        } elseif ($data['remove_image'] ?? false) {
            $attributes['image'] = null;
            $old = $product->image;
        }

        try {
            $product->update($attributes);
        } catch (\Throwable $e) {
            if ($image) {
                ImageStorage::delete($attributes['image']);
            }

            throw $e;
        }

        ImageStorage::delete($old);

        return $product;
    }

    public function setAvailability(Product $product, bool $available): Product
    {
        $product->update(['is_available' => $available]);

        return $product;
    }

    /**
     * Supprime un produit jamais commandé. Un produit présent dans des commandes est gardé
     * (historique) : l'entreprise le rend indisponible à la place.
     *
     * @return bool false si le produit figure dans des commandes
     */
    public function delete(Product $product): bool
    {
        if ($product->orderItems()->exists()) {
            return false;
        }

        $product->delete();
        ImageStorage::delete($product->image);

        return true;
    }

    /**
     * Sections existantes du commerce (suggestions et filtre), par ordre alphabétique.
     *
     * @return Collection<int, string>
     */
    public function sections(Store $store): Collection
    {
        return $store->products()
            ->whereNotNull('menu_section')
            ->distinct()
            ->orderBy('menu_section')
            ->pluck('menu_section');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)->only(['name', 'description', 'price', 'menu_section', 'is_available'])->all();
    }
}
