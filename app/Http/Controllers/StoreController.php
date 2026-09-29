<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
     * Page d'accueil (marketplace) : catégories en pastilles et commerces visibles (Store::visible()).
     *
     * - `?category=slug` : commerces de la catégorie (titre « Restaurants à Libreville »)
     * - `?q=` : commerces dont le nom ou un produit correspond
     *
     * Le tri « quartier choisi d'abord » se fait dans le navigateur : le quartier est mémorisé
     * côté client (localStorage) et chaque commerce envoie son quartier et sa zone.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
        ]);
        $q = trim((string) ($validated['q'] ?? ''));
        $like = '%'.addcslashes($q, '%_\\').'%';

        // Catégories ayant au moins un commerce visible, dans l'ordre défini par l'admin.
        $categories = Category::query()
            ->whereHas('stores', fn (Builder $query) => $query->visible())
            ->withCount(['stores' => fn (Builder $query) => $query->visible()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']);

        // Catégorie inconnue ou vide : on l'ignore plutôt que d'afficher une erreur.
        $category = filled($validated['category'] ?? null)
            ? Category::where('slug', $validated['category'])->first(['id', 'name', 'slug'])
            : null;

        $stores = Store::query()
            ->visible()
            ->with(['category:id,name', 'neighborhood:id,name,zone', 'openingHours', 'owner:id,account_status'])
            ->withCount('products')
            ->when($category, fn (Builder $query) => $query->where('category_id', $category->id))
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhereHas('products', fn (Builder $query) => $query->where('name', 'like', $like))))
            ->orderBy('name')
            ->get(['id', 'owner_id', 'name', 'category_id', 'neighborhood_id', 'logo', 'cover_image', 'is_open', 'is_active']);

        return Inertia::render('Public/Home', [
            // Commerces ouverts d'abord, puis par nom (le navigateur remonte ensuite ceux du quartier choisi).
            'stores' => $stores
                ->map(fn (Store $store) => $this->present($store) + [
                    'logo' => $store->logo,
                    'neighborhood_id' => $store->neighborhood_id,
                    'neighborhood' => $store->neighborhood?->name,
                    'zone' => $store->neighborhood?->zone,
                    'today_hours' => StoreHours::todayLabel($store),
                    'products_count' => $store->products_count,
                ])
                ->sortBy([['is_open_now', 'desc'], ['name', 'asc']])
                ->values(),
            'categories' => $categories->map(fn (Category $item) => [
                'name' => $item->name,
                'slug' => $item->slug,
                'icon' => $item->icon,
                'stores_count' => $item->stores_count,
            ]),
            'title' => $this->title($category, $q),
            'filters' => ['q' => $q, 'category' => $category?->slug],
        ]);
    }

    /**
     * Titre de la liste : « Restaurants à Libreville », « Résultats pour « poulet » »…
     */
    private function title(?Category $category, string $q): string
    {
        if ($q !== '') {
            return "Résultats pour « {$q} »".($category ? ' · '.$category->name : '');
        }

        if (! $category) {
            return 'Tous les commerces à Libreville';
        }

        // Pluriel du premier mot : « Restaurant » → « Restaurants », « Épicerie & courses » → « Épiceries & courses ».
        $plural = preg_replace_callback('/^(\S+)/u', fn (array $word) => preg_match('/[sxz]$/iu', $word[1]) ? $word[1] : $word[1].'s', $category->name);

        return "{$plural} à Libreville";
    }

    /**
     * Page boutique : menu complet, état d'ouverture et horaires de la semaine.
     */
    public function show(Store $store): Response
    {
        abort_unless($store->isVisible(), 404);

        // Menu par section (ordre alphabétique, produits sans section en dernier). Les produits
        // indisponibles restent affichés (grisés, non commandables) : le client sait qu'ils existent.
        $store->load(['category:id,name', 'neighborhood:id,name,zone', 'openingHours', 'products' => fn ($query) => $query
            ->select(['id', 'store_id', 'menu_section', 'name', 'description', 'price', 'image', 'is_available'])
            ->orderByRaw('menu_section is null')
            ->orderBy('menu_section')
            ->orderBy('name'),
        ]);

        return Inertia::render('Public/Store', [
            'store' => $this->present($store) + [
                'description' => $store->description,
                'logo' => $store->logo,
                'neighborhood' => $store->neighborhood?->name,
                'zone' => $store->neighborhood?->zone,
                'address_landmarks' => $store->address_landmarks,
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
    public function status(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isVisible(), 404);

        // Produits du panier (?products=1,2,3) : ceux qui n'existent plus dans ce commerce
        // (supprimés entre-temps) sont aussi signalés comme indisponibles.
        $requested = collect(explode(',', (string) $request->query('products', '')))
            ->map(fn (string $id) => (int) $id)
            ->filter()
            ->take(200);
        $existing = $requested->isEmpty() ? collect() : $store->products()->whereIn('id', $requested)->pluck('id');

        return response()->json([
            'store_id' => $store->id,
            ...$store->load('openingHours')->openingStatus(),
            // Pour signaler dans le panier un article devenu indisponible depuis son ajout.
            'unavailable_product_ids' => $store->products()->where('is_available', false)->pluck('id')
                ->merge($requested->diff($existing))
                ->unique()
                ->values(),
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
