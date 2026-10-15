<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PromoType;
use App\Exceptions\PromoException;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Services\OrderWorkflow;
use App\Services\PromoService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        config(['gogab.delivery_fee' => 1000, 'gogab.commission_rate' => 15]);
        $this->productId = $this->store->products()->create(['name' => 'Poulet DG', 'price' => 5000])->id;
    }

    private function promo(array $attributes = []): PromoCode
    {
        return PromoCode::create([
            'code' => 'BIENVENUE10',
            'type' => PromoType::Percent,
            'value' => 10,
            'max_uses_per_user' => 1,
            ...$attributes,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return [
            'store_id' => $this->store->id,
            'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'cash',
            'cash_given' => 20000,
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
            ...$extra,
        ];
    }

    private function discountFor(PromoCode $promo, float $subtotal = 10000, int $fee = 1000): int
    {
        return app(PromoService::class)->evaluate($promo->code, $this->client, $subtotal, $fee)['discount'];
    }

    public function test_discount_by_type(): void
    {
        $this->assertSame(1000, $this->discountFor($this->promo()));
        $this->assertSame(2000, $this->discountFor($this->promo(['code' => 'FIXE', 'type' => PromoType::Fixed, 'value' => 2000])));
        $this->assertSame(10000, $this->discountFor($this->promo(['code' => 'GROS', 'type' => PromoType::Fixed, 'value' => 50000])), 'jamais plus que le sous-total');
        $this->assertSame(1000, $this->discountFor($this->promo(['code' => 'LIVRAISON', 'type' => PromoType::FreeDelivery, 'value' => 0])));
    }

    public function test_the_typed_code_is_normalized(): void
    {
        $this->promo();

        $this->assertSame('BIENVENUE10', PromoService::normalize(' bienvenue 10 '));
        $this->assertSame(1000, app(PromoService::class)->evaluate(' bienvenue 10 ', $this->client, 10000, 1000)['discount']);
    }

    public function test_total_usage_limit_counts_only_live_orders(): void
    {
        $promo = $this->promo(['max_uses' => 1]);
        $other = \App\Models\User::factory()->create(['role' => 'client']);
        $used = $this->makeOrder(OrderStatus::Accepted, ['client_id' => $other->id]);
        PromoRedemption::create(['promo_code_id' => $promo->id, 'user_id' => $other->id, 'order_id' => $used->id, 'discount' => 1000]);

        try {
            app(PromoService::class)->evaluate($promo->code, $this->client, 10000, 1000);
            $this->fail('La limite aurait dû être atteinte.');
        } catch (PromoException $e) {
            $this->assertStringContainsString('limite d’utilisations', $e->getMessage());
        }

        // La commande de l'autre client est annulée : le code est de nouveau disponible.
        $used->update(['status' => OrderStatus::Cancelled]);
        $this->assertSame(1000, $this->discountFor($promo));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function test_refused_codes(array $attributes, string $message): void
    {
        $promo = $this->promo($attributes);

        $this->expectException(PromoException::class);
        $this->expectExceptionMessage($message);
        app(PromoService::class)->evaluate($promo->code, $this->client, 10000, 1000);
    }

    public static function refusals(): array
    {
        return [
            'inactif' => [['is_active' => false], 'n’existe pas ou n’est plus actif'],
            'expiré' => [['ends_at' => now()->subDay()], 'a expiré'],
            'pas commencé' => [['starts_at' => now()->addDay()], 'pas encore valable'],
            'achat minimum' => [['min_subtotal' => 20000], 'à partir de 20 000 FCFA'],
        ];
    }

    public function test_unknown_code_is_refused(): void
    {
        $this->expectException(PromoException::class);
        app(PromoService::class)->evaluate('NOPE', $this->client, 10000, 1000);
    }

    public function test_order_with_a_code_stores_the_discount_and_charges_the_discounted_total(): void
    {
        $this->promo();

        // Faux prix et fausse réduction envoyés par le navigateur : ignorés.
        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'bienvenue10', 'discount' => 99999, 'total_price' => 1]))
            ->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertEquals(10000, $order->subtotal);
        $this->assertEquals(1000, $order->discount);
        $this->assertSame('BIENVENUE10', $order->promo_code);
        $this->assertEquals(10000, $order->total_price); // 10 000 + 1 000 − 1 000
        $this->assertSame(1, PromoRedemption::count());
    }

    public function test_cash_given_must_cover_the_discounted_total(): void
    {
        $this->promo();

        // Total sans code : 11 000 ; avec code : 10 000.
        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'BIENVENUE10', 'cash_given' => 10500]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    public function test_a_code_is_used_once_per_client_but_freed_when_the_order_is_cancelled(): void
    {
        $this->promo();

        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'BIENVENUE10']))->assertSessionHasNoErrors();
        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'BIENVENUE10']))
            ->assertSessionHasErrors(['promo_code' => 'Vous avez déjà utilisé ce code promo.']);
        $this->assertSame(1, Order::count());

        app(OrderWorkflow::class)->transition(Order::sole(), OrderStatus::Cancelled, $this->client);

        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'BIENVENUE10']))->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
    }

    public function test_first_order_only(): void
    {
        $this->promo(['first_order_only' => true]);
        $this->makeOrder(OrderStatus::Delivered);

        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'BIENVENUE10']))
            ->assertSessionHasErrors(['promo_code' => 'Ce code est réservé à une première commande.']);
    }

    public function test_apply_endpoint_returns_the_discount_or_the_reason(): void
    {
        $this->promo();
        $items = [['product_id' => $this->productId, 'quantity' => 2]];

        $this->actingAs($this->client)->postJson("/checkout/{$this->store->id}/promo", ['promo_code' => 'bienvenue10', 'items' => $items])
            ->assertOk()->assertJson(['code' => 'BIENVENUE10', 'discount' => 1000, 'total' => 10000, 'label' => '10 % de réduction']);

        $this->actingAs($this->client)->postJson("/checkout/{$this->store->id}/promo", ['promo_code' => 'FAUX', 'items' => $items])
            ->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }

    public function test_discount_is_paid_by_gogab_not_by_the_store_or_the_courier(): void
    {
        $this->promo();
        $this->actingAs($this->client)->post('/orders', $this->payload(['promo_code' => 'BIENVENUE10', 'cash_given' => 10000]));
        $order = Order::sole();
        $order->update(['status' => OrderStatus::Arrived, 'delivery_id' => $this->courier->id]);

        app(OrderWorkflow::class)->transition($order->fresh(), OrderStatus::Delivered, $this->courier, cashCollected: true);

        $wallet = app(WalletService::class);
        // Entreprise : 10 000 − 15 % comme sans code. Livreur : gain 1 000, encaissé 10 000.
        $this->assertSame(8500, $wallet->balance($this->owner));
        $this->assertSame(-9000, $wallet->balance($this->courier));
    }

    public function test_admin_manages_promo_codes_and_others_cannot(): void
    {
        $data = ['code' => ' ete 25 ', 'type' => 'percent', 'value' => 15, 'max_uses_per_user' => 1, 'ends_at' => '2026-12-31', 'is_active' => true];

        $this->actingAs($this->admin)->post('/admin/promo-codes', $data)->assertSessionHasNoErrors();
        $promo = PromoCode::sole();
        $this->assertSame('ETE25', $promo->code);
        $this->assertSame('2026-12-31', $promo->ends_at->setTimezone('Africa/Libreville')->format('Y-m-d'));

        $this->actingAs($this->admin)->post('/admin/promo-codes', $data)->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/admin/promo-codes', [...$data, 'code' => 'TROP', 'value' => 150])->assertSessionHasErrors('value');

        $this->actingAs($this->admin)->patch("/admin/promo-codes/{$promo->id}/toggle");
        $this->assertFalse($promo->fresh()->is_active);

        $this->actingAs($this->admin)->get('/admin/promo-codes')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/PromoCodes/Index')->has('codes', 1)->where('codes.0.uses', 0));

        foreach ([$this->owner, $this->courier, $this->client] as $user) {
            $this->actingAs($user)->post('/admin/promo-codes', [...$data, 'code' => 'PIRATE'])->assertRedirect(route($user->homeRoute()));
        }
        $this->assertSame(1, PromoCode::count());
    }
}
