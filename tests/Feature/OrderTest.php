<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Neighborhood $neighborhood;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client']);
        $this->neighborhood = Neighborhood::create(['name' => 'Glass']);
        $this->store = Store::factory()->inCategory('Restaurant')->create(['name' => 'Chez Test']);
    }

    private function payload(array $items): array
    {
        return [
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'airtel_money',
            'items' => $items,
        ];
    }

    public function test_client_can_place_an_order_with_server_side_prices(): void
    {
        $poulet = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $riz = $this->store->products()->create(['name' => 'Riz', 'price' => 2500]);

        $response = $this->actingAs($this->client)->post('/orders', $this->payload([
            // Un prix envoyé par le navigateur doit être ignoré.
            ['product_id' => $poulet->id, 'quantity' => 2, 'price' => 1],
            ['product_id' => $riz->id, 'quantity' => 1],
        ]));

        $order = Order::sole();
        $response->assertRedirect(route('orders.show', $order, absolute: false))
            ->assertSessionHas('success');

        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame($this->store->id, $order->store_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentMethod::AirtelMoney, $order->payment_method);
        $this->assertNull($order->delivery_id);
        $this->assertNull($order->cash_given);
        $this->assertSame('11500.00', $order->subtotal);
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('11500.00', $order->total_price);
        $this->assertSame('GG-'.str_pad($order->id, 6, '0', STR_PAD_LEFT), $order->reference);

        // Le passage en attente est tracé dans l'historique.
        $history = $order->statusHistories()->sole();
        $this->assertSame(OrderStatus::Pending, $history->status);
        $this->assertSame($this->client->id, $history->changed_by);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $poulet->id, 'quantity' => 2, 'price' => 4500]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $riz->id, 'quantity' => 1, 'price' => 2500]);
    }

    public function test_cash_order_keeps_the_amount_given_and_note(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($this->client)->post('/orders', [
            ...$this->payload([['product_id' => $product->id, 'quantity' => 1]]),
            'payment_method' => 'cash',
            'cash_given' => 10000,
            'client_note' => 'Sans piment, svp',
        ])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertSame('10000.00', $order->cash_given);
        $this->assertSame('Sans piment, svp', $order->client_note);
    }

    public function test_cash_given_is_refused_for_mobile_money(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($this->client)
            ->post('/orders', [...$this->payload([['product_id' => $product->id, 'quantity' => 1]]), 'cash_given' => 10000])
            ->assertSessionHasErrors('cash_given');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unavailable_product_is_rejected(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500, 'is_available' => false]);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->actingAs($this->client)
            ->post('/orders', $this->payload([]))
            ->assertSessionHasErrors(['items' => 'Votre panier est vide.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_product_is_rejected(): void
    {
        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => 999, 'quantity' => 1]]))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_products_from_two_stores_are_rejected(): void
    {
        $a = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $b = Store::factory()->inCategory('Pharmacie')->create(['name' => 'Pharmacie'])
            ->products()->create(['name' => 'Paracétamol', 'price' => 1000]);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload([
                ['product_id' => $a->id, 'quantity' => 1],
                ['product_id' => $b->id, 'quantity' => 1],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_fields_are_rejected(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($this->client)
            ->post('/orders', [
                'neighborhood_id' => 999,
                'address_landmarks' => 'Glass',
                'payment_method' => 'carte_bancaire',
                'items' => [['product_id' => $product->id, 'quantity' => 0]],
            ])
            ->assertSessionHasErrors(['neighborhood_id', 'address_landmarks', 'payment_method', 'items.0.quantity']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_only_clients_can_order(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $delivery = User::factory()->create(['role' => 'delivery']);

        $this->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertRedirect(route('login', absolute: false));

        $this->actingAs($delivery)
            ->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirmation_page_shows_the_order(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $this->actingAs($this->client)->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 2]]));
        $order = Order::sole();

        $this->actingAs($this->client)
            ->get("/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Show')
                ->where('order.number', $order->reference)
                ->where('order.neighborhood', 'Glass')
                ->where('order.store', 'Chez Test')
                ->where('order.status', 'en_attente')
                ->where('order.payment_method_label', 'Airtel Money')
                ->has('order.items', 1)
                ->where('order.items.0.quantity', 2));
    }

    public function test_client_cannot_see_someone_elses_order(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $this->actingAs($this->client)->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]));

        $other = User::factory()->create(['role' => 'client']);

        $this->actingAs($other)->get('/orders/'.Order::sole()->id)->assertNotFound();
    }
}
