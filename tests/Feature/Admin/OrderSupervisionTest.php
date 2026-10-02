<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Report;
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
 * Supervision admin : tableau de bord, liste filtrée des commandes, détail, annulation,
 * relance des commandes bloquées, export CSV.
 */
class OrderSupervisionTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        // Vendredi 2 octobre 2026, 12 h à Libreville (11 h UTC).
        $this->freeze('2026-10-02 11:00:00');
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

    private function orderAt(string $utc, OrderStatus $status = OrderStatus::Pending, array $attributes = []): Order
    {
        $order = $this->makeOrder($status, $attributes);
        $order->forceFill(['created_at' => $utc, 'updated_at' => $attributes['updated_at'] ?? $utc])->saveQuietly();

        return $order;
    }

    // --- Tableau de bord ---

    public function test_dashboard_figures_match_the_data(): void
    {
        // Comptes à valider, signalement ouvert.
        User::factory()->pending()->create(['role' => 'client']);
        $this->makeCourier('En attente', Neighborhood::firstWhere('name', 'Glass'), state: 'pending');
        Report::create(['reporter_id' => $this->client->id, 'reported_user_id' => $this->courier->id, 'reason' => 'retard', 'description' => 'Une heure de retard.', 'status' => 'open']);
        Report::create(['reporter_id' => $this->client->id, 'reported_user_id' => $this->owner->id, 'reason' => 'autre', 'description' => 'Déjà traité.', 'status' => 'resolved']);

        // Livreurs : courier et farCourier disponibles ; un indisponible ne compte pas.
        $this->makeCourier('Indispo', Neighborhood::firstWhere('name', 'Louis'), available: false);

        // Commerces : celui de l'entreprise (visible) + un inactif.
        Store::factory()->create(['is_active' => false]);

        // Commandes du jour (heure de Libreville) : 00 h 30 compte, 23 h 30 la veille non.
        $this->orderAt('2026-10-01 23:30:00');                                       // 2 oct. 00 h 30 : aujourd'hui
        $this->orderAt('2026-10-01 22:30:00');                                       // 1er oct. 23 h 30 : hier
        $this->orderAt('2026-10-02 08:00:00', OrderStatus::Delivered, ['total_price' => 6000]);
        $this->orderAt('2026-09-30 10:00:00', OrderStatus::Delivered, ['total_price' => 9000, 'updated_at' => '2026-10-02 09:00:00']); // livrée aujourd'hui
        $this->orderAt('2026-09-29 10:00:00', OrderStatus::Delivered, ['total_price' => 4000, 'updated_at' => '2026-09-29 12:00:00']); // livrée avant

        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.pending_accounts', 2)
                ->where('stats.open_reports', 1)
                ->where('stats.orders_today', 2)
                ->where('stats.revenue_today', 15000)
                ->where('stats.available_couriers', 2)
                ->where('stats.active_stores', 1)
                ->where('stats.stuck_orders', 0)
                ->where('stats.total_orders', 5)
                ->has('week', 7)
                ->where('week.6.date', '2026-10-02')
                ->where('week.6.orders', 2)
                ->where('week.6.delivered', 1)
                ->where('week.5.date', '2026-10-01')
                ->where('week.5.orders', 1)
                ->where('week.4.orders', 1) // 30 sept.
                ->where('week.3.orders', 1) // 29 sept.
                ->where('week.2.orders', 0)
                ->where('week.0.date', '2026-09-26')
                ->has('byStatus', 10)
                ->has('events'));
    }

    // --- Liste et filtres ---

    public function test_orders_can_be_filtered_by_status_store_and_date(): void
    {
        $other = Store::factory()->create(['name' => 'Autre commerce']);
        $pending = $this->orderAt('2026-10-02 09:00:00');
        $delivered = $this->orderAt('2026-10-01 09:00:00', OrderStatus::Delivered);
        $elsewhere = $this->orderAt('2026-09-28 09:00:00', OrderStatus::Pending, ['store_id' => $other->id]);

        $ids = fn (string $query) => $this->actingAs($this->admin)->get('/admin/orders'.$query)->viewData('page')['props']['orders']['data'];

        $this->assertCount(3, $ids(''));
        $this->assertSame([$delivered->id], array_column($ids('?status=livree'), 'id'));
        $this->assertSame([$elsewhere->id], array_column($ids("?store={$other->id}"), 'id'));
        $this->assertSame([$pending->id, $delivered->id], array_column($ids('?from=2026-10-01'), 'id'));
        $this->assertSame([$delivered->id], array_column($ids('?from=2026-10-01&to=2026-10-01'), 'id'));
        $this->assertSame([$pending->id], array_column($ids("?status=en_attente&store={$this->store->id}"), 'id'));

        $this->actingAs($this->admin)->get('/admin/orders?status=inconnu')->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->get('/admin/orders?from=2026-10-02&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_search_by_reference(): void
    {
        $order = $this->makeOrder();
        $this->makeOrder();
        $ref = $order->reference; // GG-00000X

        foreach ([$ref, strtolower($ref), ltrim(substr($ref, 3), '0'), substr($ref, 3), ' '.$ref.' '] as $search) {
            $this->actingAs($this->admin)
                ->get('/admin/orders?q='.urlencode($search))
                ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $order->id));
        }

        $this->actingAs($this->admin)->get('/admin/orders?q=poulet')->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->actingAs($this->admin)->get('/admin/orders?q=GG-999999')->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
    }

    public function test_orders_stuck_in_courier_search_are_highlighted(): void
    {
        $stuck = $this->makeOrder(OrderStatus::SearchingCourier, ['announced_at' => now()->subMinutes(20)]);
        $recent = $this->makeOrder(OrderStatus::SearchingCourier, ['announced_at' => now()->subMinutes(5)]);

        $this->actingAs($this->admin)
            ->get('/admin/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stuckCount', 1)
                ->where('orders.data', fn ($orders) => collect($orders)->firstWhere('id', $stuck->id)['is_stuck'] === true
                    && collect($orders)->firstWhere('id', $stuck->id)['searching_minutes'] === 20
                    && collect($orders)->firstWhere('id', $recent->id)['is_stuck'] === false));

        $this->actingAs($this->admin)
            ->get('/admin/orders?status=stuck')
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $stuck->id));

        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('stats.stuck_orders', 1));
    }

    // --- Détail et actions ---

    public function test_detail_shows_the_full_history_and_the_parties(): void
    {
        $order = $this->makeOrder();
        $workflow = app(OrderWorkflow::class);
        $order->recordStatus(OrderStatus::Pending, $this->client);
        $workflow->transition($order, OrderStatus::Refused, $this->owner, 'Rupture de poulet');

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.number', $order->reference)
                ->has('order.history', 2)
                ->where('order.history.0.label', 'En attente')
                ->where('order.history.0.author', 'Marie Ndong')
                ->where('order.history.1.label', 'Refusée')
                ->where('order.history.1.author', 'Maman Ngoye')
                ->where('order.history.1.author_role', 'Entreprise')
                ->where('order.history.1.note', 'Rupture de poulet')
                ->where('parties.client.name', 'Marie Ndong')
                ->where('parties.store.name', 'Chez Maman Ngoye')
                ->where('parties.store.owner.id', $this->owner->id)
                ->where('parties.courier', null)
                ->where('can.cancel', false));
    }

    public function test_admin_cancellation_requires_a_reason_and_notifies_the_parties(): void
    {
        $order = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('can.cancel', true));

        $this->actingAs($this->admin)
            ->from(route('admin.orders.show', $order))
            ->put(route('orders.status.update', $order), ['status' => 'annulee'])
            ->assertSessionHas('error');
        $this->assertSame(OrderStatus::Delivering, $order->fresh()->status);

        $this->actingAs($this->admin)
            ->from(route('admin.orders.show', $order))
            ->put(route('orders.status.update', $order), ['status' => 'annulee', 'note' => 'Client injoignable'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Client injoignable', $order->cancel_reason);
        foreach ([$this->client, $this->owner, $this->courier] as $party) {
            $this->assertContains('Commande annulée', $this->notificationTitles($party));
        }
        $this->assertSame([], $this->notificationTitles($this->admin));
    }

    public function test_admin_can_relaunch_an_order_waiting_for_a_courier(): void
    {
        $order = $this->makeOrder(OrderStatus::SearchingCourier, ['announced_at' => now()->subMinutes(30), 'announcement_count' => 1]);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('can.relaunch', true)->where('order.is_stuck', true)->where('couriersInZone', 1));

        $this->actingAs($this->admin)->post(route('admin.orders.relaunch', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(2, $order->announcement_count);
        $this->assertTrue($order->announced_at->equalTo(now()));
        $this->assertSame(['Course toujours disponible'], $this->notificationTitles($this->courier));
        $this->assertSame([], $this->notificationTitles($this->farCourier));

        // Plus en recherche : relance refusée.
        $delivered = $this->makeOrder(OrderStatus::Delivered);
        $this->actingAs($this->admin)->from('/admin/orders')->post(route('admin.orders.relaunch', $delivered))->assertSessionHas('error');
    }

    // --- Export CSV ---

    public function test_csv_export_respects_the_filters(): void
    {
        $other = Store::factory()->create(['name' => 'Autre commerce']);
        $delivered = $this->orderAt('2026-10-01 09:00:00', OrderStatus::Delivered, ['subtotal' => 9000, 'delivery_fee' => 1000, 'total_price' => 10000]);
        $this->orderAt('2026-10-02 09:00:00');
        $this->orderAt('2026-10-01 10:00:00', OrderStatus::Delivered, ['store_id' => $other->id]);

        $response = $this->actingAs($this->admin)->get("/admin/orders/export?status=livree&store={$this->store->id}");
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=commandes-gogab-', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertCount(2, $lines); // en-tête + 1 commande
        $this->assertStringStartsWith('Référence;Date;Statut;Commerce', $lines[0]);
        $this->assertSame(
            [$delivered->reference, '01/10/2026 10:00', 'Livrée', 'Chez Maman Ngoye', 'Marie Ndong', '', 'Glass', 'Paiement à la livraison', '9000', '1000', '10000', ''],
            str_getcsv($lines[1], ';'),
        );

        // Sans filtre : toutes les commandes.
        $all = $this->actingAs($this->admin)->get('/admin/orders/export')->streamedContent();
        $this->assertCount(4, array_filter(explode("\n", trim($all))));
    }

    public function test_non_admins_cannot_reach_order_supervision(): void
    {
        $order = $this->makeOrder();

        foreach ([$this->client, $this->owner, $this->courier] as $user) {
            $this->actingAs($user)->get('/admin/orders')->assertRedirect();
            $this->actingAs($user)->get('/admin/orders/export')->assertRedirect();
            $this->actingAs($user)->get(route('admin.orders.show', $order))->assertRedirect();
            $this->actingAs($user)->post(route('admin.orders.relaunch', $order))->assertRedirect();
        }
    }
}
