<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Suivi côté client : « Mes commandes » (en cours / terminées), timeline, livreur, annulation.
 */
class OrderTrackingTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private OrderWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $this->workflow = app(OrderWorkflow::class);
    }

    public function test_order_list_separates_ongoing_and_finished_orders(): void
    {
        $pending = $this->makeOrder();
        $delivering = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $delivered = $this->makeOrder(OrderStatus::Delivered);
        $cancelled = $this->makeOrder(OrderStatus::Cancelled);
        // Commande d'un autre client : jamais listée.
        $this->makeOrder(attributes: ['client_id' => User::factory()->create(['role' => 'client'])->id]);

        $this->actingAs($this->client)
            ->get('/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Index')
                ->where('tab', 'ongoing')
                ->where('counts', ['ongoing' => 2, 'finished' => 2])
                ->has('orders.data', 2)
                ->where('orders.data.0.id', $delivering->id) // plus récente d'abord
                ->where('orders.data.1.id', $pending->id)
                ->where('orders.data.1.store.name', 'Chez Maman Ngoye')
                ->where('orders.data.1.status', 'en_attente')
                ->where('orders.data.1.total_price', '9000.00')
                ->where('orders.data.1.items_count', 2));

        $this->actingAs($this->client)
            ->get('/orders?tab=finished')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'finished')
                ->has('orders.data', 2)
                ->where('orders.data.0.id', $cancelled->id)
                ->where('orders.data.1.id', $delivered->id));
    }

    public function test_order_list_is_paginated_and_reserved_to_clients(): void
    {
        foreach (range(1, 12) as $i) {
            $this->makeOrder();
        }

        $this->actingAs($this->client)->get('/orders')
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 10)->where('orders.last_page', 2));

        $this->actingAs($this->owner)->get('/orders')->assertSessionHas('error');
        auth()->logout();
        $this->get('/orders')->assertRedirect(route('login', absolute: false));
    }

    public function test_timeline_follows_the_status_history_with_times(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $order = $this->workflow->place($this->client, [
            'store_id' => $this->store->id,
            'neighborhood_id' => $this->makeOrder()->neighborhood_id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 4500,
            'delivery_fee' => 1000,
            'total_price' => 5500,
            'payment_method' => 'cash',
            'cash_given' => 10000,
        ], [['product_id' => $this->store->products()->first()->id, 'quantity' => 1, 'price' => 4500]]);
        $this->travelTo(now()->setTime(9, 5));
        $this->workflow->transition($order, OrderStatus::Accepted, $this->owner);
        $this->travelTo(now()->setTime(9, 12));
        $this->workflow->transition($order, OrderStatus::Preparing, $this->owner);

        $this->actingAs($this->client)
            ->get("/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Show')
                ->where('order.is_final', false)
                ->where('order.can_cancel', false) // déjà acceptée
                ->where('order.cash_given', '10000.00')
                ->where('order.change_due', 4500)
                ->has('order.timeline', 8)
                ->where('order.timeline.0.label', 'Commande envoyée')
                ->where('order.timeline.0.state', 'done')
                ->where('order.timeline.0.at', now()->setTime(9, 0)->setTimezone('Africa/Libreville')->format('H\hi'))
                ->where('order.timeline.1.label', 'Acceptée')
                ->where('order.timeline.1.state', 'done')
                ->where('order.timeline.2.label', 'En préparation')
                ->where('order.timeline.2.state', 'current')
                ->where('order.timeline.3.label', 'Recherche d’un livreur')
                ->where('order.timeline.3.state', 'upcoming')
                ->where('order.timeline.3.at', null)
                ->where('order.timeline.5.label', 'En route')
                ->where('order.timeline.7.label', 'Livrée')
                ->where('order.courier', null));
    }

    public function test_assigned_courier_is_shown_with_first_name_vehicle_and_phone(): void
    {
        $this->courier->update(['phone' => '077 11 22 33']);
        $this->courier->deliveryProfile->update(['vehicle_brand' => 'Yamaha']);
        $order = $this->makeOrder(OrderStatus::SearchingCourier);
        $this->workflow->transition($order, OrderStatus::CourierAssigned, $this->courier);

        $this->actingAs($this->client)
            ->get("/orders/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.courier', ['first_name' => 'Jean', 'vehicle' => 'Moto', 'vehicle_brand' => 'Yamaha', 'phone' => '077 11 22 33'])
                ->where('order.timeline.4.state', 'current'));

        // Commande livrée : plus de numéro à appeler.
        foreach ([OrderStatus::Delivering, OrderStatus::Arrived, OrderStatus::Delivered] as $step) {
            $this->workflow->transition($order->fresh(), $step, $this->courier);
        }
        $this->actingAs($this->client)
            ->get("/orders/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.is_final', true)
                ->where('order.courier.phone', null)
                ->where('order.timeline.7.state', 'current'));
    }

    public function test_refused_order_stops_the_timeline_with_the_reason(): void
    {
        $order = $this->makeOrder();
        $order->recordStatus(OrderStatus::Pending, $this->client);
        $this->workflow->transition($order, OrderStatus::Refused, $this->owner, 'Rupture de stock.');

        $this->actingAs($this->client)
            ->get("/orders/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.is_final', true)
                ->has('order.timeline', 2)
                ->where('order.timeline.0.state', 'done')
                ->where('order.timeline.1.label', 'Refusée par le commerce')
                ->where('order.timeline.1.state', 'current')
                ->where('order.timeline.1.note', 'Rupture de stock.'));
    }

    public function test_client_cancels_only_while_pending(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->client)->get("/orders/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page->where('order.can_cancel', true));

        $this->actingAs($this->client)
            ->put("/orders/{$order->id}/status", ['status' => 'annulee', 'note' => 'Je me suis trompé d’adresse.'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Je me suis trompé d’adresse.', $order->cancel_reason);
        $this->assertSame(['Commande annulée'], $this->notificationTitles($this->owner));

        // Motif facultatif, mais plus d'annulation une fois acceptée.
        $accepted = $this->makeOrder(OrderStatus::Accepted);
        $this->actingAs($this->client)->get("/orders/{$accepted->id}")
            ->assertInertia(fn (Assert $page) => $page->where('order.can_cancel', false));
        $this->actingAs($this->client)
            ->put("/orders/{$accepted->id}/status", ['status' => 'annulee'])
            ->assertSessionHas('error');
        $this->assertSame(OrderStatus::Accepted, $accepted->fresh()->status);
    }

    public function test_client_cannot_open_someone_elses_order(): void
    {
        $order = $this->makeOrder();
        $other = User::factory()->create(['role' => 'client']);

        $this->actingAs($other)->get("/orders/{$order->id}")->assertForbidden();
        $this->actingAs($other)->put("/orders/{$order->id}/status", ['status' => 'annulee'])->assertSessionHas('error');
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_client_notifications_open_the_order(): void
    {
        $order = $this->makeOrder();
        $this->workflow->transition($order, OrderStatus::Accepted, $this->owner);

        $this->assertSame("/orders/{$order->id}", $this->client->notifications()->sole()->data['url']);
    }
}
