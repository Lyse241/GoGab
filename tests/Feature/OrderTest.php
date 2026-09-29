<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
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
 * Checkout et création de commande (POST /orders) : le panier d'UN commerce, prix relus en base,
 * frais de livraison configurables, montant remis en espèces.
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $owner;

    private Neighborhood $neighborhood;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gogab.delivery_fee' => 1000]);
        $this->neighborhood = Neighborhood::create(['name' => 'Glass', 'zone' => 'Centre']);
        $this->client = User::factory()->create([
            'role' => 'client',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Derrière la station Total, maison jaune',
        ]);
        $this->owner = User::factory()->create(['role' => 'business']);
        $this->store = Store::factory()->inCategory('Restaurant')->create(['name' => 'Chez Test', 'owner_id' => $this->owner->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function payload(array $items, array $overrides = []): array
    {
        return [
            'store_id' => $this->store->id,
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'airtel_money',
            'items' => $items,
            ...$overrides,
        ];
    }

    public function test_checkout_page_prefills_the_profile_address_and_sends_fees_and_payments(): void
    {
        $this->actingAs($this->client)
            ->get("/checkout/{$this->store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->where('store.id', $this->store->id)
                ->where('store.is_open_now', true)
                ->where('address', ['neighborhood_id' => $this->neighborhood->id, 'address_landmarks' => 'Derrière la station Total, maison jaune'])
                ->where('deliveryFee', 1000)
                ->where('quickCashAmounts', [5000, 10000, 20000])
                ->where('canOrder', true)
                ->where('paymentMethods.2', [
                    'value' => 'cash',
                    'label' => 'Paiement à la livraison',
                    'hint' => 'En espèces, à la remise de la commande.',
                    'mobile_money' => false,
                ])
                ->where('paymentMethods.0.hint', 'Le paiement se fait à la livraison via le numéro communiqué par le livreur.'));
    }

    public function test_pending_client_sees_the_checkout_but_cannot_order(): void
    {
        $pending = User::factory()->pending()->create(['role' => 'client']);
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($pending)
            ->get("/checkout/{$this->store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canOrder', false));

        $this->actingAs($pending)
            ->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertRedirect('/account/pending');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_client_places_an_order_with_server_side_prices_and_delivery_fee(): void
    {
        $poulet = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $riz = $this->store->products()->create(['name' => 'Riz', 'price' => 2500]);

        $response = $this->actingAs($this->client)->post('/orders', $this->payload([
            // Un prix envoyé par le navigateur doit être ignoré.
            ['product_id' => $poulet->id, 'quantity' => 2, 'price' => 1],
            ['product_id' => $riz->id, 'quantity' => 1],
        ]));

        $order = Order::sole();
        $response->assertRedirect(route('orders.confirmation', $order, absolute: false));

        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame($this->store->id, $order->store_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentMethod::AirtelMoney, $order->payment_method);
        $this->assertNull($order->delivery_id);
        $this->assertNull($order->cash_given);
        $this->assertNull($order->change_due);
        $this->assertSame('11500.00', $order->subtotal);
        $this->assertSame('1000.00', $order->delivery_fee);
        $this->assertSame('12500.00', $order->total_price); // sous-total + frais
        $this->assertSame('GG-'.str_pad($order->id, 6, '0', STR_PAD_LEFT), $order->reference);

        // Historique en_attente et lignes à prix figés.
        $history = $order->statusHistories()->sole();
        $this->assertSame(OrderStatus::Pending, $history->status);
        $this->assertSame($this->client->id, $history->changed_by);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $poulet->id, 'quantity' => 2, 'price' => 4500]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $riz->id, 'quantity' => 1, 'price' => 2500]);

        // L'entreprise est prévenue.
        $this->assertSame("Nouvelle commande {$order->reference}", $this->owner->notifications()->sole()->data['title']);

        // Un changement de prix ultérieur ne touche pas la commande.
        $poulet->update(['price' => 9999]);
        $this->assertSame('4500.00', $order->items()->where('product_id', $poulet->id)->value('price'));
    }

    public function test_confirmation_page_shows_number_and_tracking_link_data(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $this->actingAs($this->client)->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]], [
            'payment_method' => 'cash',
            'cash_given' => '10 000',
        ]));
        $order = Order::sole();

        $this->actingAs($this->client)
            ->get("/orders/{$order->id}/confirmation")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Confirmation')
                ->where('order.number', $order->reference)
                ->where('order.store', 'Chez Test')
                ->where('order.total_price', '5500.00')
                ->where('order.cash_given', '10000.00')
                ->where('order.change_due', 4500));

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get("/orders/{$order->id}/confirmation")
            ->assertForbidden();
    }

    public function test_cash_order_requires_an_amount_covering_the_total(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]); // total 5 500 avec les frais
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        $this->actingAs($this->client)
            ->post('/orders', $this->payload($items, ['payment_method' => 'cash']))
            ->assertSessionHasErrors(['cash_given' => 'Indiquez avec quel montant vous paierez, pour que le livreur prévoie la monnaie.']);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload($items, ['payment_method' => 'cash', 'cash_given' => 5000]))
            ->assertSessionHasErrors(['cash_given' => 'Le montant remis doit couvrir le total de la commande (5 500 FCFA).']);
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload($items, ['payment_method' => 'cash', 'cash_given' => 5500, 'client_note' => '  Sans piment, svp ']))
            ->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertSame('5500.00', $order->cash_given);
        $this->assertEquals(0, $order->change_due);
        $this->assertSame('Sans piment, svp', $order->client_note);
    }

    public function test_cash_given_is_ignored_for_mobile_money(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]], ['payment_method' => 'moov_money', 'cash_given' => 10000]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Order::sole()->cash_given);
    }

    public function test_closed_store_is_refused_with_the_opening_message(): void
    {
        $now = CarbonImmutable::parse('2026-09-28 06:30', 'Africa/Libreville');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        StoreHours::sync($this->store, StoreHours::everyDay('08:00', '22:00'));
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertSessionHasErrors(['items' => 'Chez Test : Fermé · ouvre à 08h00. Votre panier est conservé : vous pourrez commander à la réouverture.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invisible_store_is_refused(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $this->store->update(['is_active' => false]);

        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertSessionHasErrors(['items' => 'Ce commerce n’est plus disponible sur Gogab.']);
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

    public function test_products_must_all_belong_to_the_checkout_store(): void
    {
        $a = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $b = Store::factory()->inCategory('Pharmacie')->create(['name' => 'Pharmacie'])
            ->products()->create(['name' => 'Paracétamol', 'price' => 1000]);

        // Produit d'un autre commerce glissé dans le panier.
        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 1]]))
            ->assertSessionHasErrors('items');

        // Panier d'un commerce envoyé au checkout d'un autre.
        $this->actingAs($this->client)
            ->post('/orders', $this->payload([['product_id' => $b->id, 'quantity' => 1]]))
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
            ->assertSessionHasErrors(['store_id', 'neighborhood_id', 'address_landmarks', 'payment_method', 'items.0.quantity']);

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

    public function test_tracking_page_shows_the_order_with_fees_and_history(): void
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
                ->where('order.store.name', 'Chez Test')
                ->where('order.status', 'en_attente')
                ->where('order.subtotal', '9000.00')
                ->where('order.delivery_fee', '1000.00')
                ->where('order.total_price', '10000.00')
                ->where('order.payment_method_label', 'Airtel Money')
                ->where('order.change_due', null)
                ->has('order.timeline', 8)
                ->where('order.timeline.0.label', 'Commande envoyée')
                ->where('order.timeline.0.state', 'current')
                ->has('order.items', 1)
                ->where('order.items.0.quantity', 2));
    }

    public function test_client_cannot_see_someone_elses_order(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $this->actingAs($this->client)->post('/orders', $this->payload([['product_id' => $product->id, 'quantity' => 1]]));

        $other = User::factory()->create(['role' => 'client']);

        $this->actingAs($other)->get('/orders/'.Order::sole()->id)->assertForbidden();
    }
}
