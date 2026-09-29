<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
    }

    public function test_client_sees_only_his_orders(): void
    {
        $order = $this->makeOrder();

        $this->assertTrue($this->client->can('view', $order));
        $this->assertFalse(User::factory()->create(['role' => 'client'])->can('view', $order));
    }

    public function test_business_sees_the_orders_of_its_store(): void
    {
        $order = $this->makeOrder();
        $otherOwner = User::factory()->create(['role' => 'business']);
        Store::factory()->create(['owner_id' => $otherOwner->id]);

        $this->assertTrue($this->owner->can('view', $order));
        $this->assertFalse($otherOwner->can('view', $order));
    }

    public function test_courier_sees_his_deliveries_and_the_announcements_of_his_zone(): void
    {
        $announcement = $this->makeOrder(OrderStatus::SearchingCourier);
        $this->assertTrue($this->courier->can('view', $announcement));
        $this->assertFalse($this->farCourier->can('view', $announcement));

        // Pas encore annoncée : invisible pour les livreurs.
        $this->assertFalse($this->courier->can('view', $this->makeOrder(OrderStatus::Preparing)));

        $mine = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $this->assertTrue($this->courier->can('view', $mine));
        $colleague = $this->makeCourier('Collègue', Neighborhood::firstWhere('name', 'Louis'));
        $this->assertFalse($colleague->can('view', $mine));
    }

    public function test_admin_sees_every_order(): void
    {
        $this->assertTrue($this->admin->can('view', $this->makeOrder()));
        $this->assertTrue($this->admin->can('view', $this->makeOrder(OrderStatus::Delivered)));
    }
}
