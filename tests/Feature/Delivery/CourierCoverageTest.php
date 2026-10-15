<?php

namespace Tests\Feature\Delivery;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use App\Services\CourierCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Rayon de couverture : un livreur voit les courses des commerces situés à moins de X km de son
 * quartier de départ ; une annonce sans livreur est élargie au Grand Libreville après 5 minutes.
 * Coordonnées réelles : Glass → Louis ≈ 4,4 km, Glass → Owendo ≈ 8 km, Glass → Angondjé ≈ 17 km.
 */
class CourierCoverageTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private Neighborhood $glass;

    private Neighborhood $louis;

    private Neighborhood $angondje;

    private Neighborhood $owendo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glass = Neighborhood::create(['name' => 'Glass', 'zone' => 'Libreville', 'latitude' => 0.3771157, 'longitude' => 9.4532994]);
        $this->louis = Neighborhood::create(['name' => 'Louis', 'zone' => 'Libreville', 'latitude' => 0.4105973, 'longitude' => 9.4328279]);
        $this->angondje = Neighborhood::create(['name' => 'Angondjé', 'zone' => 'Akanda', 'latitude' => 0.5288199, 'longitude' => 9.4143906]);
        $this->owendo = Neighborhood::create(['name' => 'Owendo', 'zone' => 'Owendo', 'latitude' => 0.3222583, 'longitude' => 9.4979557]);

        $this->owner = User::factory()->create(['role' => 'business']);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function courierAt(Neighborhood $base, int $km, bool $available = true): User
    {
        $courier = $this->makeCourier('Livreur '.$base->name.$km, $base, $available);
        $courier->deliveryProfile->update(['coverage_km' => $km]);

        return $courier;
    }

    private function storeIn(Neighborhood $neighborhood): Store
    {
        return Store::factory()->create(['neighborhood_id' => $neighborhood->id, 'owner_id' => User::factory()->create(['role' => 'business'])->id]);
    }

    private function announce(Store $store, int $minutesAgo = 0)
    {
        $this->store = $store;
        $this->makeProductForStore($store);

        return $this->makeOrder(OrderStatus::SearchingCourier, ['store_id' => $store->id, 'announced_at' => now()->subMinutes($minutesAgo), 'delivery_fee' => 1000]);
    }

    private function makeProductForStore(Store $store): void
    {
        $store->products()->firstOrCreate(['name' => 'Poulet nyembwe'], ['price' => 4500]);
    }

    public function test_the_radius_decides_which_stores_a_courier_covers(): void
    {
        $inAkanda = $this->storeIn($this->angondje);
        $inOwendo = $this->storeIn($this->owendo);
        $inLouis = $this->storeIn($this->louis);
        $coverage = app(CourierCoverage::class);

        $five = $this->courierAt($this->glass, 5);
        $this->assertTrue($coverage->covers($five, $inLouis)); // ~4,4 km
        $this->assertFalse($coverage->covers($five, $inOwendo)); // ~8 km
        $ten = $this->courierAt($this->glass, 10);
        $this->assertTrue($coverage->covers($ten, $inOwendo));
        $this->assertFalse($coverage->covers($ten, $inAkanda)); // ~17 km
        $this->assertFalse($coverage->covers($this->courierAt($this->glass, 15), $inAkanda));
        $this->assertTrue($coverage->covers($this->courierAt($this->glass, 25), $inAkanda));
    }

    public function test_a_courier_only_sees_offers_inside_his_radius_until_the_announcement_is_widened(): void
    {
        $courier = $this->courierAt($this->glass, 10);
        $akanda = $this->announce($this->storeIn($this->angondje), minutesAgo: 1);
        $louis = $this->announce($this->storeIn($this->louis), minutesAgo: 2);

        $this->actingAs($courier)->get('/delivery/offers')->assertInertia(fn (Assert $page) => $page
            ->where('coverageKm', 10)
            ->has('offers', 1)
            ->where('offers.0.id', $louis->id));

        // Après 5 minutes sans livreur, l'annonce d'Akanda est ouverte à tout le Grand Libreville.
        $akanda->update(['announced_at' => now()->subMinutes(6)]);
        $this->actingAs($courier)->get('/delivery/offers')->assertInertia(fn (Assert $page) => $page->has('offers', 2));
    }

    public function test_a_courier_outside_the_radius_cannot_take_the_course_but_can_once_it_is_widened(): void
    {
        $courier = $this->courierAt($this->glass, 10);
        $order = $this->announce($this->storeIn($this->angondje), minutesAgo: 1);

        $this->actingAs($courier)->post("/delivery/orders/{$order->id}/accept")->assertSessionHas('error');
        $this->assertNull($order->fresh()->delivery_id);

        $order->update(['announced_at' => now()->subMinutes(7)]);
        $this->actingAs($courier)->post("/delivery/orders/{$order->id}/accept")->assertRedirect('/delivery/current');
        $this->assertSame($courier->id, $order->fresh()->delivery_id);
    }

    public function test_widening_notifies_the_new_couriers_the_business_and_the_admin_when_nobody_is_left(): void
    {
        $far = $this->courierAt($this->glass, 10);
        $store = $this->storeIn($this->angondje);
        $order = $this->announce($store, minutesAgo: 6);

        $this->assertSame(1, app(CourierCoverage::class)->widenStaleAnnouncements());
        $this->assertNotNull($order->fresh()->widened_at);
        $this->assertContains('Course disponible un peu plus loin', $this->notificationTitles($far));
        $this->assertContains('Recherche de livreur élargie', $this->notificationTitles($store->owner));
        $this->assertNotContains('Aucun livreur disponible', $this->notificationTitles($this->admin));

        // Une seule fois par annonce.
        $this->assertSame(0, app(CourierCoverage::class)->widenStaleAnnouncements());

        // Personne de disponible : l'administrateur est alerté.
        $far->deliveryProfile->update(['is_available' => false]);
        $other = $this->announce($this->storeIn($this->owendo), minutesAgo: 8);
        Artisan::call('gogab:widen-announcements');
        $this->assertContains('Aucun livreur disponible', $this->notificationTitles($this->admin));
        $this->assertNotNull($other->fresh()->widened_at);
    }

    public function test_the_store_page_and_checkout_warn_when_no_courier_covers_the_store(): void
    {
        $store = $this->storeIn($this->angondje);
        $this->courierAt($this->glass, 10); // trop loin d'Akanda

        $this->get("/stores/{$store->id}")->assertInertia(fn (Assert $page) => $page->where('deliveryAvailable', false));
        $this->actingAs($this->client)->get("/checkout/{$store->id}")->assertInertia(fn (Assert $page) => $page->where('deliveryAvailable', false));

        $this->courierAt($this->angondje, 5);
        $this->get("/stores/{$store->id}")->assertInertia(fn (Assert $page) => $page->where('deliveryAvailable', true));
    }

    public function test_a_courier_can_change_his_departure_neighborhood_and_radius(): void
    {
        $courier = $this->courierAt($this->glass, 10);

        $this->actingAs($courier)
            ->patch('/delivery/profile/base-neighborhood', ['base_neighborhood_id' => $this->owendo->id, 'coverage_km' => 25])
            ->assertSessionHasNoErrors();
        $profile = $courier->deliveryProfile->fresh();
        $this->assertSame($this->owendo->id, $profile->base_neighborhood_id);
        $this->assertSame(25, $profile->coverage_km);

        $this->actingAs($courier)->patch('/delivery/profile/base-neighborhood', ['base_neighborhood_id' => $this->owendo->id, 'coverage_km' => 7])->assertSessionHasErrors('coverage_km');
    }
}
