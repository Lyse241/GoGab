<?php

namespace Tests\Feature\Delivery;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Course en cours (/delivery/current) : étapes, notifications, encaissement cash ;
 * historique et gains (/delivery/history).
 */
class CurrentDeliveryTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $this->store->update(['phone' => '011 22 33 44', 'address_landmarks' => 'Face au marché de Louis']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function freeze(string $time): void
    {
        Carbon::setTestNow($time);
        CarbonImmutable::setTestNow($time);
    }

    private function assigned(array $attributes = []): Order
    {
        return $this->makeOrder(OrderStatus::CourierAssigned, [
            'delivery_id' => $this->courier->id,
            'delivery_fee' => 1000,
            'total_price' => 10000,
            'cash_given' => 15000,
            'client_note' => 'Sonnez deux fois',
            ...$attributes,
        ]);
    }

    private function advance(Order $order, string $status, array $extra = [])
    {
        return $this->actingAs($this->courier)
            ->from('/delivery/current')
            ->put(route('orders.status.update', $order), ['status' => $status, ...$extra]);
    }

    public function test_the_screen_shows_store_client_items_and_cash_to_collect(): void
    {
        $order = $this->assigned();

        $this->actingAs($this->courier)
            ->get('/delivery/current')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Current')
                ->where('order.id', $order->id)
                ->where('order.status', 'livreur_assigne')
                ->where('order.next_status', 'en_livraison')
                ->where('order.store', [
                    'name' => 'Chez Maman Ngoye',
                    'neighborhood' => 'Louis',
                    'address_landmarks' => 'Face au marché de Louis',
                    'phone' => '011 22 33 44',
                ])
                ->where('order.client', [
                    'first_name' => 'Marie',
                    'phone' => '066 20 00 01',
                    'neighborhood' => 'Glass',
                    'address_landmarks' => 'Près de la pharmacie, portail bleu',
                ])
                ->where('order.item_count', 2)
                ->where('order.items.0.name', 'Poulet nyembwe')
                ->where('order.client_note', 'Sonnez deux fois')
                ->where('order.is_cash', true)
                ->where('order.total_price', '10000.00')
                ->where('order.cash_given', '15000.00')
                ->where('order.change_due', 5000)
                ->where('order.earning', '1000.00'));
    }

    public function test_without_an_active_course_the_screen_is_empty(): void
    {
        $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);

        $this->actingAs($this->courier)
            ->get('/delivery/current')
            ->assertInertia(fn (Assert $page) => $page->component('Delivery/Current')->where('order', null));
    }

    public function test_each_button_moves_to_the_right_status_and_notifies_the_right_people(): void
    {
        $order = $this->assigned();

        // Récupération → client et entreprise.
        $this->advance($order, 'en_livraison')->assertRedirect('/delivery/current')->assertSessionHas('success');
        $this->assertSame(OrderStatus::Delivering, $order->fresh()->status);
        $this->assertSame(['Commande récupérée'], $this->notificationTitles($this->client));
        $this->assertSame(['Commande récupérée'], $this->notificationTitles($this->owner));

        // Arrivée → client seulement.
        $this->advance($order, 'arrive')->assertSessionHas('success');
        $this->assertSame(OrderStatus::Arrived, $order->fresh()->status);
        $this->assertContains('Votre livreur est arrivé', $this->notificationTitles($this->client));
        $this->assertNotContains('Votre livreur est arrivé', $this->notificationTitles($this->owner));
        $this->assertCount(1, $this->owner->fresh()->notifications);

        // Remise → client et entreprise.
        $this->advance($order, 'livree', ['cash_collected' => true])->assertSessionHas('success');
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertContains('Commande livrée', $this->notificationTitles($this->client));
        $this->assertContains('Commande livrée', $this->notificationTitles($this->owner));

        // Plus de course : écran vide.
        $this->actingAs($this->courier)->get('/delivery/current')->assertInertia(fn (Assert $page) => $page->where('order', null));
    }

    public function test_the_client_timeline_follows_each_step(): void
    {
        $order = $this->assigned();
        $expected = ['en_livraison' => 'En route', 'arrive' => 'Livreur arrivé', 'livree' => 'Livrée'];

        foreach ($expected as $status => $label) {
            $this->advance($order, $status, ['cash_collected' => true]);

            $this->actingAs($this->client)
                ->get(route('orders.show', $order))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('order.status', $status)
                    ->where('order.timeline', fn ($timeline) => collect($timeline)->contains(fn ($step) => $step['label'] === $label && in_array($step['state'], ['current', 'done'], true))));
        }
    }

    public function test_a_cash_order_requires_the_collected_amount_to_be_confirmed(): void
    {
        $order = $this->assigned(['status' => OrderStatus::Arrived]);

        $this->advance($order, 'livree')->assertSessionHas('error', OrderWorkflow::CASH_MESSAGE);
        $this->advance($order, 'livree', ['cash_collected' => false])->assertSessionHas('error', OrderWorkflow::CASH_MESSAGE);
        $this->assertSame(OrderStatus::Arrived, $order->fresh()->status);
        $this->assertNull($order->fresh()->cash_collected_at);

        $this->freeze('2026-10-02 18:30:00');
        $this->advance($order, 'livree', ['cash_collected' => true])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertTrue($order->cash_collected_at->equalTo(now()));
    }

    public function test_a_mobile_money_order_needs_no_cash_confirmation(): void
    {
        $order = $this->assigned(['status' => OrderStatus::Arrived, 'payment_method' => 'airtel_money', 'cash_given' => null]);

        $this->actingAs($this->courier)->get('/delivery/current')->assertInertia(fn (Assert $page) => $page
            ->where('order.is_cash', false)
            ->where('order.change_due', null));

        $this->advance($order, 'livree')->assertSessionHas('success');
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertNull($order->fresh()->cash_collected_at);
    }

    public function test_history_lists_delivered_courses_and_earnings_of_day_week_and_month(): void
    {
        // Vendredi 2 octobre 2026, 12 h à Libreville (UTC+1) ; semaine du lundi 28 septembre.
        $this->freeze('2026-10-02 11:00:00');

        $deliveredAt = fn (string $utc, int $fee, array $attributes = []) => tap(
            $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id, 'delivery_fee' => $fee, ...$attributes]),
            fn (Order $order) => $order->forceFill(['updated_at' => $utc])->saveQuietly(),
        );

        $today = $deliveredAt('2026-10-02 09:00:00', 1000);
        $deliveredAt('2026-10-01 23:30:00', 1500); // 2 oct. 00 h 30 à Libreville : aujourd'hui
        $deliveredAt('2026-09-29 10:00:00', 2000); // mardi : cette semaine, mois précédent
        $deliveredAt('2026-09-27 10:00:00', 4000); // dimanche dernier : ni la semaine ni le mois
        $deliveredAt('2026-10-02 08:00:00', 9000, ['delivery_id' => $this->farCourier->id]); // autre livreur
        $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id, 'delivery_fee' => 1000]); // pas terminée

        $this->actingAs($this->courier)
            ->get('/delivery/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/History')
                ->where('earnings', ['today' => 2500, 'week' => 4500, 'month' => 2500])
                ->has('deliveries.data', 4)
                ->where('deliveries.data.0.id', $today->id)
                ->where('deliveries.data.0.date', '02/10/2026')
                ->where('deliveries.data.0.time', '10h00')
                ->where('deliveries.data.0.store', 'Chez Maman Ngoye')
                ->where('deliveries.data.0.earning', '1000.00'));

        // Accueil : mêmes chiffres du jour.
        $this->actingAs($this->courier)
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page->where('stats', ['deliveries' => 2, 'earnings' => 2500]));
    }
}
