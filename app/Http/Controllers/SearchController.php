<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Services\StoreHours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recherche globale (/search?q=) : commerces visibles dont le nom, la catégorie ou la description
 * correspond, et produits correspondants regroupés par commerce.
 */
class SearchController extends Controller
{
    public const MIN_LENGTH = 2;

    private const MAX_STORES = 30;

    private const MAX_PRODUCTS = 60;

    public function __invoke(Request $request): Response
    {
        $q = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));

        if (mb_strlen($q) < self::MIN_LENGTH) {
            return Inertia::render('Public/Search', [
                'q' => $q,
                'stores' => [],
                'productGroups' => [],
                'minLength' => self::MIN_LENGTH,
            ]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $stores = Store::query()
            ->visible()
            ->with(['category:id,name', 'neighborhood:id,name', 'openingHours', 'owner:id,account_status'])
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', $like)))
            ->orderBy('name')
            ->limit(self::MAX_STORES)
            ->get(['id', 'owner_id', 'name', 'category_id', 'neighborhood_id', 'logo', 'cover_image', 'is_open', 'is_active']);

        $products = Product::query()
            ->whereHas('store', fn (Builder $query) => $query->visible())
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('description', 'like', $like))
            ->with(['store' => fn ($query) => $query
                ->select(['id', 'owner_id', 'name', 'category_id', 'neighborhood_id', 'logo', 'cover_image', 'is_open', 'is_active'])
                ->with(['category:id,name', 'neighborhood:id,name', 'openingHours', 'owner:id,account_status'])])
            // Disponibles d'abord.
            ->orderByDesc('is_available')
            ->orderBy('name')
            ->limit(self::MAX_PRODUCTS)
            ->get(['id', 'store_id', 'menu_section', 'name', 'description', 'price', 'image', 'is_available']);

        return Inertia::render('Public/Search', [
            'q' => $q,
            'stores' => $stores
                ->map(fn (Store $store) => $this->presentStore($store))
                ->sortBy([['is_open_now', 'desc'], ['name', 'asc']])
                ->values(),
            // Un groupe par commerce : ses produits correspondants (commerces ouverts d'abord).
            'productGroups' => $products
                ->groupBy('store_id')
                ->map(fn ($items) => [
                    'store' => $this->presentStore($items->first()->store),
                    'products' => $items->map(fn (Product $product) => $product->only(['id', 'store_id', 'menu_section', 'name', 'description', 'price', 'image', 'is_available']))->values(),
                ])
                ->sortBy([['store.is_open_now', 'desc'], ['store.name', 'asc']])
                ->values(),
            'minLength' => self::MIN_LENGTH,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentStore(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'category' => $store->category?->name,
            'neighborhood' => $store->neighborhood?->name,
            'logo' => $store->logo,
            'cover_image' => $store->cover_image,
            'today_hours' => StoreHours::todayLabel($store),
            ...$store->openingStatus(),
        ];
    }
}
