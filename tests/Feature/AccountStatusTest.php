<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rôles, statuts de compte (pending, approved, rejected, suspended) et redirections.
 */
class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_each_role_to_its_space(): void
    {
        $cases = [
            'client' => '/',
            'delivery' => '/delivery',
            'business' => '/business',
            'admin' => '/admin',
        ];

        foreach ($cases as $role => $expected) {
            $user = User::factory()->create(['role' => $role]);
            if ($role === 'business') {
                Store::factory()->create(['owner_id' => $user->id]); // créé à l'inscription
            }

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($expected);
            $this->get($expected)->assertOk();

            auth()->logout();
        }
    }

    public function test_non_approved_accounts_are_sent_to_their_status_page_after_login(): void
    {
        $cases = [
            'pending' => '/account/pending',
            'rejected' => '/account/rejected',
            'suspended' => '/account/suspended',
        ];

        foreach ($cases as $state => $expected) {
            $user = User::factory()->{$state}()->create(['role' => 'delivery']);

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($expected);
            $this->get($expected)->assertOk();

            auth()->logout();
        }
    }

    public function test_pending_accounts_are_blocked_from_their_space(): void
    {
        foreach (['delivery' => '/delivery', 'business' => '/business', 'admin' => '/admin'] as $role => $url) {
            $this->actingAs(User::factory()->pending()->create(['role' => $role]))
                ->get($url)
                ->assertRedirect('/account/pending')
                ->assertSessionHas('warning');
        }
    }

    public function test_pending_client_can_browse_but_not_order(): void
    {
        $client = User::factory()->pending()->create(['role' => 'client']);
        $store = Store::factory()->create();
        $product = $store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($client)->get('/')->assertOk();
        $this->actingAs($client)->get("/stores/{$store->id}")->assertOk();
        $this->actingAs($client)->get('/cart')->assertOk();

        $this->actingAs($client)->get('/checkout')->assertRedirect('/account/pending');

        $this->actingAs($client)->post('/orders', [
            'neighborhood_id' => Neighborhood::create(['name' => 'Glass'])->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'payment_method' => 'airtel_money',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertRedirect('/account/pending');

        $this->actingAs($client)->postJson('/orders', [])->assertForbidden()->assertJsonStructure(['message']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_status_pages_are_reserved_to_the_matching_status(): void
    {
        $approved = User::factory()->create(['role' => 'client']);
        $pending = User::factory()->pending()->create(['role' => 'client']);

        $this->actingAs($approved)->get('/account/pending')->assertRedirect('/');
        $this->actingAs($pending)->get('/account/rejected')->assertRedirect('/account/pending');
        $this->actingAs($pending)->get('/account/suspended')->assertRedirect('/account/pending');

        auth()->logout();
        $this->get('/account/pending')->assertRedirect(route('login', absolute: false));
    }

    public function test_rejected_page_shows_the_reason_and_correction_link(): void
    {
        $user = User::factory()->rejected('Le RCCM est illisible.')->create(['role' => 'business']);

        $this->actingAs($user)
            ->get('/account/rejected')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Rejected')
                ->where('rejection_reason', 'Le RCCM est illisible.')
                ->where('submission.account.role', 'business'));

        $this->actingAs($user)
            ->get('/account/rejected/correction')
            ->assertInertia(fn (Assert $page) => $page->component('Account/Correction'));
    }

    public function test_suspended_account_has_a_dedicated_page(): void
    {
        $user = User::factory()->suspended()->create(['role' => 'client']);

        $this->actingAs($user)->get('/checkout')->assertRedirect('/account/suspended');
        $this->actingAs($user)
            ->get('/account/suspended')
            ->assertInertia(fn (Assert $page) => $page->component('Account/Suspended'));
    }

    public function test_role_middleware_accepts_several_roles(): void
    {
        Route::middleware(['web', 'auth', 'role:admin,business'])->get('/_test/admin-or-business', fn () => 'ok');

        $this->actingAs(User::factory()->create(['role' => 'business']))->get('/_test/admin-or-business')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/_test/admin-or-business')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'client']))->get('/_test/admin-or-business')
            ->assertRedirect('/')
            ->assertSessionHas('error');
    }

    public function test_old_dashboard_urls_redirect(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin');
        $this->get('/delivery/dashboard')->assertRedirect('/delivery');
    }

    public function test_shared_auth_user_only_contains_interface_fields(): void
    {
        $user = User::factory()->pending()->create(['name' => 'Marie Ndong', 'role' => 'client']);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', 'Marie Ndong')
                ->where('auth.user.role', 'client')
                ->where('auth.user.account_status', 'pending')
                ->where('auth.user.account_status_label', 'En attente de validation')
                ->where('auth.user.initials', 'MN')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->missing('auth.user.address_landmarks')
                ->where('auth.unread_notifications', 0)
                ->has('flash'));
    }

    public function test_seeded_test_accounts_cover_every_state(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local'); // le seeder génère des fichiers de démo
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $expected = [
            'admin@gogab.ga' => ['admin', 'approved'],
            'client1@gogab.ga' => ['client', 'approved'],
            'client.attente@gogab.ga' => ['client', 'pending'],
            'client.suspendu@gogab.ga' => ['client', 'suspended'],
            'livreur1@gogab.ga' => ['delivery', 'approved'],
            'livreur.attente@gogab.ga' => ['delivery', 'pending'],
            'entreprise@gogab.ga' => ['business', 'approved'],
            'entreprise.refusee@gogab.ga' => ['business', 'rejected'],
        ];

        foreach ($expected as $email => [$role, $status]) {
            $user = User::where('email', $email)->sole();
            $this->assertSame([$role, $status], [$user->role->value, $user->account_status->value], $email);
            $this->post('/login', ['email' => $email, 'password' => 'password'])->assertRedirect();
            auth()->logout();
        }

        $this->assertNotEmpty(User::where('email', 'entreprise.refusee@gogab.ga')->value('rejection_reason'));
        $this->assertNotNull(User::where('email', 'entreprise@gogab.ga')->sole()->store);
        $this->assertCount(3, User::where('email', 'livreur.attente@gogab.ga')->sole()->documents);
    }
}
