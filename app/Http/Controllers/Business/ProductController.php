<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ProductRequest;
use App\Models\Product;
use App\Models\Store;
use App\Services\ProductCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue de l'entreprise connectée (/business/products).
 * ProductPolicy : une entreprise ne voit et ne modifie que ses propres produits (403 sinon).
 */
class ProductController extends Controller
{
    /** Valeur du filtre « section » pour les produits sans section. */
    public const NO_SECTION = '__none';

    public function __construct(private readonly ProductCatalogService $catalog) {}

    public function index(Request $request): Response
    {
        $store = $this->currentStore($request);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'section' => (string) $request->query('section', ''),
        ];

        $products = $store->products()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like));
            })
            ->when($filters['section'] === self::NO_SECTION, fn (Builder $query) => $query->whereNull('menu_section'))
            ->when(! in_array($filters['section'], ['', self::NO_SECTION], true), fn (Builder $query) => $query->where('menu_section', $filters['section']))
            // Sans section en dernier.
            ->orderByRaw('menu_section is null')
            ->orderBy('menu_section')
            ->orderBy('name')
            ->get(['id', 'menu_section', 'name', 'description', 'price', 'image', 'is_available']);

        return Inertia::render('Business/Products/Index', [
            'products' => $products->map(fn (Product $product) => $this->present($product)),
            'sections' => $this->catalog->sections($store),
            'filters' => $filters,
            'noSection' => self::NO_SECTION,
            'totals' => [
                'all' => $store->products()->count(),
                'available' => $store->products()->where('is_available', true)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Product::class);

        return $this->form($this->currentStore($request), null);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->catalog->create($this->currentStore($request), $request->validated(), $request->file('image'));

        return redirect()
            ->route('business.products.index')
            ->with('success', "Produit « {$product->name} » ajouté.");
    }

    public function edit(Request $request, Product $product): Response
    {
        return $this->form($this->currentStore($request), $product);
    }

    /**
     * POST (multipart, envoi de photo) : voir routes/web.php.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->catalog->update($product, $request->validated(), $request->file('image'));

        return redirect()
            ->route('business.products.index')
            ->with('success', "Produit « {$product->name} » mis à jour.");
    }

    /**
     * Interrupteur Disponible / Indisponible de la liste.
     */
    public function availability(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(['is_available' => ['required', 'boolean']]);
        $this->catalog->setAvailability($product, (bool) $validated['is_available']);

        return back()->with('success', $product->is_available
            ? "« {$product->name} » est de nouveau disponible."
            : "« {$product->name} » est indisponible : les clients ne peuvent plus le commander.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        if (! $this->catalog->delete($product)) {
            return back()->with('error', "Impossible de supprimer « {$product->name} » : il figure dans des commandes. Rendez-le plutôt indisponible.");
        }

        return back()->with('success', "Produit « {$product->name} » supprimé.");
    }

    private function form(Store $store, ?Product $product): Response
    {
        return Inertia::render('Business/Products/Form', [
            'product' => $product ? $this->present($product) : null,
            'sections' => $this->catalog->sections($store),
            'maxImageSize' => ProductRequest::IMAGE_MAX_KB * 1024,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (int) $product->price,
            'menu_section' => $product->menu_section,
            'image' => $product->image,
            'is_available' => $product->is_available,
        ];
    }

    private function currentStore(Request $request): Store
    {
        $store = $request->user()->store;

        abort_if($store === null, 404, 'Aucun commerce n’est rattaché à ce compte.');

        return $store;
    }
}
