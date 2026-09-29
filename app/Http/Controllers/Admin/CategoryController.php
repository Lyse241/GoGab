<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catégories de commerces : nom, icône lucide, ordre d'affichage.
 * Une catégorie utilisée par un commerce ne peut pas être supprimée.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::withCount('stores')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon', 'sort_order']),
            'icons' => config('gogab.category_icons'),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'icon' => $data['icon'],
            // Sans ordre précisé : à la fin de la liste.
            'sort_order' => $data['sort_order'] ?? ((int) Category::max('sort_order') + 10),
        ]);

        return back()->with('success', "Catégorie « {$category->name} » ajoutée.");
    }

    /**
     * Le slug ne change pas au renommage : il sert dans les liens de filtre (/?category=slug).
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        $category->update([
            'name' => $data['name'],
            'icon' => $data['icon'],
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
        ]);

        return back()->with('success', "Catégorie « {$category->name} » mise à jour.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $stores = $category->stores()->count();

        if ($stores > 0) {
            return back()->with('error', "Impossible de supprimer « {$category->name} » : elle est utilisée par {$stores} commerce".($stores > 1 ? 's' : '').'.');
        }

        $category->delete();

        return back()->with('success', "Catégorie « {$category->name} » supprimée.");
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categorie';
        $slug = $base;
        $suffix = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
