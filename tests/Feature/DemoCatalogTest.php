<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Store;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalogue de démonstration : assez de commerces et de plats, des photos qui existent toutes, et
 * les crédits exigés par les licences Creative Commons.
 */
class DemoCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<string, mixed>> crédits indexés par fichier (products/x.jpg) */
    private function credits(): array
    {
        $list = json_decode(file_get_contents(public_path('images/demo/credits.json')), true, flags: JSON_THROW_ON_ERROR);

        return collect($list)->keyBy('file')->all();
    }

    public function test_every_photo_of_the_catalog_exists_and_is_credited(): void
    {
        $credits = $this->credits();

        foreach (DemoCatalog::stores() as $store) {
            $files = ['stores/'.$store['cover'].'.jpg'];
            foreach (DemoCatalog::expand($store['products']) as $product) {
                $files[] = 'products/'.$product[4].'.jpg';
            }

            foreach ($files as $file) {
                $this->assertFileExists(public_path('images/demo/'.$file), "{$store['name']} : photo manquante {$file}");
                // Version allégée utilisée par les cartes et les listes (imageUrl(..., 'sm') côté React).
                $this->assertFileExists(public_path('images/demo/'.preg_replace('/\.jpg$/', '-sm.jpg', $file)), "{$store['name']} : miniature manquante {$file}");
                $this->assertArrayHasKey($file, $credits, "Photo sans crédit : {$file}");
            }
        }
    }

    public function test_creative_commons_photos_carry_author_licence_and_source(): void
    {
        $attributed = collect($this->credits())->filter(fn (array $credit) => str_contains($credit['licence'], 'CC BY'));

        $this->assertGreaterThan(15, $attributed->count(), 'les plats gabonais authentiques sont sous CC BY / CC BY-SA');
        foreach ($attributed as $file => $credit) {
            $this->assertNotEmpty($credit['author'], "Auteur manquant : {$file}");
            $this->assertStringStartsWith('https://', $credit['page'], "Source manquante : {$file}");
        }
    }

    public function test_the_catalog_is_big_enough_and_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(30, Store::visible()->count());
        $this->assertGreaterThanOrEqual(300, \App\Models\Product::count());

        // Chaque commerce de démonstration a une vraie carte, des photos locales et des horaires.
        foreach (DemoCatalog::stores() as $data) {
            $store = Store::with(['products', 'openingHours'])->where('name', $data['name'])->firstOrFail();

            $this->assertGreaterThanOrEqual(5, $store->products->count(), "{$store->name} : carte trop courte");
            $this->assertCount(7, $store->openingHours, "{$store->name} : horaires incomplets");
            $this->assertSame($store->products->count(), $store->products->pluck('name')->unique()->count(), "{$store->name} : produits en double");
            $this->assertStringStartsWith('/images/demo/stores/', $store->cover_image);
            $this->assertNotEmpty($store->description);
            foreach ($store->products as $product) {
                $this->assertStringStartsWith('/images/demo/products/', $product->image, "{$store->name} / {$product->name}");
                $this->assertGreaterThan(0, $product->price);
            }
        }

        // Chaque catégorie proposée sur l'accueil a au moins un commerce visible.
        foreach (Category::whereIn('slug', collect(DemoCatalog::stores())->pluck('category')->unique())->get() as $category) {
            $this->assertGreaterThan(0, Store::visible()->where('category_id', $category->id)->count(), $category->name);
        }
    }

    public function test_gabonese_cuisine_is_on_the_menu(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['Poulet nyembwe', 'Sauce gombo aux crabes', 'Atanga fumé et manioc', 'Dindon au nyembwe'] as $dish) {
            $this->assertTrue(\App\Models\Product::where('name', $dish)->exists(), "Plat gabonais absent : {$dish}");
        }
    }

    public function test_photo_credits_page_lists_the_authors(): void
    {
        $this->get('/credits-photos')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Public/PhotoCredits')
            ->has('attributed')
            ->where('attributed.0.licence', fn ($licence) => str_contains($licence, 'CC BY'))
            ->has('others'));
    }
}
