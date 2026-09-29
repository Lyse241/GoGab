<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomePageTest extends TestCase
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

    public function test_categories_come_from_the_table_with_icon_and_slug(): void
    {
        $restaurant = Category::factory()->create(['name' => 'Restaurant', 'slug' => 'restaurant', 'icon' => 'utensils', 'sort_order' => 20]);
        $pharmacie = Category::factory()->create(['name' => 'Pharmacie', 'slug' => 'pharmacie', 'icon' => 'pill', 'sort_order' => 10]);
        Category::factory()->create(['name' => 'Fleuriste', 'slug' => 'fleuriste']); // aucun commerce : pas de pastille
        $hidden = Category::factory()->create(['name' => 'Caché', 'slug' => 'cache']);
        Store::factory()->count(2)->create(['category_id' => $restaurant->id]);
        Store::factory()->create(['category_id' => $pharmacie->id]);
        Store::factory()->create(['category_id' => $hidden->id, 'is_active' => false]); // commerce invisible

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home')
                ->has('categories', 2)
                ->where('categories.0', ['name' => 'Pharmacie', 'slug' => 'pharmacie', 'icon' => 'pill', 'stores_count' => 1])
                ->where('categories.1.slug', 'restaurant')
                ->where('categories.1.stores_count', 2)
                ->where('title', 'Tous les commerces à Libreville')
                ->where('filters', ['q' => '', 'category' => null]));
    }

    public function test_category_filter_updates_the_list_and_the_title(): void
    {
        Store::factory()->inCategory('Restaurant')->create(['name' => 'Chez Maman Ngoye']);
        Store::factory()->inCategory('Pharmacie')->create(['name' => 'Pharmacie du Port']);
        Store::factory()->inCategory('Épicerie & courses')->create(['name' => 'Épicerie Louis']);

        $this->get('/?category=restaurant')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stores', 1)
                ->where('stores.0.name', 'Chez Maman Ngoye')
                ->where('title', 'Restaurants à Libreville')
                ->where('filters.category', 'restaurant')
                ->has('categories', 3));

        $this->get('/?category=epicerie-courses')
            ->assertInertia(fn (Assert $page) => $page->where('title', 'Épiceries & courses à Libreville'));

        // Recherche dans une catégorie.
        $this->get('/?category=pharmacie&q=port')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stores', 1)
                ->where('title', 'Résultats pour « port » · Pharmacie'));

        // Catégorie inconnue : ignorée.
        $this->get('/?category=inconnue')
            ->assertInertia(fn (Assert $page) => $page->has('stores', 3)->where('filters.category', null));
    }

    public function test_only_visible_stores_are_listed_and_closed_ones_are_flagged(): void
    {
        $this->at('2026-09-28 10:00');
        $open = Store::factory()->create(['name' => 'B Ouvert']);
        $closed = Store::factory()->create(['name' => 'A Fermé', 'is_open' => false]);
        Store::factory()->create(['name' => 'Inactif', 'is_active' => false]);
        Store::factory()->create(['name' => 'Entreprise suspendue', 'owner_id' => User::factory()->suspended()->create(['role' => 'business'])->id]);
        Store::factory()->create(['name' => 'Entreprise en attente', 'owner_id' => User::factory()->pending()->create(['role' => 'business'])->id]);
        Store::factory()->create(['name' => 'Entreprise validée', 'owner_id' => User::factory()->create(['role' => 'business'])->id]);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stores', 3)
                // Ouverts d'abord (tri par nom ensuite), fermés à la fin avec leur état.
                ->where('stores.0.name', 'B Ouvert')
                ->where('stores.0.is_open_now', true)
                ->where('stores.1.name', 'Entreprise validée')
                ->where('stores.2.name', 'A Fermé')
                ->where('stores.2.is_open_now', false)
                ->where('stores.2.status_label', 'Temporairement fermé'));
    }

    public function test_store_cards_carry_logo_neighborhood_zone_and_todays_hours(): void
    {
        $this->at('2026-09-28 10:00'); // lundi
        $louis = Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre']);
        $store = Store::factory()->withoutHours()->create(['neighborhood_id' => $louis->id, 'logo' => 'stores/logos/logo.png']);
        StoreHours::sync($store, StoreHours::everyDay('08:00', '22:00', [7]));

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stores.0.logo', 'stores/logos/logo.png')
                ->where('stores.0.neighborhood_id', $louis->id)
                ->where('stores.0.neighborhood', 'Louis')
                ->where('stores.0.zone', 'Centre')
                ->where('stores.0.today_hours', '08h00 – 22h00'));

        $this->at('2026-09-27 10:00'); // dimanche : jour de repos
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('stores.0.today_hours', 'Fermé aujourd’hui'));
    }

    public function test_footer_data_is_shared_from_the_server(): void
    {
        Store::factory()->count(2)->inCategory('Restaurant')->create();
        Store::factory()->inCategory('Pharmacie')->create();

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('footer.payment_methods', ['Airtel Money', 'Moov Money', 'Paiement à la livraison'])
                ->where('footer.popular_categories.0', ['name' => 'Restaurant', 'slug' => 'restaurant'])
                ->where('footer.popular_categories.1.slug', 'pharmacie'));
    }

    public function test_logged_in_user_shares_his_default_neighborhood(): void
    {
        $akanda = Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);
        $client = User::factory()->create(['role' => 'client', 'neighborhood_id' => $akanda->id]);

        $this->actingAs($client)->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.neighborhood_id', $akanda->id));
    }
}
