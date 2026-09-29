<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorePageAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function at(string $time): void
    {
        $now = CarbonImmutable::parse($time, 'Africa/Libreville');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    public function test_store_page_has_the_full_header(): void
    {
        $this->at('2026-09-28 10:00'); // lundi
        $store = Store::factory()->inCategory('Restaurant')->withoutHours()->create([
            'name' => 'Chez Maman Ngoye',
            'description' => 'Cuisine gabonaise maison.',
            'logo' => 'stores/logos/logo.png',
            'address_landmarks' => 'En face du marché',
            'neighborhood_id' => Neighborhood::create(['name' => 'Nombakélé', 'zone' => 'Centre'])->id,
        ]);
        StoreHours::sync($store, StoreHours::everyDay('08:00', '22:00', [7]));

        $this->get("/stores/{$store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Store')
                ->where('store.name', 'Chez Maman Ngoye')
                ->where('store.category', 'Restaurant')
                ->where('store.description', 'Cuisine gabonaise maison.')
                ->where('store.logo', 'stores/logos/logo.png')
                ->where('store.neighborhood', 'Nombakélé')
                ->where('store.zone', 'Centre')
                ->where('store.address_landmarks', 'En face du marché')
                ->where('store.is_open_now', true)
                ->where('store.today', 1)
                ->has('store.opening_hours', 7)
                ->where('store.opening_hours.6.is_closed', true));
    }

    public function test_closed_store_sends_the_server_message(): void
    {
        $this->at('2026-09-28 06:30');
        $store = Store::factory()->withoutHours()->create();
        StoreHours::sync($store, StoreHours::everyDay('08:00', '22:00'));

        $this->get("/stores/{$store->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.is_open_now', false)
                ->where('store.status_label', 'Fermé · ouvre à 08h00'));
    }

    public function test_invisible_store_page_is_not_found(): void
    {
        $suspended = User::factory()->suspended()->create(['role' => 'business']);

        $this->get('/stores/'.Store::factory()->create(['is_active' => false])->id)->assertNotFound();
        $this->get('/stores/'.Store::factory()->create(['owner_id' => $suspended->id])->id)->assertNotFound();
    }

    public function test_global_search_finds_a_store_by_name_and_by_product(): void
    {
        $maman = Store::factory()->inCategory('Restaurant')->create(['name' => 'Chez Maman Ngoye']);
        Product::factory()->for($maman)->create(['name' => 'Poulet nyembwe']);
        $braise = Store::factory()->inCategory('Restaurant')->create(['name' => 'Le Braisé du Bord de Mer']);
        Product::factory()->for($braise)->create(['name' => 'Poulet DG']);
        Product::factory()->for($braise)->unavailable()->create(['name' => 'Poulet braisé']);
        Store::factory()->inCategory('Pharmacie')->create(['name' => 'Pharmacie du Port']);

        // Par le nom du commerce.
        $this->get('/search?q=maman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Search')
                ->where('q', 'maman')
                ->has('stores', 1)
                ->where('stores.0.name', 'Chez Maman Ngoye')
                ->has('productGroups', 0));

        // Par un produit : groupés par commerce, disponibles d'abord.
        $this->get('/search?q=poulet')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stores', 0)
                ->has('productGroups', 2)
                ->where('productGroups.0.store.name', 'Chez Maman Ngoye')
                ->where('productGroups.0.products.0.name', 'Poulet nyembwe')
                ->where('productGroups.1.store.name', 'Le Braisé du Bord de Mer')
                ->has('productGroups.1.products', 2)
                ->where('productGroups.1.products.0.name', 'Poulet DG')
                ->where('productGroups.1.products.1.is_available', false));

        // Par la catégorie.
        $this->get('/search?q=pharmacie')
            ->assertInertia(fn (Assert $page) => $page->where('stores.0.name', 'Pharmacie du Port'));
    }

    public function test_global_search_ignores_invisible_stores_and_short_queries(): void
    {
        $hidden = Store::factory()->create(['name' => 'Snack caché', 'is_active' => false]);
        Product::factory()->for($hidden)->create(['name' => 'Sandwich caché']);

        $this->get('/search?q=caché')
            ->assertInertia(fn (Assert $page) => $page->has('stores', 0)->has('productGroups', 0));

        $this->get('/search?q=a')
            ->assertInertia(fn (Assert $page) => $page->where('q', 'a')->where('minLength', 2)->has('stores', 0));

        $this->get('/search')->assertOk();

        // Jokers SQL traités comme du texte.
        Store::factory()->create(['name' => 'Épicerie Louis']);
        $this->get('/search?q=%25%25')->assertInertia(fn (Assert $page) => $page->has('stores', 0));
    }
}
