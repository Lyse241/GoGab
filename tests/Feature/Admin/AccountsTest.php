<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\VehicleType;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Gogab']);
    }

    private function account(string $role, string $state = 'pending', array $attributes = []): User
    {
        return User::factory()->{$state}()->create(['role' => $role, ...$attributes]);
    }

    public function test_only_admins_can_open_the_accounts_page(): void
    {
        $this->get('/admin/accounts')->assertRedirect(route('login', absolute: false));

        foreach (['client', 'delivery', 'business'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin/accounts')
                ->assertSessionHas('error');
        }
    }

    public function test_pending_accounts_are_listed_by_default_oldest_first(): void
    {
        $this->travelTo(now()->subDays(3));
        $older = $this->account('delivery', 'pending', ['name' => 'Livreur Ancien', 'phone' => '077 11 22 33']);
        $older->documents()->create([
            'type' => DocumentType::IdCard,
            'file_path' => 'documents/x/cin.pdf',
            'original_name' => 'cin.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'status' => DocumentStatus::Pending,
        ]);
        $this->travelBack();

        $newer = $this->account('client', 'pending', ['name' => 'Client Récent']);
        $this->account('business', 'approved');
        $this->account('admin', 'pending'); // les admins n'apparaissent jamais

        $this->actingAs($this->admin)
            ->get('/admin/accounts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->where('filters.status', 'pending')
                ->has('accounts.data', 2)
                ->where('accounts.data.0.id', $older->id)
                ->where('accounts.data.0.role', 'delivery')
                ->where('accounts.data.0.role_label', 'Livreur')
                ->where('accounts.data.0.phone', '077 11 22 33')
                ->where('accounts.data.0.documents_count', 1)
                ->where('accounts.data.0.account_status', 'pending')
                ->where('accounts.data.0.registered_at', $older->created_at->format('d/m/Y'))
                ->where('accounts.data.1.id', $newer->id)
                ->where('counts', ['pending' => 2, 'approved' => 1, 'rejected' => 0, 'suspended' => 0, 'all' => 3])
                ->has('types', 3));
    }

    public function test_tabs_type_filter_and_counts(): void
    {
        $this->account('client', 'pending');
        $this->account('delivery', 'pending');
        $rejected = $this->account('delivery', 'rejected');
        $this->account('business', 'suspended');

        $this->actingAs($this->admin)
            ->get('/admin/accounts?status=rejected')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'rejected')
                ->has('accounts.data', 1)
                ->where('accounts.data.0.id', $rejected->id));

        // Le filtre par type s'applique aussi aux compteurs des onglets.
        $this->actingAs($this->admin)
            ->get('/admin/accounts?type=delivery')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'delivery')
                ->has('accounts.data', 1)
                ->where('accounts.data.0.role', 'delivery')
                ->where('counts', ['pending' => 1, 'approved' => 0, 'rejected' => 1, 'suspended' => 0, 'all' => 2]));

        $this->actingAs($this->admin)
            ->get('/admin/accounts?status=inconnu&type=admin')
            ->assertSessionHasErrors(['status', 'type']);
    }

    public function test_search_by_name_email_or_phone(): void
    {
        $marie = $this->account('client', 'pending', ['name' => 'Marie Ndong', 'email' => 'marie@exemple.ga', 'phone' => '077 12 34 56']);
        $this->account('client', 'pending', ['name' => 'Paul Obame', 'email' => 'paul@exemple.ga', 'phone' => '066 98 76 54']);

        foreach (['ndong', 'marie@exemple', '077 12', '0771234', '12 34 56'] as $query) {
            $this->actingAs($this->admin)
                ->get('/admin/accounts?q='.urlencode($query))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.q', $query)
                    ->has('accounts.data', 1)
                    ->where('accounts.data.0.id', $marie->id));
        }

        $this->actingAs($this->admin)
            ->get('/admin/accounts?q=%25')
            ->assertInertia(fn (Assert $page) => $page->has('accounts.data', 0));
    }

    public function test_pending_counter_is_shared_with_the_admin_menu_and_dashboard(): void
    {
        $this->account('client', 'pending');
        $this->account('business', 'pending');
        $this->account('delivery', 'approved');

        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertInertia(fn (Assert $page) => $page
                ->where('badges.pending_accounts', 2)
                ->where('stats.pending_accounts', 2));

        // Les autres rôles ne reçoivent pas ce compteur.
        $this->actingAs(User::factory()->create(['role' => 'delivery']))
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page->where('badges', []));
    }

    public function test_directories_list_every_status_of_one_role(): void
    {
        $this->account('client', 'approved', ['name' => 'Client Validé']);
        $this->account('client', 'pending');
        $this->account('delivery', 'approved');

        $this->actingAs($this->admin)
            ->get('/admin/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->where('page.mode', 'directory')
                ->where('page.locked_type', 'client')
                ->where('filters.status', 'all')
                ->has('accounts.data', 2)
                ->where('counts.all', 2));

        // Le type est imposé par l'annuaire.
        $this->actingAs($this->admin)
            ->get('/admin/clients?type=delivery&status=approved')
            ->assertInertia(fn (Assert $page) => $page->has('accounts.data', 1)->where('accounts.data.0.name', 'Client Validé'));
    }

    public function test_sections_not_built_yet_show_coming_soon(): void
    {
        $sections = [
            '/admin/orders' => 'Commandes',
        ];

        foreach ($sections as $url => $title) {
            $this->actingAs($this->admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('ComingSoon')->where('title', $title));
        }
    }

    private function order(Store $store, array $attributes = []): Order
    {
        return Order::create([
            'store_id' => $store->id,
            'client_id' => User::factory()->create(['name' => 'Client Commande'])->id,
            'neighborhood_id' => Neighborhood::firstOrCreate(['name' => 'Louis'], ['zone' => 'Centre'])->id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 2500,
            'total_price' => 2500,
            'payment_method' => 'cash',
            'status' => OrderStatus::Delivered,
            ...$attributes,
        ]);
    }

    public function test_business_fiche_shows_store_products_and_recent_orders(): void
    {
        $owner = $this->account('business', 'approved');
        $store = Store::factory()->create(['owner_id' => $owner->id, 'name' => 'Chez Tante Marie']);
        $store->products()->create(['name' => 'Poulet DG', 'price' => 4500, 'is_available' => true]);
        $store->products()->create(['name' => 'Banane plantain', 'price' => 1000, 'is_available' => false]);
        $this->order($store);
        $this->order(Store::factory()->create()); // autre commerce : non compté

        $this->actingAs($this->admin)
            ->get("/admin/accounts/{$owner->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.name', 'Chez Tante Marie')
                ->where('activity.products.count', 2)
                ->where('activity.products.available', 1)
                ->where('activity.products.items.0.name', 'Banane plantain')
                ->where('activity.orders_count', 1)
                ->has('activity.recent_orders', 1)
                ->where('activity.recent_orders.0.client', 'Client Commande'));
    }

    public function test_delivery_fiche_shows_zone_availability_and_recent_courses(): void
    {
        $courier = $this->account('delivery', 'approved');
        $courier->deliveryProfile()->create([
            'vehicle_type' => VehicleType::Moto,
            'base_neighborhood_id' => Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord'])->id,
            'is_available' => true,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/accounts/{$courier->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveryProfile.base_zone', 'Nord')
                ->where('activity.is_available', true)
                ->where('activity.products', null)
                ->where('activity.orders_count', 0)
                ->has('activity.recent_orders', 0));

        $this->order(Store::factory()->create(['name' => 'Pharmacie du Port']), ['delivery_id' => $courier->id]);

        $this->actingAs($this->admin)
            ->get("/admin/accounts/{$courier->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.orders_count', 1)
                ->where('activity.recent_orders.0.store', 'Pharmacie du Port'));
    }
}
