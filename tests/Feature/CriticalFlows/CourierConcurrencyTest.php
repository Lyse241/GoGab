<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\OrderStatus;
use App\Exceptions\OrderTransitionException;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Parcours critique 5 — concurrence : deux livreurs acceptent la même course, un seul l'obtient.
 *
 * PHPUnit exécute les requêtes l'une après l'autre ; la course réelle est reproduite en gardant
 * en mémoire la commande telle que le second livreur l'a lue (« encore libre ») : c'est la
 * relecture verrouillée et la mise à jour conditionnelle d'OrderWorkflow qui doivent l'arrêter.
 */
class CourierConcurrencyTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $this->order = $this->makeOrder(OrderStatus::SearchingCourier, ['announced_at' => now()]);
    }

    public function test_two_couriers_accept_the_same_course_and_only_one_gets_it(): void
    {
        $second = $this->makeCourier('Second livreur', Neighborhood::firstWhere('name', 'Louis'));

        // Les deux ont la même offre à l'écran.
        $seenByFirst = Order::find($this->order->id);
        $seenBySecond = Order::find($this->order->id);
        $this->assertNull($seenBySecond->delivery_id);

        app(OrderWorkflow::class)->transition($seenByFirst, OrderStatus::CourierAssigned, $this->courier);

        try {
            app(OrderWorkflow::class)->transition($seenBySecond, OrderStatus::CourierAssigned, $second);
            $this->fail('Le second livreur ne doit pas obtenir la course.');
        } catch (OrderTransitionException $exception) {
            $this->assertSame(OrderWorkflow::TAKEN_MESSAGE, $exception->getMessage());
        }

        $this->assertOneWinner($this->courier);
    }

    public function test_the_same_race_through_http_shows_the_message_to_the_second_courier(): void
    {
        $second = $this->makeCourier('Second livreur', Neighborhood::firstWhere('name', 'Louis'));

        $this->actingAs($second)->post(route('delivery.orders.accept', $this->order))->assertRedirect(route('delivery.current'));
        $this->actingAs($this->courier)
            ->from('/delivery/offers')
            ->post(route('delivery.orders.accept', $this->order))
            ->assertRedirect('/delivery/offers')
            ->assertSessionHas('error', OrderWorkflow::TAKEN_MESSAGE);

        $this->assertOneWinner($second);
    }

    public function test_a_double_click_by_the_same_courier_does_not_take_the_course_twice(): void
    {
        $this->actingAs($this->courier)->post(route('delivery.orders.accept', $this->order))->assertSessionHas('success');
        $this->actingAs($this->courier)->from('/delivery/offers')->post(route('delivery.orders.accept', $this->order))->assertSessionHas('error');

        $this->assertOneWinner($this->courier);
    }

    public function test_a_courier_cannot_take_two_courses_at_the_same_time(): void
    {
        $other = $this->makeOrder(OrderStatus::SearchingCourier, ['announced_at' => now()]);

        app(OrderWorkflow::class)->transition($this->order, OrderStatus::CourierAssigned, $this->courier);

        $this->expectExceptionMessage(OrderWorkflow::BUSY_MESSAGE);
        app(OrderWorkflow::class)->transition($other, OrderStatus::CourierAssigned, $this->courier);
    }

    private function assertOneWinner($winner): void
    {
        $order = $this->order->fresh();
        $this->assertSame($winner->id, $order->delivery_id);
        $this->assertSame(OrderStatus::CourierAssigned, $order->status);
        $this->assertSame(1, $order->statusHistories()->where('status', OrderStatus::CourierAssigned)->count());
        // Client et entreprise ne sont prévenus qu'une fois.
        $this->assertSame(1, $this->client->notifications()->where('data->title', 'Livreur trouvé')->count());
        $this->assertSame(1, $this->owner->notifications()->where('data->title', 'Livreur assigné')->count());
    }
}
