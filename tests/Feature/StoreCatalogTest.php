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
        $pharmacie = Store::create(['name' => 'Pharmacie Test', 'category' => 'Pharmacie']);
        $pharmacie->products()->create(['name' => 'Paracétamol', 'price' => 1000]);
        Store::create(['name' => 'Resto Test', 'category' => 'Restaurant']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stores/Index')
                ->has('stores', 2)
                ->where('categories', ['Pharmacie', 'Restaurant'])
                ->where('stores.0.name', 'Pharmacie Test')
                ->where('stores.0.products_count', 1));
    }

    public function test_store_page_shows_its_menu(): void
    {
        $store = Store::create(['name' => 'Chez Test', 'category' => 'Restaurant']);
        $store->products()->create(['name' => 'Poulet nyembwe', 'description' => 'Plat', 'price' => 4500]);
        Store::create(['name' => 'Autre', 'category' => 'Restaurant'])
            ->products()->create(['name' => 'Ne doit pas apparaître', 'price' => 100]);

        $this->get("/stores/{$store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stores/Show')
                ->where('store.name', 'Chez Test')
                ->has('products', 1)
                ->where('products.0.name', 'Poulet nyembwe')
                ->where('products.0.price', '4500.00'));
    }

    public function test_unknown_store_returns_404(): void
    {
        $this->get('/stores/999')->assertNotFound();
    }
}
