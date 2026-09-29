<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_stores_and_categories(): void
    {
        $pharmacie = Store::factory()->inCategory('Pharmacie')->create(['name' => 'Pharmacie Test']);
        $pharmacie->products()->create(['name' => 'Paracétamol', 'price' => 1000]);
        Store::factory()->inCategory('Restaurant')->create(['name' => 'Resto Test']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stores/Index')
                ->has('stores', 2)
                ->where('categories', ['Pharmacie', 'Restaurant'])
                ->where('stores.0.name', 'Pharmacie Test')
                ->where('stores.0.category', 'Pharmacie')
                ->where('stores.0.products_count', 1));
    }

    public function test_store_page_shows_its_menu(): void
    {
        $store = Store::factory()->inCategory('Restaurant')->create(['name' => 'Chez Test']);
        $store->products()->create(['name' => 'Poulet nyembwe', 'description' => 'Plat', 'price' => 4500]);
        Store::factory()->inCategory('Restaurant')->create(['name' => 'Autre'])
            ->products()->create(['name' => 'Ne doit pas apparaître', 'price' => 100]);

        $this->get("/stores/{$store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stores/Show')
                ->where('store.name', 'Chez Test')
                ->where('store.category', 'Restaurant')
                ->has('products', 1)
                ->where('products.0.name', 'Poulet nyembwe')
                ->where('products.0.price', '4500.00'));
    }

    public function test_search_matches_store_names_and_product_names(): void
    {
        Store::factory()->inCategory('Pharmacie')->create(['name' => 'Pharmacie du Bon Secours']);
        Store::factory()->inCategory('Restaurant')->create(['name' => 'Chez Maman Ngoye'])
            ->products()->create(['name' => 'Poulet nyembwe', 'price' => 4500]);
        Store::factory()->inCategory('Boulangerie')->create(['name' => 'Boulangerie Owendo']);

        $this->get('/?q=nyembwe')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'nyembwe')
                ->has('stores', 1)
                ->where('stores.0.name', 'Chez Maman Ngoye')
                ->where('categories', ['Restaurant']));

        $this->get('/?q=pharmacie')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stores', 1)
                ->where('stores.0.name', 'Pharmacie du Bon Secours'));

        // Les jokers SQL saisis par l'utilisateur sont traités comme du texte.
        $this->get('/?q=%25')
            ->assertInertia(fn (Assert $page) => $page->has('stores', 0));

        $this->get('/?q=')
            ->assertInertia(fn (Assert $page) => $page->where('filters.q', '')->has('stores', 3));
    }

    public function test_unknown_store_returns_404(): void
    {
        $this->get('/stores/999')->assertNotFound();
    }
}
