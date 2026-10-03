<?php

namespace Tests\Feature\CriticalFlows;

use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Parcours critique 6 — sécurité du checkout : le serveur ne fait jamais confiance au navigateur.
 */
class CheckoutSecurityTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private Product $poulet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        config(['gogab.delivery_fee' => 1000]);
        $this->poulet = $this->store->products()->create(['name' => 'Poulet nyembwe', 'price' => 4500]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function order(array $items, array $overrides = [], ?User $client = null)
    {
        return $this->actingAs($client ?? $this->client)->post('/orders', [
            'store_id' => $this->store->id,
            'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'airtel_money',
            'items' => $items,
            ...$overrides,
        ]);
    }

    public function test_a_forged_price_or_total_is_ignored(): void
    {
        $this->order([['product_id' => $this->poulet->id, 'quantity' => 2, 'price' => 1, 'unit_price' => 1]], [
            'subtotal' => 2,
            'total_price' => 2,
            'delivery_fee' => 0,
        ])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame('9000.00', $order->subtotal);
        $this->assertSame('1000.00', $order->delivery_fee);
        $this->assertSame('10000.00', $order->total_price);
        $this->assertSame('4500.00', $order->items()->sole()->price);
    }

    public function test_a_product_of_another_store_is_refused(): void
    {
        $other = Store::factory()->create(['name' => 'Autre commerce']);
        $foreign = $other->products()->create(['name' => 'Pizza', 'price' => 100]);

        $this->order([['product_id' => $this->poulet->id, 'quantity' => 1], ['product_id' => $foreign->id, 'quantity' => 1]])
            ->assertSessionHasErrors('items');
        $this->order([['product_id' => $foreign->id, 'quantity' => 1]])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_an_unavailable_or_unknown_product_is_refused(): void
    {
        $this->poulet->update(['is_available' => false]);
        $this->order([['product_id' => $this->poulet->id, 'quantity' => 1]])->assertSessionHasErrors('items');
        $this->order([['product_id' => 99999, 'quantity' => 1]])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_a_closed_store_is_refused(): void
    {
        // Fermeture manuelle.
        $this->store->update(['is_open' => false]);
        $this->order([['product_id' => $this->poulet->id, 'quantity' => 1]])->assertSessionHasErrors('items');

        // Hors horaires : ouvert de 08:00 à 22:00, il est 23 h à Libreville.
        $this->store->update(['is_open' => true]);
        StoreHours::sync($this->store, StoreHours::everyDay('08:00', '22:00'));
        Carbon::setTestNow('2026-10-02 22:00:00'); // 23 h à Libreville (UTC+1)
        CarbonImmutable::setTestNow('2026-10-02 22:00:00');
        $this->order([['product_id' => $this->poulet->id, 'quantity' => 1]])
            ->assertSessionHasErrors(['items' => 'Chez Maman Ngoye : Fermé · ouvre demain à 08h00. Votre panier est conservé : vous pourrez commander à la réouverture.']);

        $this->assertSame(0, Order::count());
        $this->assertCount(0, $this->owner->notifications);
    }

    public function test_cash_below_the_total_is_refused(): void
    {
        $items = [['product_id' => $this->poulet->id, 'quantity' => 2]]; // 10 000 FCFA avec les frais

        $this->order($items, ['payment_method' => 'cash'])->assertSessionHasErrors('cash_given');
        $this->order($items, ['payment_method' => 'cash', 'cash_given' => 9999])
            ->assertSessionHasErrors(['cash_given' => 'Le montant remis doit couvrir le total de la commande (10 000 FCFA).']);
        $this->assertSame(0, Order::count());

        $this->order($items, ['payment_method' => 'cash', 'cash_given' => 10000])->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    public function test_an_invisible_store_and_a_non_client_are_refused(): void
    {
        $this->store->update(['is_active' => false]);
        $this->order([['product_id' => $this->poulet->id, 'quantity' => 1]])->assertSessionHasErrors('items');

        $this->store->update(['is_active' => true]);
        foreach ([$this->owner, $this->courier, $this->admin] as $user) {
            $this->order([['product_id' => $this->poulet->id, 'quantity' => 1]], client: $user)->assertRedirect();
        }

        $this->assertSame(0, Order::count());
    }
}
