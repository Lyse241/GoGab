<?php

namespace Tests\Feature\Business;

use App\Enums\OrderStatus;
use App\Enums\VehicleType;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Annonce de livraison côté entreprise : publication, relance après 5 minutes, annulation avec
 * motif, suivi du livreur (acceptation, récupération, livraison).
 */
class DeliveryAnnouncementTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $this->freeze('2026-10-02 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function freeze(string $time): void
    {
        Carbon::setTestNow($time);
        CarbonImmutable::setTestNow($time);
    }

    private function publish(Order $order)
    {
        return $this->actingAs($this->owner)
            ->from(route('business.orders.index'))
            ->put(route('orders.status.update', $order), ['status' => 'en_recherche_livreur']);
    }

    public function test_publishing_notifies_available_couriers_of_the_zone_and_the_client_only(): void
    {
        $centre = Neighborhood::firstWhere('name', 'Glass');
        $unavailable = $this->makeCourier('Indisponible', $centre, available: false);
        $pending = $this->makeCourier('En attente', $centre, state: 'pending');
        $order = $this->makeOrder(OrderStatus::Preparing);

        $this->publish($order)->assertRedirect(route('business.orders.index'))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::SearchingCourier, $order->status);
        $this->assertTrue($order->announced_at->equalTo(now()));
        $this->assertSame(1, $order->announcement_count);

        $this->assertSame(['Nouvelle course disponible'], $this->notificationTitles($this->courier));
        $this->assertSame(['Recherche d’un livreur'], $this->notificationTitles($this->client));
        $this->assertSame([], $this->notificationTitles($this->farCourier));
        $this->assertSame([], $this->notificationTitles($unavailable));
        $this->assertSame([], $this->notificationTitles($pending));
    }

    public function test_publishing_is_refused_unless_the_order_is_being_prepared(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::SearchingCourier, OrderStatus::Delivered] as $status) {
            $order = $this->makeOrder($status);

            $this->publish($order)->assertSessionHas('error');

            $this->assertSame($status, $order->fresh()->status);
        }

        $this->assertSame([], $this->notificationTitles($this->courier));
    }

    public function test_the_card_shows_the_elapsed_time_and_offers_relaunch_only_after_five_minutes(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing);
        $this->publish($order);

        $this->freeze('2026-10-02 12:04:30');
        $this->actingAs($this->owner)
            ->get('/business/orders?tab=searching')
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.data.0.announcement.elapsed_seconds', 270)
                ->where('orders.data.0.announcement.retry_after_seconds', 300)
                ->where('orders.data.0.announcement.can_relaunch', false)
                ->where('orders.data.0.announcement.can_cancel', false)
                ->where('orders.data.0.courier', null));

        // Trop tôt : relance et annulation refusées, rien n'est envoyé.
        $this->actingAs($this->owner)->post(route('business.orders.relaunch', $order))->assertSessionHas('error');
        $this->actingAs($this->owner)
            ->put(route('orders.status.update', $order), ['status' => 'annulee', 'note' => 'Personne'])
            ->assertSessionHas('error');
        $this->assertSame(OrderStatus::SearchingCourier, $order->fresh()->status);
        $this->assertCount(1, $this->courier->notifications);

        $this->freeze('2026-10-02 12:05:00');
        $this->actingAs($this->owner)
            ->get('/business/orders?tab=searching')
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.data.0.announcement.can_relaunch', true)
                ->where('orders.data.0.announcement.can_cancel', true));
    }

    public function test_relaunch_notifies_the_zone_couriers_again_and_restarts_the_delay(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing);
        $this->publish($order);

        $this->freeze('2026-10-02 12:06:00');
        $this->actingAs($this->owner)
            ->post(route('business.orders.relaunch', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::SearchingCourier, $order->status);
        $this->assertSame(2, $order->announcement_count);
        $this->assertTrue($order->announced_at->equalTo(now()));
        $this->assertSame(['Course toujours disponible', 'Nouvelle course disponible'], $this->notificationTitles($this->courier));
        $this->assertSame([], $this->notificationTitles($this->farCourier));
        // Le client n'est prévenu qu'à la première publication.
        $this->assertSame(['Recherche d’un livreur'], $this->notificationTitles($this->client));

        // Le délai repart : nouvelle relance refusée tout de suite.
        $this->actingAs($this->owner)->post(route('business.orders.relaunch', $order))->assertSessionHas('error');
        $this->assertSame(2, $order->fresh()->announcement_count);
    }

    public function test_after_five_minutes_the_business_can_cancel_with_a_mandatory_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing);
        $this->publish($order);
        $this->freeze('2026-10-02 12:05:01');

        $this->actingAs($this->owner)
            ->put(route('orders.status.update', $order), ['status' => 'annulee'])
            ->assertSessionHas('error');
        $this->assertSame(OrderStatus::SearchingCourier, $order->fresh()->status);

        $this->actingAs($this->owner)
            ->put(route('orders.status.update', $order), ['status' => 'annulee', 'note' => 'Aucun livreur disponible'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Aucun livreur disponible', $order->cancel_reason);
        $this->assertContains('Commande annulée', $this->notificationTitles($this->client));
        $this->assertNotContains('Commande annulée', $this->notificationTitles($this->owner));
    }

    public function test_another_business_cannot_relaunch_the_announcement(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing);
        $this->publish($order);
        $this->freeze('2026-10-02 12:10:00');

        $other = $this->makeBusinessWithStore();

        $this->actingAs($other)->post(route('business.orders.relaunch', $order))->assertForbidden();
        $this->assertSame(1, $order->fresh()->announcement_count);
    }

    public function test_courier_details_appear_as_soon_as_a_courier_accepts_then_the_order_moves_through_the_tabs(): void
    {
        $this->courier->forceFill(['phone' => '077 11 22 33'])->save();
        $this->courier->deliveryProfile->update(['vehicle_type' => VehicleType::Moto, 'vehicle_brand' => 'Yamaha']);
        $order = $this->makeOrder(OrderStatus::Preparing);
        $this->publish($order);

        $this->actingAs($this->courier)->post(route('delivery.orders.accept', $order));
        $this->assertSame(OrderStatus::CourierAssigned, $order->fresh()->status);
        $this->assertContains('Livreur assigné', $this->notificationTitles($this->owner));

        $this->actingAs($this->owner)
            ->get('/business/orders?tab=searching')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.searching', 1)
                ->where('counts.delivering', 0)
                ->where('orders.data.0.status', 'livreur_assigne')
                ->where('orders.data.0.announcement', null)
                ->where('orders.data.0.courier', [
                    'name' => 'Jean Livreur',
                    'phone' => '077 11 22 33',
                    'vehicle' => 'Moto',
                    'vehicle_brand' => 'Yamaha',
                ]));

        $this->actingAs($this->owner)
            ->get(route('business.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.courier.name', 'Jean Livreur'));

        // Récupération : entreprise notifiée, onglet « En livraison ».
        $this->actingAs($this->courier)->put(route('orders.status.update', $order), ['status' => 'en_livraison']);
        $this->assertContains('Commande récupérée', $this->notificationTitles($this->owner));
        $this->actingAs($this->owner)
            ->get('/business/orders?tab=delivering')
            ->assertInertia(fn (Assert $page) => $page->where('counts.searching', 0)->where('counts.delivering', 1)->where('orders.data.0.courier.name', 'Jean Livreur'));

        // Livraison : onglet « Terminées ».
        $this->actingAs($this->courier)->put(route('orders.status.update', $order), ['status' => 'arrive']);
        $this->actingAs($this->courier)->put(route('orders.status.update', $order), ['status' => 'livree']);
        $this->assertContains('Commande livrée', $this->notificationTitles($this->owner));
        $this->actingAs($this->owner)
            ->get('/business/orders?tab=finished')
            ->assertInertia(fn (Assert $page) => $page->where('counts.delivering', 0)->where('counts.finished', 1)->where('orders.data.0.status', 'livree'));
    }

    private function makeBusinessWithStore(): User
    {
        $business = User::factory()->create(['role' => 'business']);
        Store::factory()->create(['owner_id' => $business->id]);

        return $business;
    }
}
