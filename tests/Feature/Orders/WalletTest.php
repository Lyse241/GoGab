<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\OrderTransitionException;
use App\Models\WalletEntry;
use App\Services\OrderWorkflow;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $this->wallet = app(WalletService::class);
        config(['gogab.commission_rate' => 15]);
    }

    private function deliver(array $attributes = [])
    {
        $order = $this->makeOrder(OrderStatus::Arrived, [
            'delivery_id' => $this->courier->id,
            'subtotal' => 9000,
            'delivery_fee' => 1000,
            'total_price' => 10000,
            ...$attributes,
        ]);

        return app(OrderWorkflow::class)->transition($order, OrderStatus::Delivered, $this->courier, cashCollected: true);
    }

    public function test_delivery_credits_the_business_minus_commission_and_the_courier_minus_cash(): void
    {
        $this->deliver();

        // Entreprise : 9 000 − 15 % (1 350) = 7 650 dus par Gogab.
        $this->assertSame(7650, $this->wallet->balance($this->owner));
        // Livreur : gain 1 000, encaissé 10 000 → il doit 9 000 à Gogab.
        $this->assertSame(-9000, $this->wallet->balance($this->courier));
        $this->assertSame(4, WalletEntry::count());
    }

    public function test_commission_rate_comes_from_the_config(): void
    {
        config(['gogab.commission_rate' => 10]);

        $this->deliver();

        $this->assertSame(8100, $this->wallet->balance($this->owner));
    }

    public function test_settling_a_delivery_twice_does_not_duplicate_entries(): void
    {
        $order = $this->deliver();

        $this->wallet->settleDelivery($order);

        $this->assertSame(4, WalletEntry::count());
        $this->assertSame(7650, $this->wallet->balance($this->owner));
    }

    public function test_cancelled_and_refused_orders_create_no_entry(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);
        app(OrderWorkflow::class)->transition($order, OrderStatus::Refused, $this->owner, 'Rupture de stock');

        $this->assertSame(0, WalletEntry::count());
    }

    public function test_admin_settlements_reduce_the_balance_and_cannot_exceed_it(): void
    {
        $this->deliver();

        $this->wallet->settle($this->admin, $this->owner, 5000, 'Virement Airtel');
        $this->assertSame(2650, $this->wallet->balance($this->owner));

        // Le livreur remet 9 000 FCFA d'espèces : son solde revient à zéro.
        $this->wallet->settle($this->admin, $this->courier, 9000);
        $this->assertSame(0, $this->wallet->balance($this->courier));

        $this->assertSame(WalletEntryType::Settlement, WalletEntry::latest('id')->first()->type);

        $this->expectException(OrderTransitionException::class);
        $this->wallet->settle($this->admin, $this->owner, 2651);
    }

    public function test_nothing_to_settle_on_a_balanced_account(): void
    {
        $this->expectException(OrderTransitionException::class);

        $this->wallet->settle($this->admin, $this->owner, 100);
    }

    public function test_admin_sees_balances_and_records_a_settlement_over_http(): void
    {
        $this->deliver();

        $this->actingAs($this->admin)
            ->get('/admin/finances')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Finances/Index')
                ->has('accounts', 2)
                ->where('totals.commission', 1350)
                ->where('totals.owed_to_users', 7650)
                ->where('totals.owed_to_gogab', 9000));

        $this->actingAs($this->admin)
            ->post("/admin/finances/{$this->owner->id}/settle", ['amount' => 7650])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $this->wallet->balance($this->owner));

        $this->actingAs($this->admin)
            ->post("/admin/finances/{$this->owner->id}/settle", ['amount' => 10])
            ->assertSessionHasErrors('amount');
    }

    public function test_business_and_courier_see_only_their_own_balance(): void
    {
        $this->deliver();

        $this->actingAs($this->owner)->get('/business/wallet')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Wallet/Index')->where('balance', 7650)->has('entries.data', 2));

        $this->actingAs($this->courier)->get('/delivery/wallet')->assertOk()
            ->assertInertia(fn ($page) => $page->where('balance', -9000)->has('entries.data', 2));

        // Chacun reste dans son espace.
        $this->actingAs($this->client)->get('/business/wallet')->assertRedirect(route('home'));
        $this->actingAs($this->owner)->get('/delivery/wallet')->assertRedirect(route('business.dashboard'));
    }

    public function test_only_admins_reach_the_finances(): void
    {
        foreach ([$this->owner, $this->courier, $this->client] as $user) {
            // Le middleware de rôle renvoie chacun vers son propre espace.
            $this->actingAs($user)->get('/admin/finances')->assertRedirect(route($user->homeRoute()));
            $this->actingAs($user)->post("/admin/finances/{$this->owner->id}/settle", ['amount' => 10])->assertRedirect(route($user->homeRoute()));
        }
        $this->assertSame(0, \App\Models\WalletEntry::count());
    }
}
