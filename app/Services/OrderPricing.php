<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Prix d'une commande, toujours relus en base (le navigateur n'envoie que des identifiants
 * et des quantités) : lignes à prix figés, sous-total, frais de livraison, total.
 */
class OrderPricing
{
    public static function deliveryFee(): int
    {
        return max(0, (int) config('gogab.delivery_fee', 0));
    }

    /**
     * Produits disponibles du commerce parmi ceux demandés, indexés par id.
     *
     * @param  iterable<int>  $productIds
     * @return Collection<int, Product>
     */
    public function availableProducts(Store $store, iterable $productIds): Collection
    {
        return $store->products()
            ->whereIn('id', collect($productIds)->all())
            ->where('is_available', true)
            ->get(['id', 'store_id', 'name', 'price'])
            ->keyBy('id');
    }

    /**
     * @param  iterable<array{product_id: int, quantity: int}>  $lines
     * @return array{items: list<array{product_id: int, quantity: int, price: string}>, subtotal: float, delivery_fee: int, total: float}
     */
    public function quote(Store $store, iterable $lines): array
    {
        $lines = collect($lines);
        $products = $this->availableProducts($store, $lines->pluck('product_id'));

        $items = $lines
            ->filter(fn (array $line) => $products->has($line['product_id']))
            ->map(fn (array $line) => [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'price' => $products[$line['product_id']]->price,
            ])
            ->values();

        $subtotal = round($items->sum(fn (array $item) => $item['price'] * $item['quantity']), 2);
        $fee = self::deliveryFee();

        return [
            'items' => $items->all(),
            'subtotal' => $subtotal,
            'delivery_fee' => $fee,
            'total' => $subtotal + $fee,
        ];
    }
}
