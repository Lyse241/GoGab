<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Espace livreur (HTTP) : annonces de sa zone, prise de course et étapes de livraison.
 * Les règles elles-mêmes sont testées dans Orders\OrderWorkflowTest.
 */
class DeliveryTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
    }

    public function test_offers_and_current_pages_list_zone_announcements_and_own_deliveries(): void
    {
        $announcement = $this->makeOrder(OrderStatus::SearchingCourier);
        $this->makeOrder(OrderStatus::Preparing); // pas encore annoncée
        $mine = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->farCourier->id]);
        $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);

        $this->actingAs($this->courier)
            ->get('/delivery/offers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Offers')
                ->where('zone', 'Centre')
                ->where('isAvailable', true)
                ->has('offers', 1)
                ->where('offers.0.id', $announcement->id)
                ->where('offers.0.store', 'Chez Maman Ngoye')
                ->where('offers.0.store_neighborhood', 'Louis')
                ->where('offers.0.neighborhood', 'Glass')
                ->where('offers.0.cash_given', '10000.00')
                ->missing('offers.0.client') // ni nom ni téléphone avant acceptation
                ->missing('offers.0.address_landmarks')
                ->where('busy.id', $mine->id)
                ->where('badges', ['offers' => 1, 'active_orders' => 1]));

        $this->actingAs($this->courier)
            ->get('/delivery/current')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Current')
                ->has('orders', 1)
                ->where('orders.0.id', $mine->id)
                ->where('orders.0.next_status', 'arrive')
                ->where('orders.0.client.phone', '066 20 00 01'));

        // Livreur d'une autre zone : aucune annonce.
        $this->actingAs($this->farCourier)
            ->get('/delivery/offers')
            ->assertInertia(fn (Assert $page) => $page->where('zone', 'Nord')->has('offers', 0));
    }

    public function test_unavailable_courier_sees_no_announcement(): void
    {
        $this->makeOrder(OrderStatus::SearchingCourier);
        $this->courier->deliveryProfile->update(['is_available' => false]);

        $this->actingAs($this->courier->fresh())
            ->get('/delivery/offers')
            ->assertInertia(fn (Assert $page) => $page->where('isAvailable', false)->has('offers', 0)->where('badges.offers', 0));
    }

    public function test_courier_takes_an_announced_order(): void
    {
        $order = $this->makeOrder(OrderStatus::SearchingCourier);

        $this->actingAs($this->courier)
            ->post("/delivery/orders/{$order->id}/accept")
            ->assertRedirect('/delivery/current')
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($this->courier->id, $order->delivery_id);
        $this->assertSame(OrderStatus::CourierAssigned, $order->status);
        $this->assertSame($this->courier->id, $order->statusHistories()->sole()->changed_by);
    }

    public function test_an_order_cannot_be_taken_twice(): void
    {
        $order = $this->makeOrder(OrderStatus::SearchingCourier);
        $colleague = $this->makeCourier('Collègue', Neighborhood::firstWhere('name', 'Louis'));

        $this->actingAs($colleague)->post("/delivery/orders/{$order->id}/accept");

        $this->actingAs($this->courier)
            ->post("/delivery/orders/{$order->id}/accept")
            ->assertSessionHas('error', OrderWorkflow::TAKEN_MESSAGE);

        $this->assertSame($colleague->id, $order->fresh()->delivery_id);
    }

    public function test_courier_advances_step_by_step(): void
    {
        $order = $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->courier->id]);

        foreach (['en_livraison' => OrderStatus::Delivering, 'arrive' => OrderStatus::Arrived, 'livree' => OrderStatus::Delivered] as $step => $expected) {
            $this->actingAs($this->courier)
                ->put("/orders/{$order->id}/status", ['status' => $step])
                ->assertSessionHas('success');
            $this->assertSame($expected, $order->fresh()->status);
        }
    }

    public function test_status_cannot_skip_a_step_or_be_repeated(): void
    {
        $order = $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->courier->id]);

        // Saut d'étape refusé.
        $this->actingAs($this->courier)->put("/orders/{$order->id}/status", ['status' => 'livree'])->assertSessionHas('error');
        $this->assertSame(OrderStatus::CourierAssigned, $order->fresh()->status);

        // Double clic : la seconde requête identique est refusée.
        $this->actingAs($this->courier)->put("/orders/{$order->id}/status", ['status' => 'en_livraison']);
        $this->actingAs($this->courier)->put("/orders/{$order->id}/status", ['status' => 'en_livraison'])->assertSessionHas('error');

        // Retour en arrière impossible.
        $this->actingAs($this->courier)->put("/orders/{$order->id}/status", ['status' => 'livreur_assigne'])->assertSessionHas('error');
        $this->assertSame(OrderStatus::Delivering, $order->fresh()->status);
    }

    public function test_only_the_assigned_courier_can_update_the_delivery(): void
    {
        $order = $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->courier->id]);
        $colleague = $this->makeCourier('Collègue', Neighborhood::firstWhere('name', 'Louis'));

        $this->actingAs($colleague)
            ->put("/orders/{$order->id}/status", ['status' => 'en_livraison'])
            ->assertSessionHas('error', "La commande {$order->reference} n’est pas assignée à votre compte.");

        $this->assertSame(OrderStatus::CourierAssigned, $order->fresh()->status);
    }

    public function test_non_couriers_cannot_take_orders(): void
    {
        $order = $this->makeOrder(OrderStatus::SearchingCourier);

        foreach ([$this->client, $this->admin, $this->owner] as $user) {
            $this->actingAs($user)->post("/delivery/orders/{$order->id}/accept")->assertSessionHas('error');
        }

        $this->assertNull($order->fresh()->delivery_id);
        $this->assertSame(OrderStatus::SearchingCourier, $order->fresh()->status);
    }
}
