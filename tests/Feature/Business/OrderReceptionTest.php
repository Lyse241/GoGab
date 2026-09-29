<?php

namespace Tests\Feature\Business;

use App\Enums\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderWorkflow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Réception et traitement des commandes par l'entreprise (/business/orders).
 */
class OrderReceptionTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_orders_are_grouped_in_tabs_and_limited_to_the_business_store(): void
    {
        $new = $this->makeOrder(OrderStatus::Pending, ['client_note' => 'Sans piment']);
        $this->makeOrder(OrderStatus::Accepted);
        $this->makeOrder(OrderStatus::Preparing);
        $this->makeOrder(OrderStatus::SearchingCourier);
        $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $this->makeOrder(OrderStatus::Delivered);
        $this->makeOrder(OrderStatus::Refused);

        // Commande d'un autre commerce : invisible.
        $other = Store::factory()->create(['owner_id' => User::factory()->create(['role' => 'business'])->id]);
        $this->makeOrder(attributes: ['store_id' => $other->id]);

        $this->actingAs($this->owner)
            ->get('/business/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Orders/Index')
                ->where('tab', 'new')
                ->where('counts', ['new' => 1, 'preparing' => 2, 'searching' => 1, 'delivering' => 1, 'finished' => 2])
                ->where('pendingIds', [$new->id])
                ->has('orders.data', 1)
                ->where('orders.data.0.number', $new->reference)
                ->where('orders.data.0.client', 'Marie Ndong')
                ->where('orders.data.0.neighborhood', 'Glass')
                ->where('orders.data.0.payment_method_label', 'Paiement à la livraison')
                ->where('orders.data.0.client_note', 'Sans piment')
                ->where('orders.data.0.items.0.name', 'Poulet nyembwe')
                ->where('orders.data.0.items.0.quantity', 2)
                ->where('orders.data.0.actions', ['acceptee', 'refusee']));

        $this->actingAs($this->owner)
            ->get('/business/orders?tab=preparing')
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 2)
                ->where('orders.data.0.actions', ['en_preparation'])
                ->where('orders.data.1.actions', ['en_recherche_livreur']));

        $this->actingAs($this->owner)
            ->get('/business/orders?tab=finished')
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2)->where('orders.data.0.actions', []));
    }

    public function test_detail_shows_the_full_history_and_is_reserved_to_the_store(): void
    {
        $order = $this->makeOrder();
        $order->recordStatus(OrderStatus::Pending, $this->client);
        app(OrderWorkflow::class)->transition($order, OrderStatus::Accepted, $this->owner);

        $this->actingAs($this->owner)
            ->get("/business/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Orders/Show')
                ->where('order.status', 'acceptee')
                ->where('order.address_landmarks', 'Près de la pharmacie, portail bleu')
                ->has('order.history', 2)
                ->where('order.history.0.label', 'En attente')
                ->where('order.history.0.author', 'Marie Ndong')
                ->where('order.history.1.label', 'Acceptée')
                ->where('order.history.1.author', 'Maman Ngoye')
                ->where('order.history.1.author_role', 'Entreprise'));

        $intruder = User::factory()->create(['role' => 'business']);
        Store::factory()->create(['owner_id' => $intruder->id]);
        $this->actingAs($intruder)->get("/business/orders/{$order->id}")->assertForbidden();
    }

    public function test_accept_refuse_and_prepare_notify_the_client(): void
    {
        $accepted = $this->makeOrder();
        $this->actingAs($this->owner)->put("/orders/{$accepted->id}/status", ['status' => 'acceptee'])->assertSessionHas('success');
        $this->actingAs($this->owner)->put("/orders/{$accepted->id}/status", ['status' => 'en_preparation'])->assertSessionHas('success');
        $this->assertSame(OrderStatus::Preparing, $accepted->fresh()->status);

        $refused = $this->makeOrder();
        // Motif obligatoire.
        $this->actingAs($this->owner)
            ->put("/orders/{$refused->id}/status", ['status' => 'refusee'])
            ->assertSessionHas('error', 'Indiquez le motif pour passer la commande à « Refusée ».');
        $this->assertSame(OrderStatus::Pending, $refused->fresh()->status);

        $this->actingAs($this->owner)->put("/orders/{$refused->id}/status", ['status' => 'refusee', 'note' => 'Plus de poulet.']);
        $this->assertSame(OrderStatus::Refused, $refused->fresh()->status);

        $this->assertSame(['Commande acceptée', 'Commande en préparation', 'Commande refusée'], $this->notificationTitles($this->client));
        $this->assertStringContainsString('Motif : Plus de poulet.', $this->client->notifications()->get()->firstWhere('data.title', 'Commande refusée')->data['message']);
    }

    public function test_business_cannot_process_another_store_orders(): void
    {
        $intruder = User::factory()->create(['role' => 'business']);
        Store::factory()->create(['owner_id' => $intruder->id]);
        $order = $this->makeOrder();

        $this->actingAs($intruder)
            ->put("/orders/{$order->id}/status", ['status' => 'acceptee'])
            ->assertSessionHas('error', "La commande {$order->reference} ne concerne pas votre commerce.");
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_new_order_counter_and_notification_link_open_the_order(): void
    {
        $order = app(OrderWorkflow::class)->place($this->client, [
            'store_id' => $this->store->id,
            'neighborhood_id' => $this->makeOrder()->neighborhood_id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 4500,
            'total_price' => 4500,
            'payment_method' => 'airtel_money',
        ], [['product_id' => $this->store->products()->first()->id, 'quantity' => 1, 'price' => 4500]]);

        $this->assertSame("/business/orders/{$order->id}", $this->owner->notifications()->sole()->data['url']);

        $this->actingAs($this->owner)
            ->get('/business')
            ->assertInertia(fn (Assert $page) => $page->where('badges.new_orders', 2)->where('stats.new', 2));
    }

    public function test_dashboard_counts_today_in_libreville_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 10:00', 'Africa/Libreville'));
        $this->makeOrder(OrderStatus::Pending);
        $this->makeOrder(OrderStatus::Preparing);
        $this->makeOrder(OrderStatus::Delivered, ['subtotal' => 7000, 'total_price' => 8000]);
        $this->makeOrder(OrderStatus::Delivered, ['subtotal' => 3000, 'total_price' => 4000]);
        $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00', 'Africa/Libreville'));
        $this->makeOrder(OrderStatus::Delivered, ['subtotal' => 99000, 'total_price' => 100000]); // hier
        $this->travelTo(CarbonImmutable::parse('2026-09-28 18:00', 'Africa/Libreville'));

        $this->actingAs($this->owner)
            ->get('/business')
            ->assertInertia(fn (Assert $page) => $page->where('stats', ['new' => 4, 'preparing' => 1, 'delivered' => 2, 'revenue' => 10000]));
    }

    public function test_other_roles_cannot_open_the_business_orders(): void
    {
        foreach ([$this->client, $this->courier, $this->admin] as $user) {
            $this->actingAs($user)->get('/business/orders')->assertSessionHas('error');
        }
    }
}
