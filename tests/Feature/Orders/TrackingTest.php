<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    // Commerce à Louis, adresse du client à Glass, livreur à mi-chemin.
    private const STORE = [0.4105973, 9.4328279];

    private const HOME = [0.3771157, 9.4532994];

    private const COURIER = [0.4000000, 9.4400000];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        Neighborhood::where('name', 'Louis')->update(['latitude' => self::STORE[0], 'longitude' => self::STORE[1]]);
        Neighborhood::where('name', 'Glass')->update(['latitude' => self::HOME[0], 'longitude' => self::HOME[1]]);
    }

    private function active(OrderStatus $status = OrderStatus::Delivering): Order
    {
        return $this->makeOrder($status, ['delivery_id' => $this->courier->id]);
    }

    private function share(Order $order, array $position = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->courier)->postJson("/delivery/orders/{$order->id}/location", [
            'latitude' => self::COURIER[0], 'longitude' => self::COURIER[1], 'accuracy' => 12.4, 'heading' => 270, ...$position,
        ]);
    }

    public function test_the_courier_shares_his_position_during_the_delivery(): void
    {
        $order = $this->active();

        $this->share($order)->assertOk()->assertJson(['shared' => true]);

        $profile = $this->courier->deliveryProfile->fresh();
        $this->assertEqualsWithDelta(self::COURIER[0], (float) $profile->latitude, 0.000001);
        $this->assertSame(12, $profile->accuracy);
        $this->assertSame(270, $profile->heading);
        $this->assertNotNull($profile->located_at);
    }

    public function test_only_the_assigned_courier_of_an_active_order_can_share(): void
    {
        $other = $this->makeCourier('Paul Autre', Neighborhood::firstWhere('name', 'Glass'));
        $order = $this->active();

        $this->share($order, as: $other)->assertStatus(422);
        $this->assertNull($other->deliveryProfile->fresh()->latitude);

        // Course pas encore prise, ou déjà terminée : pas de partage.
        foreach ([OrderStatus::Preparing, OrderStatus::SearchingCourier, OrderStatus::Delivered, OrderStatus::Cancelled] as $status) {
            $closed = $this->makeOrder($status, ['delivery_id' => $status === OrderStatus::Preparing || $status === OrderStatus::SearchingCourier ? null : $this->courier->id]);
            $this->share($closed)->assertStatus(422);
        }
        $this->assertNull($this->courier->deliveryProfile->fresh()->latitude);

        // Un client ou une entreprise ne publie pas de position.
        $this->share($order, as: $this->client)->assertRedirect(route('home'));
    }

    public function test_invalid_positions_are_refused(): void
    {
        $order = $this->active();

        $this->share($order, ['latitude' => 48.85, 'longitude' => 2.35])->assertStatus(422); // Paris
        $this->share($order, ['latitude' => 'abc'])->assertStatus(422);
        $this->share($order, ['latitude' => 120])->assertStatus(422);
        $this->actingAs($this->courier)->postJson("/delivery/orders/{$order->id}/location", [])->assertStatus(422);
        $this->assertNull($this->courier->deliveryProfile->fresh()->latitude);
    }

    public function test_the_client_sees_the_courier_the_stages_and_an_estimated_arrival(): void
    {
        $order = $this->active(OrderStatus::Delivering);
        $this->share($order);
        $service = app(TrackingService::class);

        $json = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->assertOk()->json();

        $this->assertTrue($json['active']);
        $this->assertSame('to_client', $json['stage']);
        $this->assertEqualsWithDelta(self::COURIER[0], $json['courier']['lat'], 0.00001);
        $this->assertTrue($json['courier']['fresh']);
        $this->assertSame('Chez Maman Ngoye', $json['store']['name']);
        $this->assertSame('Quartier Glass', $json['destination']['label']);

        $km = $service->distanceKm(self::COURIER[0], self::COURIER[1], self::HOME[0], self::HOME[1]);
        $this->assertSame(round($km, 1), $json['distance_km']);
        $this->assertSame((int) ceil($km / TrackingService::AVERAGE_SPEED_KMH * 60), $json['eta_minutes']);
        $this->assertStringContainsString('arrive chez vous', $json['message']);

        // Avant la récupération : le livreur va vers le commerce.
        $order->update(['status' => OrderStatus::CourierAssigned]);
        $json = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertSame('to_store', $json['stage']);
        $kmStore = $service->distanceKm(self::COURIER[0], self::COURIER[1], self::STORE[0], self::STORE[1]);
        $this->assertSame(round($kmStore, 1), $json['distance_km']);
        $this->assertStringContainsString('se rend au commerce', $json['message']);

        // Arrivé : plus d'estimation.
        $order->update(['status' => OrderStatus::Arrived]);
        $json = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertSame('arrived', $json['stage']);
        $this->assertNull($json['eta_minutes']);
        $this->assertSame('Votre livreur est arrivé.', $json['message']);
    }

    public function test_an_old_position_is_flagged_and_gives_no_estimate(): void
    {
        $order = $this->active();
        $this->share($order);
        $this->courier->deliveryProfile->forceFill(['located_at' => Carbon::now()->subMinutes(5)])->save();

        $json = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->json();

        $this->assertFalse($json['courier']['fresh']);
        $this->assertGreaterThanOrEqual(300, $json['courier']['seconds_ago']);
        $this->assertNull($json['eta_minutes']);
        $this->assertStringContainsString('indisponible', $json['message']);
    }

    public function test_no_position_is_exposed_before_the_courier_is_assigned_or_after_the_delivery(): void
    {
        $waiting = $this->makeOrder(OrderStatus::Preparing);
        $json = $this->actingAs($this->client)->getJson("/orders/{$waiting->id}/tracking")->json();
        $this->assertFalse($json['active']);
        $this->assertNull($json['courier']);

        // Même si le profil du livreur contient une ancienne position, une commande terminée ne l'expose pas.
        $this->courier->deliveryProfile->forceFill(['latitude' => 0.4, 'longitude' => 9.44, 'located_at' => now()])->save();
        $done = $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);
        $json = $this->actingAs($this->client)->getJson("/orders/{$done->id}/tracking")->json();
        $this->assertFalse($json['active']);
        $this->assertNull($json['courier']);
    }

    public function test_only_the_people_of_the_order_can_follow_it(): void
    {
        $order = $this->active();
        $this->share($order);
        $stranger = User::factory()->create(['role' => 'client']);
        $otherStore = User::factory()->create(['role' => 'business']);

        $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->assertOk();
        $this->actingAs($this->owner)->getJson("/orders/{$order->id}/tracking")->assertOk();
        $this->actingAs($this->admin)->getJson("/orders/{$order->id}/tracking")->assertOk();
        $this->actingAs($stranger)->getJson("/orders/{$order->id}/tracking")->assertForbidden();
        $this->actingAs($otherStore)->getJson("/orders/{$order->id}/tracking")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson("/orders/{$order->id}/tracking")->assertUnauthorized();
    }

    public function test_the_position_is_erased_when_the_delivery_ends_or_is_cancelled(): void
    {
        $order = $this->active(OrderStatus::Arrived);
        $this->share($order);
        $this->assertNotNull($this->courier->deliveryProfile->fresh()->latitude);

        app(OrderWorkflow::class)->transition($order, OrderStatus::Delivered, $this->courier, cashCollected: true);

        $profile = $this->courier->deliveryProfile->fresh();
        $this->assertNull($profile->latitude);
        $this->assertNull($profile->located_at);

        $second = $this->active(OrderStatus::Delivering);
        $this->share($second);
        app(OrderWorkflow::class)->transition($second, OrderStatus::Cancelled, $this->admin, 'Client injoignable');
        $this->assertNull($this->courier->deliveryProfile->fresh()->latitude);
    }

    public function test_the_business_only_sees_the_courier_coming_to_the_store(): void
    {
        $order = $this->active(OrderStatus::CourierAssigned);
        $this->share($order);

        // Avant le retrait : approche du livreur vers la boutique, sans l'adresse du client.
        $json = $this->actingAs($this->owner)->getJson("/orders/{$order->id}/tracking")->assertOk()->json();
        $this->assertTrue($json['active']);
        $this->assertSame('to_store', $json['stage']);
        $this->assertNotNull($json['courier']);
        $this->assertNotNull($json['store']);
        $this->assertNull($json['destination'], 'l’adresse du client n’est jamais envoyée au commerce');
        $this->assertStringContainsString('arrive dans', $json['message']);

        // Dès que la commande est récupérée : plus rien (ni livreur, ni adresse, ni message).
        foreach ([OrderStatus::Delivering, OrderStatus::Arrived] as $status) {
            $order->update(['status' => $status]);
            $json = $this->actingAs($this->owner)->getJson("/orders/{$order->id}/tracking")->assertOk()->json();
            $this->assertFalse($json['active'], "statut {$status->value}");
            $this->assertNull($json['courier']);
            $this->assertNull($json['destination']);
            $this->assertNull($json['message']);
            $this->assertNull($json['eta_minutes']);
        }

        // Le client et l'admin gardent la vue complète.
        foreach ([$this->client, $this->admin] as $viewer) {
            $json = $this->actingAs($viewer)->getJson("/orders/{$order->id}/tracking")->json();
            $this->assertTrue($json['active']);
            $this->assertNotNull($json['courier']);
            $this->assertNotNull($json['destination']);
        }
    }

    public function test_the_business_order_page_carries_the_pickup_tracking_only(): void
    {
        $order = $this->active(OrderStatus::CourierAssigned);
        $this->share($order);

        $this->actingAs($this->owner)->get("/business/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->component('Business/Orders/Show')
            ->where('tracking.active', true)
            ->where('tracking.destination', null)
            ->where('tracking.store.name', 'Chez Maman Ngoye'));

        $order->update(['status' => OrderStatus::Delivering]);
        $this->actingAs($this->owner)->get("/business/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->where('tracking.active', false)
            ->where('tracking.courier', null)
            ->where('tracking.destination', null));
    }

    public function test_the_business_is_warned_once_when_the_courier_is_close_to_the_store(): void
    {
        $order = $this->active(OrderStatus::CourierAssigned);

        // Loin du commerce (environ 1,8 km) : aucune alerte.
        $this->share($order);
        $this->assertNotContains('Votre livreur arrive', $this->notificationTitles($this->owner));

        // À moins de 600 m : une seule notification, même si le livreur continue d'envoyer sa position.
        $near = [self::STORE[0] + 0.002, self::STORE[1] + 0.002];
        $this->share($order, ['latitude' => $near[0], 'longitude' => $near[1]]);
        $this->share($order, ['latitude' => $near[0], 'longitude' => $near[1]]);
        $this->share($order, ['latitude' => self::STORE[0], 'longitude' => self::STORE[1]]);

        $titles = $this->notificationTitles($this->owner);
        $this->assertSame(1, collect($titles)->filter(fn ($title) => $title === 'Votre livreur arrive')->count());
        $this->assertStringContainsString($order->reference, $this->owner->notifications()->first()->data['message']);
        // Le client n'est pas concerné par cette alerte.
        $this->assertNotContains('Votre livreur arrive', $this->notificationTitles($this->client));
    }

    public function test_an_exact_store_pin_replaces_the_neighborhood_center_and_tightens_the_alert(): void
    {
        $order = $this->active(OrderStatus::CourierAssigned);
        $this->share($order);

        // Sans épingle : centre du quartier, position approximative.
        $json = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertFalse($json['store']['exact']);
        $this->assertEqualsWithDelta(self::STORE[0], $json['store']['lat'], 0.00001);

        $pin = [0.3900000, 9.4400000];
        $this->store->update(['latitude' => $pin[0], 'longitude' => $pin[1]]);

        $json = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertTrue($json['store']['exact']);
        $this->assertEqualsWithDelta($pin[0], $json['store']['lat'], 0.00001);
        $this->assertEqualsWithDelta($pin[1], $json['store']['lng'], 0.00001);

        // L'entreprise voit aussi l'épingle exacte (c'est sa boutique).
        $json = $this->actingAs($this->owner)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertTrue($json['store']['exact']);

        // À ~350 m de l'épingle : trop loin (seuil 150 m) ; à ~100 m : le commerce est prévenu.
        $this->share($order, ['latitude' => $pin[0] + 0.0032, 'longitude' => $pin[1]]);
        $this->assertNotContains('Votre livreur arrive', $this->notificationTitles($this->owner));

        $this->share($order, ['latitude' => $pin[0] + 0.0009, 'longitude' => $pin[1]]);
        $this->assertContains('Votre livreur arrive', $this->notificationTitles($this->owner));
        $json = $this->actingAs($this->owner)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertStringContainsString('tout proche', $json['message']);
    }

    public function test_the_courier_reports_his_arrival_at_the_store(): void
    {
        $order = $this->active(OrderStatus::CourierAssigned);
        $this->share($order);

        $this->actingAs($this->courier)->post("/delivery/orders/{$order->id}/arrived-at-store")->assertRedirect();

        $this->assertNotNull($order->fresh()->courier_arrived_at);
        $this->assertContains('Votre livreur est arrivé', $this->notificationTitles($this->owner));
        $this->assertContains('Votre livreur est au commerce', $this->notificationTitles($this->client));

        // Un second clic ne renvoie aucune notification.
        $this->actingAs($this->courier)->post("/delivery/orders/{$order->id}/arrived-at-store")->assertRedirect();
        $this->assertSame(1, collect($this->notificationTitles($this->owner))->filter(fn ($t) => $t === 'Votre livreur est arrivé')->count());

        // La carte passe à l'étape « sur place » : plus d'estimation, messages adaptés à chacun.
        $client = $this->actingAs($this->client)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertSame('at_store', $client['stage']);
        $this->assertNull($client['eta_minutes']);
        $this->assertStringContainsString('arrivé au commerce', $client['message']);

        $business = $this->actingAs($this->owner)->getJson("/orders/{$order->id}/tracking")->json();
        $this->assertSame('at_store', $business['stage']);
        $this->assertSame('Votre livreur est arrivé : remettez-lui la commande.', $business['message']);
        $this->assertNull($business['destination']);

        // La page du livreur affiche l'heure à laquelle il a signalé son arrivée.
        $this->actingAs($this->courier)->get('/delivery/current')->assertInertia(fn ($page) => $page
            ->where('order.arrived_at_store', fn ($time) => preg_match('/^\d{2}h\d{2}$/', $time) === 1));
    }

    public function test_the_arrival_can_only_be_reported_by_the_assigned_courier_before_the_pickup(): void
    {
        $other = $this->makeCourier('Paul Autre', Neighborhood::firstWhere('name', 'Glass'));
        $order = $this->active(OrderStatus::CourierAssigned);

        $this->actingAs($other)->post("/delivery/orders/{$order->id}/arrived-at-store")->assertRedirect()->assertSessionHas('error');
        $this->assertNull($order->fresh()->courier_arrived_at);

        foreach ([OrderStatus::Delivering, OrderStatus::Arrived, OrderStatus::Delivered] as $status) {
            $order->update(['status' => $status]);
            $this->actingAs($this->courier)->post("/delivery/orders/{$order->id}/arrived-at-store")->assertSessionHas('error');
            $this->assertNull($order->fresh()->courier_arrived_at);
        }

        // Ni le client, ni l'entreprise.
        $this->actingAs($this->client)->post("/delivery/orders/{$order->id}/arrived-at-store")->assertRedirect(route('home'));
        $this->actingAs($this->owner)->post("/delivery/orders/{$order->id}/arrived-at-store")->assertRedirect(route('business.dashboard'));
        $this->assertSame(0, $this->owner->notifications()->count());
    }

    public function test_after_the_pickup_no_store_alert_is_sent(): void
    {
        $order = $this->active(OrderStatus::Delivering);

        $this->share($order, ['latitude' => self::STORE[0], 'longitude' => self::STORE[1]]);

        $this->assertNotContains('Votre livreur arrive', $this->notificationTitles($this->owner));
    }

    public function test_the_order_page_carries_the_tracking_data(): void
    {
        $order = $this->active();
        $this->share($order);

        $this->actingAs($this->client)->get("/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->component('Orders/Show')
            ->where('tracking.active', true)
            ->where('tracking.stage', 'to_client')
            ->where('tracking.courier.fresh', true)
            // La page de commande charge peu de colonnes : le commerce et l'adresse doivent quand même être placés.
            ->where('tracking.store.name', 'Chez Maman Ngoye')
            ->where('tracking.store.lat', self::STORE[0])
            ->where('tracking.destination.lng', self::HOME[1]));
    }

    public function test_neighborhoods_are_geolocated_by_the_seeder(): void
    {
        $this->seed(\Database\Seeders\NeighborhoodSeeder::class);

        $this->assertSame(16, Neighborhood::whereNotNull('latitude')->whereNotNull('longitude')->count());
        // Tous les quartiers sont dans la zone de Libreville.
        Neighborhood::all()->each(function (Neighborhood $neighborhood) {
            $this->assertEqualsWithDelta(0.4, (float) $neighborhood->latitude, 0.2, $neighborhood->name);
            $this->assertEqualsWithDelta(9.45, (float) $neighborhood->longitude, 0.15, $neighborhood->name);
        });
    }
}
