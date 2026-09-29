<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StoreHours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    /**
     * Page d'accueil : liste des boutiques (filtrage par catégorie côté React).
     * `?q=` (recherche du header) : boutiques dont le nom ou un produit correspond.
     */
    public function index(Request $request): Response
    {
        $q = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $stores = Store::query()
            ->visible()
            ->with(['category:id,name,sort_order', 'openingHours', 'owner:id,account_status'])
            ->withCount('products')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhereHas('products', fn (Builder $query) => $query->where('name', 'like', $like))))
            ->orderBy('name')
            ->get(['id', 'owner_id', 'name', 'category_id', 'cover_image', 'is_open', 'is_active']);

        return Inertia::render('Stores/Index', [
            // Commerces ouverts d'abord, puis par nom.
            'stores' => $stores
                ->map(fn (Store $store) => $this->present($store) + [
                    'products_count' => $store->products_count,
                ])
                ->sortBy([['is_open_now', 'desc'], ['name', 'asc']])
                ->values(),
            // Catégories des boutiques affichées, dans l'ordre défini par l'admin.
            'categories' => $stores->pluck('category')
                ->unique('id')
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->pluck('name')
                ->values(),
            'filters' => ['q' => $q],
        ]);
    }

    /**
     * Page boutique : menu complet, état d'ouverture et horaires de la semaine.
     */
    public function show(Store $store): Response
    {
        abort_unless($store->isVisible(), 404);

        // Menu par section (ordre alphabétique, produits sans section en dernier). Les produits
        // indisponibles restent affichés (grisés, non commandables) : le client sait qu'ils existent.
        $store->load(['category:id,name', 'openingHours', 'products' => fn ($query) => $query
            ->select(['id', 'store_id', 'menu_section', 'name', 'description', 'price', 'image', 'is_available'])
            ->orderByRaw('menu_section is null')
            ->orderBy('menu_section')
            ->orderBy('name'),
        ]);

        return Inertia::render('Stores/Show', [
            'store' => $this->present($store) + [
                'opening_hours' => StoreHours::schedule($store),
                // Jour courant à Libreville (1 = lundi), pour surligner la ligne du jour.
                'today' => StoreHours::now()->isoWeekday(),
            ],
            'products' => $store->products,
        ]);
    }

    /**
     * État d'ouverture et produits indisponibles en JSON (vérification légère du panier et du checkout).
     */
    public function status(Store $store): JsonResponse
    {
        abort_unless($store->isVisible(), 404);

        return response()->json([
            'store_id' => $store->id,
            ...$store->load('openingHours')->openingStatus(),
            // Pour signaler dans le panier un article devenu indisponible depuis son ajout.
            'unavailable_product_ids' => $store->products()->where('is_available', false)->pluck('id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'category' => $store->category->name,
            'cover_image' => $store->cover_image,
            // Calculé côté serveur, à l'heure de Libreville.
            ...$store->openingStatus(),
        ];
    }
}
