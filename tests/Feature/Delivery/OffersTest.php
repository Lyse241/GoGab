<?php

namespace Tests\Feature\Delivery;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Services\OrderWorkflow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Offres de livraison autour du livreur (/delivery/offers) et acceptation sûre.
 */
class OffersTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        Carbon::setTestNow('2026-10-02 12:00:00');
        CarbonImmutable::setTestNow('2026-10-02 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function announce(array $attributes = [], int $minutesAgo = 0): Order
    {
        return $this->makeOrder(OrderStatus::SearchingCourier, [
            'delivery_fee' => 1000,
            'announced_at' => now()->subMinutes($minutesAgo),
            ...$attributes,
        ]);
    }

    public function test_a_courier_only_sees_offers_of_his_zone_oldest_first_with_everything_needed_before_accepting(): void
    {
        $recent = $this->announce(minutesAgo: 1);
        $old = $this->announce(['subtotal' => 5500, 'total_price' => 6500, 'cash_given' => 10000], minutesAgo: 7);
        $mobile = $this->announce(['payment_method' => 'airtel_money', 'cash_given' => null], minutesAgo: 3);

        // Commerce d'une autre zone (Nord) : jamais proposé au livreur du Centre.
        $northStore = Store::factory()->create(['neighborhood_id' => Neighborhood::firstWhere('name', 'Akanda')->id]);
        $north = $this->announce(['store_id' => $northStore->id]);

        $this->actingAs($this->courier)
            ->get('/delivery/offers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Offers')
                ->where('busy', null)
                ->has('offers', 3)
                ->where('offers.0.id', $old->id)
                ->where('offers.1.id', $mobile->id)
                ->where('offers.2.id', $recent->id)
                ->where('offers.0.store', 'Chez Maman Ngoye')
                ->where('offers.0.store_neighborhood', 'Louis')
                ->where('offers.0.neighborhood', 'Glass')
                ->where('offers.0.item_count', 2)
                ->where('offers.0.earning', '1000.00')
                ->where('offers.0.is_cash', true)
                ->where('offers.0.payment_method_label', 'Paiement à la livraison')
                ->where('offers.0.cash_given', '10000.00')
                ->where('offers.0.change_due', 3500)
                ->where('offers.0.announced_seconds', 420)
                ->where('offers.1.is_cash', false)
                ->where('offers.1.change_due', null));

        $this->actingAs($this->farCourier)
            ->get('/delivery/offers')
            ->assertInertia(fn (Assert $page) => $page->where('zone', 'Nord')->has('offers', 1)->where('offers.0.id', $north->id));
    }

    public function test_accepting_assigns_the_course_notifies_client_and_business_and_redirects_to_the_current_screen(): void
    {
        $order = $this->announce();

        $this->actingAs($this->courier)
            ->post(route('delivery.orders.accept', $order))
            ->assertRedirect(route('delivery.current'))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::CourierAssigned, $order->status);
        $this->assertSame($this->courier->id, $order->delivery_id);
        $this->assertContains('Livreur trouvé', $this->notificationTitles($this->client));
        $this->assertContains('Livreur assigné', $this->notificationTitles($this->owner));
    }

    public function test_two_couriers_accepting_the_same_course_only_one_gets_it(): void
    {
        $order = $this->announce();
        $colleague = $this->makeCourier('Collègue', Neighborhood::firstWhere('name', 'Louis'));

        // Les deux livreurs ont la même offre à l'écran ; le premier valide.
        $this->actingAs($colleague)->from('/delivery/offers')->post(route('delivery.orders.accept', $order))->assertRedirect(route('delivery.current'));

        // Le second : message, retour sur les offres, carte disparue.
        $this->actingAs($this->courier)
            ->from('/delivery/offers')
            ->post(route('delivery.orders.accept', $order))
            ->assertRedirect('/delivery/offers')
            ->assertSessionHas('error', OrderWorkflow::TAKEN_MESSAGE);

        $this->assertSame($colleague->id, $order->fresh()->delivery_id);
        $this->assertSame(1, $order->statusHistories()->where('status', OrderStatus::CourierAssigned)->count());
        $this->actingAs($this->courier)->get('/delivery/offers')->assertInertia(fn (Assert $page) => $page->has('offers', 0));

        // Seuls le premier livreur et ses notifications comptent : une seule « Livreur trouvé ».
        $this->assertSame(1, collect($this->notificationTitles($this->client))->filter(fn ($title) => $title === 'Livreur trouvé')->count());
    }

    public function test_a_stale_offer_cannot_be_taken_even_if_the_in_memory_order_still_looks_free(): void
    {
        $order = $this->announce();
        // Un autre livreur a pris la course entre la lecture et l'écriture.
        Order::whereKey($order->id)->update(['delivery_id' => $this->makeCourier('Rapide', Neighborhood::firstWhere('name', 'Louis'))->id, 'status' => OrderStatus::CourierAssigned]);

        $this->expectExceptionMessage('vient d’être prise');
        app(OrderWorkflow::class)->transition($order, OrderStatus::CourierAssigned, $this->courier);
    }

    public function test_a_courier_with_an_active_course_cannot_accept_a_second_one(): void
    {
        $active = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $offer = $this->announce();

        $this->actingAs($this->courier)
            ->get('/delivery/offers')
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 1)
                ->where('busy.id', $active->id)
                ->where('busy.number', $active->reference)
                ->where('busy.message', OrderWorkflow::BUSY_MESSAGE));

        $this->actingAs($this->courier)
            ->from('/delivery/offers')
            ->post(route('delivery.orders.accept', $offer))
            ->assertRedirect('/delivery/offers')
            ->assertSessionHas('error', OrderWorkflow::BUSY_MESSAGE);
        $this->assertNull($offer->fresh()->delivery_id);

        // Course terminée : il peut à nouveau accepter.
        $active->update(['status' => OrderStatus::Delivered]);
        $this->actingAs($this->courier)->post(route('delivery.orders.accept', $offer))->assertSessionHas('success');
        $this->assertSame($this->courier->id, $offer->fresh()->delivery_id);
    }

    public function test_an_unavailable_or_unapproved_courier_cannot_accept(): void
    {
        $offer = $this->announce();
        $this->courier->deliveryProfile->update(['is_available' => false]);

        $this->actingAs($this->courier->fresh())
            ->from('/delivery/offers')
            ->post(route('delivery.orders.accept', $offer))
            ->assertSessionHas('error', 'Vous êtes indisponible : passez disponible pour accepter une course.');

        $pending = $this->makeCourier('En attente', Neighborhood::firstWhere('name', 'Louis'), state: 'pending');
        $this->actingAs($pending)->post(route('delivery.orders.accept', $offer))->assertRedirect('/account/pending');

        $this->assertNull($offer->fresh()->delivery_id);
    }
}
