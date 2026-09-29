<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Horaires d'ouverture. Repère : le lundi 28 septembre 2026, heure de Libreville (UTC+1).
 */
class StoreOpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * Fige l'horloge à une heure de Libreville.
     */
    private function at(string $datetime): void
    {
        $now = CarbonImmutable::parse($datetime, 'Africa/Libreville');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    /**
     * @param  list<array<string, mixed>>  $days
     */
    private function store(array $days, array $attributes = []): Store
    {
        return Store::factory()->withHours($days)->create($attributes)->fresh();
    }

    /**
     * Vérifie isOpenNow(), statusMessage() et le scope openNow() d'un coup.
     */
    private function assertStatus(Store $store, bool $open, string $label): void
    {
        $this->assertSame($open, $store->isOpenNow(), "isOpenNow() : {$label}");
        $this->assertSame($label, $store->statusMessage()['label']);
        $this->assertSame($open, Store::openNow()->whereKey($store->id)->exists(), "scope openNow() : {$label}");
    }

    // --- Ouvert / fermé ---

    public function test_open_during_opening_hours(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'));

        $this->at('2026-09-28 10:00');
        $this->assertStatus($store, true, 'Ouvert · ferme à 22h00');
        $this->assertSame(['open' => true, 'label' => 'Ouvert · ferme à 22h00', 'detail' => 'ferme à 22h00'], $store->statusMessage());

        $this->at('2026-09-28 08:00'); // pile à l'ouverture
        $this->assertStatus($store, true, 'Ouvert · ferme à 22h00');
    }

    public function test_closed_before_opening(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'));

        $this->at('2026-09-28 07:30');
        $this->assertStatus($store, false, 'Fermé · ouvre à 08h00');
        $this->assertEquals(CarbonImmutable::parse('2026-09-28 08:00', 'Africa/Libreville'), $store->nextOpeningAt());
    }

    public function test_closed_after_closing_opens_tomorrow(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'));

        $this->at('2026-09-28 22:00'); // pile à la fermeture
        $this->assertStatus($store, false, 'Fermé · ouvre demain à 08h00');
        $this->assertEquals(CarbonImmutable::parse('2026-09-29 08:00', 'Africa/Libreville'), $store->nextOpeningAt());
    }

    public function test_rest_day_is_skipped(): void
    {
        // Fermé le dimanche (7).
        $store = $this->store(StoreHours::everyDay('08:00', '22:00', [7]));

        $this->at('2026-10-04 11:00'); // dimanche
        $this->assertStatus($store, false, 'Fermé · ouvre demain à 08h00');

        $this->at('2026-10-03 23:00'); // samedi soir : dimanche sauté
        $this->assertStatus($store, false, 'Fermé · ouvre lundi à 08h00');
        $this->assertEquals(CarbonImmutable::parse('2026-10-05 08:00', 'Africa/Libreville'), $store->nextOpeningAt());
    }

    public function test_next_opening_a_week_later(): void
    {
        // Ouvert seulement le lundi.
        $store = $this->store(StoreHours::everyDay('08:00', '12:00', [2, 3, 4, 5, 6, 7]));

        $this->at('2026-09-28 13:00'); // lundi après la fermeture
        $this->assertStatus($store, false, 'Fermé · ouvre lundi prochain à 08h00');
    }

    // --- Horaires passant minuit ---

    public function test_overnight_hours_use_the_previous_day_slot(): void
    {
        // Ouvert uniquement le lundi, de 18:00 à 02:00.
        $store = $this->store(StoreHours::everyDay('18:00', '02:00', [2, 3, 4, 5, 6, 7]));

        $this->at('2026-09-28 17:59'); // lundi
        $this->assertStatus($store, false, 'Fermé · ouvre à 18h00');

        $this->at('2026-09-28 23:30'); // lundi soir
        $this->assertStatus($store, true, 'Ouvert · ferme à 02h00');

        $this->at('2026-09-29 01:00'); // mardi 01:00 : créneau du lundi
        $this->assertStatus($store, true, 'Ouvert · ferme à 02h00');

        $this->at('2026-09-29 02:00'); // mardi 02:00 : fermé jusqu'au lundi suivant
        $this->assertStatus($store, false, 'Fermé · ouvre lundi à 18h00');
    }

    public function test_overnight_slot_of_a_rest_day_does_not_spill_over(): void
    {
        // 18:00 → 02:00 tous les jours sauf le lundi.
        $store = $this->store(StoreHours::everyDay('18:00', '02:00', [1]));

        $this->at('2026-09-29 01:00'); // mardi 01:00 : le lundi était fermé
        $this->assertStatus($store, false, 'Fermé · ouvre à 18h00');

        $this->at('2026-09-28 01:00'); // lundi 01:00 : créneau du dimanche
        $this->assertStatus($store, true, 'Ouvert · ferme à 02h00');
    }

    public function test_same_opening_and_closing_time_means_all_day(): void
    {
        $store = $this->store(StoreHours::everyDay('00:00', '00:00'));

        $this->at('2026-09-28 03:15');
        $this->assertStatus($store, true, 'Ouvert 24 h/24');
    }

    // --- Interrupteur manuel, horaires absents, commerce inactif ---

    public function test_manual_switch_closes_the_store_during_opening_hours(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'), ['is_open' => false]);

        $this->at('2026-09-28 10:00');
        $this->assertStatus($store, false, 'Temporairement fermé');
        $this->assertSame('fermeture temporaire', $store->statusMessage()['detail']);
    }

    public function test_store_without_hours(): void
    {
        $store = Store::factory()->withoutHours()->create()->fresh();

        $this->at('2026-09-28 10:00');
        $this->assertStatus($store, false, 'Horaires non renseignés');
        $this->assertNull($store->nextOpeningAt());

        // Tous les jours fermés = aucun horaire.
        $allClosed = $this->store(StoreHours::everyDay('08:00', '22:00', [1, 2, 3, 4, 5, 6, 7]));
        $this->assertStatus($allClosed, false, 'Horaires non renseignés');
    }

    public function test_inactive_store_is_closed(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'), ['is_active' => false]);

        $this->at('2026-09-28 10:00');
        $this->assertStatus($store, false, 'Fermé · commerce indisponible');
    }

    // --- Fuseau horaire ---

    public function test_hours_are_evaluated_in_libreville_time_not_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'));

        // 07:30 UTC = 08:30 à Libreville.
        $now = CarbonImmutable::parse('2026-09-28 07:30', 'UTC');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        $this->assertStatus($store, true, 'Ouvert · ferme à 22h00');
    }

    // --- Pages et API ---

    public function test_pages_receive_the_status_computed_by_the_server(): void
    {
        $open = $this->store(StoreHours::everyDay('08:00', '22:00'), ['name' => 'A ouvert']);
        $this->store(StoreHours::everyDay('08:00', '22:00'), ['name' => 'B fermé', 'is_open' => false]);

        $this->at('2026-09-28 10:00');

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stores.0.is_open_now', true)
                ->where('stores.0.status_label', 'Ouvert · ferme à 22h00')
                ->where('stores.1.is_open_now', false)
                ->where('stores.1.status_label', 'Temporairement fermé'));

        $this->get("/stores/{$open->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.is_open_now', true)
                ->where('store.status_detail', 'ferme à 22h00')
                ->has('store.opening_hours', 7));

        $this->at('2026-09-28 07:00');

        $this->getJson("/stores/{$open->id}/status")
            ->assertOk()
            ->assertExactJson([
                'store_id' => $open->id,
                'is_open_now' => false,
                'status_label' => 'Fermé · ouvre à 08h00',
                'status_detail' => 'ouvre à 08h00',
                'unavailable_product_ids' => [],
            ]);
    }

    // --- Gestion par l'admin (horaires + fermeture temporaire) ---

    public function test_admin_saves_opening_hours_and_temporary_closure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->withoutHours()->create(['name' => 'Chez Test']);

        $hours = StoreHours::everyDay('18:00', '02:00', [1]);

        $this->actingAs($admin)
            ->put("/admin/stores/{$store->id}", [
                'name' => 'Chez Test',
                'category_id' => $store->category_id,
                'is_open' => false,
                'opening_hours' => $hours,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $store->refresh()->load('openingHours');
        $this->assertFalse($store->is_open);
        $this->assertCount(7, $store->openingHours);
        $this->assertTrue($store->openingHours->firstWhere('day_of_week', 1)->is_closed);
        $this->assertSame('18:00:00', $store->openingHours->firstWhere('day_of_week', 2)->opens_at);
        $this->assertSame('02:00:00', $store->openingHours->firstWhere('day_of_week', 2)->closes_at);
        $this->assertSame($hours, StoreHours::schedule($store));

        $this->actingAs($admin)
            ->get("/admin/stores/{$store->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.is_open', false)
                ->where('store.status_label', 'Temporairement fermé')
                ->where('openingHours.1.opens_at', '18:00'));
    }

    public function test_opening_hours_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create(['name' => 'Chez Test']);

        $hours = StoreHours::everyDay('08:00', '22:00');
        $hours[0]['opens_at'] = '';        // lundi ouvert sans heure d'ouverture
        $hours[1]['closes_at'] = '25:00';  // mardi : heure invalide
        $hours[6] = ['day_of_week' => 7, 'is_closed' => true, 'opens_at' => '', 'closes_at' => '']; // dimanche fermé : OK

        $this->actingAs($admin)
            ->put("/admin/stores/{$store->id}", [
                'name' => 'Chez Test',
                'category_id' => $store->category_id,
                'opening_hours' => $hours,
            ])
            ->assertSessionHasErrors(['opening_hours.1.closes_at']);

        $hours[1]['closes_at'] = '22:00';

        $this->actingAs($admin)
            ->put("/admin/stores/{$store->id}", [
                'name' => 'Chez Test',
                'category_id' => $store->category_id,
                'opening_hours' => $hours,
            ])
            ->assertSessionHasErrors(['opening_hours.0.opens_at' => 'Lundi : indiquez l’heure d’ouverture, ou cochez « Fermé ce jour ».']);

        $this->actingAs($admin)
            ->put("/admin/stores/{$store->id}", [
                'name' => 'Chez Test',
                'category_id' => $store->category_id,
                'opening_hours' => array_slice($hours, 0, 6),
            ])
            ->assertSessionHasErrors('opening_hours');
    }

    public function test_seeded_demo_stores_have_varied_hours(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local'); // le seeder génère des fichiers de démo
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(0, Store::doesntHave('openingHours')->count());
        $this->assertTrue(Store::where('is_open', false)->exists(), 'au moins un commerce fermé pour les tests');

        $this->at('2026-09-28 10:00'); // lundi matin
        $this->assertSame('Temporairement fermé', Store::firstWhere('name', 'Supérette Akanda Express')->statusMessage()['label']);
        $this->assertSame('Fermé · ouvre demain à 18h00', Store::firstWhere('name', 'Le Braisé du Bord de Mer')->statusMessage()['label']);
        $this->assertTrue(Store::firstWhere('name', 'Chez Maman Ngoye')->isOpenNow());
    }

    // --- Commandes ---

    private function placeOrder(Store $store)
    {
        $product = $store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $client = User::factory()->create(['role' => 'client']);

        return $this->actingAs($client)->post('/orders', [
            'store_id' => $store->id,
            'neighborhood_id' => Neighborhood::create(['name' => 'Glass', 'zone' => 'Centre'])->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'airtel_money',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
    }

    public function test_order_is_refused_when_the_store_is_closed(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'), ['name' => 'Chez Maman Ngoye']);

        $this->at('2026-09-28 07:00');

        $this->placeOrder($store)
            ->assertSessionHasErrors(['items' => 'Chez Maman Ngoye : Fermé · ouvre à 08h00. Votre panier est conservé : vous pourrez commander à la réouverture.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_is_refused_when_temporarily_closed(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'), ['name' => 'Supérette', 'is_open' => false]);

        $this->at('2026-09-28 10:00');

        $this->placeOrder($store)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_is_accepted_when_the_store_is_open(): void
    {
        $store = $this->store(StoreHours::everyDay('08:00', '22:00'));

        $this->at('2026-09-28 10:00');

        $this->placeOrder($store)->assertSessionHasNoErrors();
        $this->assertSame($store->id, Order::sole()->store_id);
    }
}
