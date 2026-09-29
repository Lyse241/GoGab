<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\ModerationType;
use App\Enums\OrderStatus;
use App\Enums\VehicleType;
use App\Models\ModerationAction;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Gogab']);
    }

    private function moderate(User $account, string $action, array $data = [])
    {
        return $this->actingAs($this->admin)->post("/admin/accounts/{$account->id}/moderation/{$action}", $data);
    }

    // --- Avertissement ---

    public function test_warning_is_notified_listed_and_must_be_acknowledged(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->moderate($client, 'warn', ['reason' => 'comportement_abusif', 'message' => ''])
            ->assertSessionHasErrors(['message' => 'Écrivez le message qui sera montré à l’utilisateur.']);

        $this->moderate($client, 'warn', ['reason' => 'comportement_abusif', 'message' => 'Propos insultants envers un livreur.'])
            ->assertSessionHas('success');

        $notification = $client->notifications()->sole();
        $this->assertSame('Vous avez reçu un avertissement', $notification->data['title']);
        $this->assertStringContainsString('Comportement abusif : Propos insultants envers un livreur.', $notification->data['message']);
        $this->assertSame('/account/warnings', $notification->data['url']);

        // À la prochaine visite : avertissement à accuser (bandeau « J'ai compris »).
        $warning = ModerationAction::sole();
        $this->actingAs($client)->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('moderation.warnings_count', 1)
                ->where('moderation.pending_warning.id', $warning->id)
                ->where('moderation.pending_warning.reason', 'Comportement abusif'));

        $this->actingAs($client)->get('/account/warnings')
            ->assertInertia(fn (Assert $page) => $page->component('Account/Warnings')->has('warnings', 1)->where('warnings.0.acknowledged', false));

        $this->actingAs($client)->postJson("/account/warnings/{$warning->id}/acknowledge")->assertOk();
        $this->assertNotNull($warning->fresh()->acknowledged_at);
        $this->actingAs($client)->get('/')->assertInertia(fn (Assert $page) => $page->where('moderation.pending_warning', null));

        // On n'accuse pas l'avertissement d'un autre.
        $this->actingAs(User::factory()->create())->postJson("/account/warnings/{$warning->id}/acknowledge")->assertNotFound();
    }

    public function test_three_warnings_suggest_a_block_but_never_block(): void
    {
        $courier = User::factory()->create(['role' => 'delivery']);

        foreach (range(1, 3) as $i) {
            $this->moderate($courier, 'warn', ['reason' => 'retards_repetes', 'message' => "Retard n°{$i} sur une livraison."]);
        }

        $this->assertSame(AccountStatus::Approved, $courier->fresh()->account_status);
        $this->actingAs($this->admin)->get("/admin/accounts/{$courier->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('moderation.warnings_count', 3)
                ->where('moderation.suggest_block', true)
                ->has('moderation.actions', 3));
    }

    // --- Blocage ---

    public function test_temporary_block_then_automatic_unblock(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->moderate($client, 'block', ['reason' => 'fraude', 'message' => 'Paiements contestés à répétition.', 'duration' => '7d'])
            ->assertSessionHas('success');

        $client->refresh();
        $this->assertSame(AccountStatus::Suspended, $client->account_status);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $client->blocked_until->timestamp, 5);
        $this->assertSame('Votre compte est bloqué', $client->notifications()->sole()->data['title']);

        // Pas encore expiré.
        $this->artisan('gogab:unblock-expired')->assertSuccessful();
        $this->assertTrue($client->fresh()->isBlocked());

        $this->travel(7)->days();
        $this->travel(1)->minutes();
        $this->artisan('gogab:unblock-expired')->expectsOutputToContain('1 compte(s) débloqué(s)')->assertSuccessful();

        $client->refresh();
        $this->assertSame(AccountStatus::Approved, $client->account_status);
        $this->assertNull($client->blocked_until);
        $unblock = $client->moderationActions()->first();
        $this->assertSame(ModerationType::Unblock, $unblock->type);
        $this->assertNull($unblock->admin_id);
        $this->assertTrue($client->notifications()->get()->contains(fn ($notification) => $notification->data['title'] === 'Votre compte est débloqué'));
    }

    public function test_blocked_account_is_redirected_to_the_suspended_page(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->moderate($client, 'block', ['reason' => 'comportement_abusif', 'message' => 'Insultes répétées au support.', 'duration' => 'indefinite']);
        $client->refresh();

        foreach (['/', '/cart', '/profile', '/notifications', '/checkout'] as $url) {
            $this->actingAs($client)->get($url)->assertRedirect('/account/suspended');
        }
        $this->actingAs($client)->getJson('/notifications/unread')->assertForbidden();

        $this->actingAs($client)->get('/account/suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Suspended')
                ->where('block.reason', 'Comportement abusif')
                ->where('block.message', 'Insultes répétées au support.')
                ->where('blockedUntil', null));

        // La déconnexion reste possible.
        $this->actingAs($client)->post('/logout')->assertRedirect('/');
    }

    public function test_blocked_courier_is_no_longer_available(): void
    {
        $courier = User::factory()->create(['role' => 'delivery']);
        $courier->deliveryProfile()->create([
            'vehicle_type' => VehicleType::Moto,
            'base_neighborhood_id' => Neighborhood::create(['name' => 'Louis'])->id,
            'is_available' => true,
        ]);

        $this->moderate($courier, 'block', ['reason' => 'retards_repetes', 'message' => 'Trop de retards ce mois-ci.', 'duration' => '24h']);

        $this->assertFalse($courier->deliveryProfile->fresh()->is_available);
        $this->actingAs($courier->fresh())->get('/delivery')->assertRedirect('/account/suspended');
    }

    public function test_blocked_business_store_is_hidden_then_shown_again(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $store = Store::factory()->create(['owner_id' => $owner->id, 'name' => 'Chez Tante Marie']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('stores', 1));

        $this->moderate($owner, 'block', ['reason' => 'plainte', 'message' => 'Plusieurs plaintes pour produits périmés.', 'duration' => '30d']);

        $this->assertTrue($store->fresh()->is_active); // rien n'est effacé : seule la visibilité change
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('stores', 0));
        $this->get("/stores/{$store->id}")->assertNotFound();
        $this->assertFalse($store->fresh()->isOpenNow());

        $this->moderate($owner, 'unblock', ['reason' => 'autre', 'message' => 'Situation régularisée.'])->assertSessionHas('success');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('stores', 1));
        $this->assertTrue($owner->notifications()->get()->contains(fn ($notification) => $notification->data['title'] === 'Votre compte est débloqué'));
    }

    public function test_active_orders_are_listed_before_blocking(): void
    {
        $courier = User::factory()->create(['role' => 'delivery']);
        $store = Store::factory()->create();
        $neighborhood = Neighborhood::create(['name' => 'Glass']);
        $make = fn (OrderStatus $status) => Order::create([
            'store_id' => $store->id,
            'client_id' => User::factory()->create()->id,
            'delivery_id' => $courier->id,
            'neighborhood_id' => $neighborhood->id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 1000,
            'total_price' => 1000,
            'payment_method' => 'cash',
            'status' => $status,
        ]);
        $active = $make(OrderStatus::Delivering);
        $make(OrderStatus::Delivered);

        $this->actingAs($this->admin)->get("/admin/accounts/{$courier->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('moderation.active_orders', 1)
                ->where('moderation.active_orders.0.reference', $active->fresh()->reference));
    }

    public function test_blocked_account_cannot_be_reapproved_through_validation(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->moderate($client, 'block', ['reason' => 'fraude', 'message' => 'Paiements contestés.', 'duration' => 'indefinite']);

        $this->actingAs($this->admin)->post("/admin/accounts/{$client->id}/approve")->assertSessionHasErrors('account');
        $this->actingAs($this->admin)->post("/admin/accounts/{$client->id}/reject", ['reason' => 'Test de refus.'])->assertSessionHasErrors('account');
        $this->assertTrue($client->fresh()->isBlocked());

        $this->actingAs($this->admin)->get("/admin/accounts/{$client->id}")
            ->assertInertia(fn (Assert $page) => $page->where('decision.can_approve', false));
    }

    public function test_unblock_requires_a_reason_and_only_applies_to_blocked_accounts(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->moderate($client, 'unblock', ['reason' => 'autre'])->assertSessionHasErrors('moderation');

        $this->moderate($client, 'block', ['reason' => 'fraude', 'message' => 'Paiements contestés.', 'duration' => 'indefinite']);
        $this->moderate($client, 'unblock', [])->assertSessionHasErrors(['reason' => 'Choisissez un motif.']);
        $this->assertTrue($client->fresh()->isBlocked());

        // Un compte en attente ne se bloque pas (on refuse l'inscription).
        $pending = User::factory()->pending()->create(['role' => 'client']);
        $this->moderate($pending, 'block', ['reason' => 'fraude', 'message' => 'Faux documents.', 'duration' => '24h'])->assertSessionHasErrors('moderation');
    }

    // --- Signalement interne ---

    public function test_flag_is_internal_and_filterable(): void
    {
        $courier = User::factory()->create(['role' => 'delivery', 'name' => 'Livreur Signalé']);
        User::factory()->create(['role' => 'delivery', 'name' => 'Livreur Normal']);

        $this->moderate($courier, 'flag', ['reason' => 'faux_documents', 'internal_note' => 'Plaque différente de celle déclarée, à surveiller.'])
            ->assertSessionHas('success');

        $this->assertNotNull($courier->fresh()->flagged_at);
        $this->assertSame(0, $courier->notifications()->count()); // jamais notifié

        $this->actingAs($this->admin)->get('/admin/deliveries')
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.title', 'Livreurs')
                ->where('filters.status', 'all')
                ->has('accounts.data', 2)
                ->where('flaggedCount', 1));

        $this->actingAs($this->admin)->get('/admin/deliveries?flagged=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts.data', 1)
                ->where('accounts.data.0.name', 'Livreur Signalé')
                ->where('accounts.data.0.flagged', true));

        // La note interne n'est jamais envoyée à l'utilisateur.
        $this->actingAs($courier->fresh())->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('moderation.warnings_count', 0));

        $this->moderate($courier, 'unflag', [])->assertSessionHas('success');
        $this->assertNull($courier->fresh()->flagged_at);
    }

    // --- Journal et sécurité ---

    public function test_moderation_log_can_be_filtered(): void
    {
        $client = User::factory()->create(['role' => 'client', 'name' => 'Marie']);
        $other = User::factory()->create(['role' => 'admin', 'name' => 'Autre Admin']);
        $this->moderate($client, 'warn', ['reason' => 'plainte', 'message' => 'Plainte d’un commerçant.']);
        $this->moderate($client, 'flag', ['reason' => 'fraude', 'internal_note' => 'Plusieurs comptes suspects.']);

        $this->actingAs($this->admin)->get('/admin/moderation')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Moderation/Index')
                ->has('actions.data', 2)
                ->where('actions.data.0.type', 'flag')
                ->where('actions.data.0.account.name', 'Marie')
                ->where('actions.data.0.admin', 'Admin Gogab'));

        $this->actingAs($this->admin)->get('/admin/moderation?type=warning')->assertInertia(fn (Assert $page) => $page->has('actions.data', 1));
        $this->actingAs($this->admin)->get('/admin/moderation?reason=fraude')->assertInertia(fn (Assert $page) => $page->has('actions.data', 1));
        $this->actingAs($this->admin)->get("/admin/moderation?admin={$other->id}")->assertInertia(fn (Assert $page) => $page->has('actions.data', 0));
    }

    public function test_only_admins_moderate_and_never_themselves_or_another_admin(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $payload = ['reason' => 'fraude', 'message' => 'Test de sécurité.', 'duration' => '24h'];

        foreach (['client', 'delivery', 'business'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post("/admin/accounts/{$client->id}/moderation/block", $payload);
        }
        $this->assertFalse($client->fresh()->isBlocked());
        $this->assertSame(0, ModerationAction::count());

        $this->actingAs($this->admin)->post("/admin/accounts/{$this->admin->id}/moderation/block", $payload)->assertForbidden();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin)->post("/admin/accounts/{$otherAdmin->id}/moderation/warn", $payload)->assertForbidden();
        $this->assertSame(0, ModerationAction::count());

        $this->actingAs($client)->get('/admin/moderation')->assertSessionHas('error');
    }
}
