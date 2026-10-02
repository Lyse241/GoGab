<?php

namespace Tests\Feature\Delivery;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Espace livreur : accès, disponibilité, accueil (course en cours, compteurs du jour),
 * profil (quartier de base = zone des offres, documents).
 */
class CourierSpaceTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_only_an_approved_courier_reaches_the_delivery_space(): void
    {
        foreach (['/delivery', '/delivery/offers', '/delivery/current', '/delivery/history', '/delivery/profile'] as $url) {
            $this->actingAs($this->courier)->get($url)->assertOk();
        }

        $pending = $this->makeCourier('En attente', Neighborhood::firstWhere('name', 'Glass'), state: 'pending');
        $this->actingAs($pending)->get('/delivery')->assertRedirect('/account/pending');
        $this->actingAs($pending)->patch('/delivery/availability', ['is_available' => false])->assertRedirect('/account/pending');

        $this->actingAs($this->client)->get('/delivery')->assertRedirect('/');
        $this->actingAs($this->owner)->get('/delivery/profile')->assertRedirect('/business');
        $this->actingAs($this->admin)->patch('/delivery/availability', ['is_available' => true])->assertRedirect('/admin');

        auth()->logout();
        $this->get('/delivery')->assertRedirect('/login');
    }

    public function test_the_layout_receives_availability_and_zone(): void
    {
        $this->actingAs($this->courier)
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Home')
                ->where('courier', ['is_available' => true, 'base_neighborhood' => 'Glass', 'zone' => 'Centre']));

        // Prop réservée aux livreurs.
        $this->actingAs($this->client)->get('/')->assertInertia(fn (Assert $page) => $page->where('courier', null));
    }

    public function test_the_availability_switch_is_saved(): void
    {
        $this->actingAs($this->courier)
            ->from('/delivery')
            ->patch('/delivery/availability', ['is_available' => false])
            ->assertRedirect('/delivery')
            ->assertSessionHas('success');
        $this->assertFalse($this->courier->deliveryProfile->fresh()->is_available);

        $this->actingAs($this->courier)->get('/delivery')->assertInertia(fn (Assert $page) => $page->where('courier.is_available', false));

        $this->actingAs($this->courier)->patch('/delivery/availability', ['is_available' => true]);
        $this->assertTrue($this->courier->deliveryProfile->fresh()->is_available);

        $this->actingAs($this->courier)->patch('/delivery/availability', [])->assertSessionHasErrors('is_available');
    }

    public function test_an_unavailable_courier_receives_no_announcement_notification(): void
    {
        $this->actingAs($this->courier)->patch('/delivery/availability', ['is_available' => false]);
        $order = $this->makeOrder(OrderStatus::Preparing);

        $this->actingAs($this->owner)->put(route('orders.status.update', $order), ['status' => 'en_recherche_livreur']);

        $this->assertSame([], $this->notificationTitles($this->courier));
        $this->actingAs($this->courier)->get('/delivery/offers')->assertInertia(fn (Assert $page) => $page->has('offers', 0));
    }

    public function test_changing_the_base_neighborhood_changes_the_zone_and_the_offers(): void
    {
        $this->makeOrder(OrderStatus::SearchingCourier); // commerce en zone Centre
        $akanda = Neighborhood::firstWhere('name', 'Akanda'); // zone Nord

        $this->actingAs($this->courier)
            ->from('/delivery/profile')
            ->patch('/delivery/profile/base-neighborhood', ['base_neighborhood_id' => $akanda->id])
            ->assertRedirect('/delivery/profile')
            ->assertSessionHas('success');

        $this->assertSame($akanda->id, $this->courier->deliveryProfile->fresh()->base_neighborhood_id);
        $this->actingAs($this->courier)
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page->where('courier.zone', 'Nord')->where('courier.base_neighborhood', 'Akanda'));
        $this->actingAs($this->courier)
            ->get('/delivery/offers')
            ->assertInertia(fn (Assert $page) => $page->where('zone', 'Nord')->has('offers', 0));

        $this->actingAs($this->courier)
            ->patch('/delivery/profile/base-neighborhood', ['base_neighborhood_id' => 9999])
            ->assertSessionHasErrors('base_neighborhood_id');
        $this->assertSame($akanda->id, $this->courier->deliveryProfile->fresh()->base_neighborhood_id);
    }

    public function test_home_shows_the_current_delivery_and_today_counters(): void
    {
        Carbon::setTestNow('2026-10-02 10:00:00');
        CarbonImmutable::setTestNow('2026-10-02 10:00:00');

        $current = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id, 'delivery_fee' => 1000]);
        $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id, 'delivery_fee' => 1500]);
        $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->farCourier->id, 'delivery_fee' => 1000]); // autre livreur
        $yesterday = $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id, 'delivery_fee' => 1000]);
        $yesterday->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $this->actingAs($this->courier)
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page
                ->where('current.id', $current->id)
                ->where('current.status', 'en_livraison')
                ->where('current.store', 'Chez Maman Ngoye')
                ->where('stats', ['deliveries' => 2, 'earnings' => 2500])
                ->where('badges.active_orders', 1));

        $this->actingAs($this->farCourier)
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page->where('current', null)->where('stats.deliveries', 1));
    }

    public function test_profile_shows_vehicle_and_the_status_of_each_required_document(): void
    {
        $this->courier->documents()->create([
            'type' => DocumentType::IdCard,
            'file_path' => 'documents/x/cin.pdf',
            'original_name' => 'cin.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'status' => 'approved',
        ]);
        $this->courier->documents()->create([
            'type' => DocumentType::DrivingLicense,
            'file_path' => 'documents/x/permis.pdf',
            'original_name' => 'permis.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'status' => 'rejected',
            'rejection_reason' => 'Photo floue',
        ]);

        $this->actingAs($this->courier)
            ->get('/delivery/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Profile')
                ->where('vehicle.type', 'Moto')
                ->where('baseNeighborhoodId', Neighborhood::firstWhere('name', 'Glass')->id)
                ->has('documents', count(DocumentType::requiredFor($this->courier->role, $this->courier->deliveryProfile->vehicle_type)))
                ->where('documents.0.type', DocumentType::IdCard->value)
                ->where('documents.0.status', 'approved')
                ->where('documents.1.status', 'rejected')
                ->where('documents.1.rejection_reason', 'Photo floue')
                ->where('documents.2.status', null)); // manquant
    }

    public function test_a_courier_without_delivery_profile_cannot_toggle_availability(): void
    {
        $courier = User::factory()->create(['role' => 'delivery']);

        $this->actingAs($courier)->patch('/delivery/availability', ['is_available' => true])->assertNotFound();
        $this->actingAs($courier)->get('/delivery/profile')->assertNotFound();
    }
}
