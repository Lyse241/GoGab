<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'phone' => '066 20 00 01']);
        $this->courier = User::factory()->create(['role' => 'delivery']);
    }

    private function makeOrder(array $attributes = []): Order
    {
        $store = Store::firstOrCreate(['name' => 'Chez Test'], ['category' => 'Restaurant']);
        $product = $store->products()->firstOrCreate(['name' => 'Poulet'], ['price' => 4500]);
        $neighborhood = Neighborhood::firstOrCreate(['name' => 'Glass']);

        $order = Order::create([
            'client_id' => $this->client->id,
            'neighborhood_id' => $neighborhood->id,
            'total_price' => 9000,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'cash_on_delivery',
            'status' => 'en_attente',
            ...$attributes,
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 4500]);

        return $order;
    }

    public function test_dashboard_lists_available_and_own_orders(): void
    {
        $available = $this->makeOrder();
        $mine = $this->makeOrder(['delivery_id' => $this->courier->id, 'status' => 'en_livraison']);
        $otherCourier = User::factory()->create(['role' => 'delivery']);
        $this->makeOrder(['delivery_id' => $otherCourier->id, 'status' => 'acceptee']);
        $this->makeOrder(['delivery_id' => $this->courier->id, 'status' => 'livree']);

        $this->actingAs($this->courier)
            ->get('/delivery/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Dashboard')
                ->has('available', 1)
                ->where('available.0.id', $available->id)
                ->where('available.0.neighborhood', 'Glass')
                ->where('available.0.store', 'Chez Test')
                ->where('available.0.payment_method_label', 'Paiement à la livraison')
                ->where('available.0.address_landmarks', 'Près de la pharmacie, portail bleu')
                ->where('available.0.client', null) // pas de téléphone avant acceptation
                ->has('mine', 1)
                ->where('mine.0.id', $mine->id)
                ->where('mine.0.next_status', 'livree')
                ->where('mine.0.client.phone', '066 20 00 01')
                ->where('deliveredToday', 1));
    }

    public function test_courier_can_accept_an_available_order(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->courier)
            ->post("/delivery/orders/{$order->id}/accept")
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($this->courier->id, $order->delivery_id);
        $this->assertSame('acceptee', $order->status);
    }

    public function test_an_order_cannot_be_accepted_twice(): void
    {
        $order = $this->makeOrder();
        $first = User::factory()->create(['role' => 'delivery']);

        $this->actingAs($first)->post("/delivery/orders/{$order->id}/accept");

        $this->actingAs($this->courier)
            ->post("/delivery/orders/{$order->id}/accept")
            ->assertSessionHas('error');

        $this->assertSame($first->id, $order->fresh()->delivery_id);
    }

    public function test_courier_advances_status_step_by_step(): void
    {
        $order = $this->makeOrder(['delivery_id' => $this->courier->id, 'status' => 'acceptee']);

        $this->actingAs($this->courier)
            ->put("/orders/{$order->id}/status", ['status' => 'en_livraison'])
            ->assertSessionHas('success');
        $this->assertSame('en_livraison', $order->fresh()->status);

        $this->actingAs($this->courier)
            ->put("/orders/{$order->id}/status", ['status' => 'livree'])
            ->assertSessionHas('success');
        $this->assertSame('livree', $order->fresh()->status);
    }

    public function test_status_cannot_skip_a_step_or_be_repeated(): void
    {
        $order = $this->makeOrder(['delivery_id' => $this->courier->id, 'status' => 'acceptee']);

        // Saut d'étape acceptee → livree refusé.
        $this->actingAs($this->courier)
            ->put("/orders/{$order->id}/status", ['status' => 'livree'])
            ->assertSessionHas('error');
        $this->assertSame('acceptee', $order->fresh()->status);

        // Double clic : la 2e requête identique est refusée.
        $this->actingAs($this->courier)->put("/orders/{$order->id}/status", ['status' => 'en_livraison']);
        $this->actingAs($this->courier)
            ->put("/orders/{$order->id}/status", ['status' => 'en_livraison'])
            ->assertSessionHas('error');

        // Retour en arrière impossible.
        $this->actingAs($this->courier)
            ->put("/orders/{$order->id}/status", ['status' => 'en_attente'])
            ->assertSessionHasErrors('status');
        $this->assertSame('en_livraison', $order->fresh()->status);
    }

    public function test_only_the_assigned_courier_can_update_status(): void
    {
        $order = $this->makeOrder(['delivery_id' => $this->courier->id, 'status' => 'acceptee']);
        $otherCourier = User::factory()->create(['role' => 'delivery']);

        $this->actingAs($otherCourier)
            ->put("/orders/{$order->id}/status", ['status' => 'en_livraison'])
            ->assertForbidden();

        $this->assertSame('acceptee', $order->fresh()->status);
    }

    public function test_non_couriers_cannot_use_delivery_actions(): void
    {
        $order = $this->makeOrder();

        foreach (['client', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->post("/delivery/orders/{$order->id}/accept")->assertSessionHas('error');
            $this->actingAs($user)->put("/orders/{$order->id}/status", ['status' => 'acceptee'])->assertSessionHas('error');
        }

        $this->assertNull($order->fresh()->delivery_id);
        $this->assertSame('en_attente', $order->fresh()->status);
    }
}
