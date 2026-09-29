<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Neighborhood $neighborhood;

    protected function setUp(): void
    {
        parent::setUp();

        $this->neighborhood = Neighborhood::create(['name' => 'Glass', 'zone' => 'Centre']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Marie Ndong',
            'phone' => '077 12 34 56',
            'email' => 'marie@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Près de la pharmacie du Bon Secours, portail bleu',
            ...$overrides,
        ];
    }

    // --- Choix du profil ---

    public function test_profile_choice_screen_links_to_the_three_forms(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register/Index'));

        $this->get('/register/client')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register/Client')
                ->has('neighborhoods', 1));

        $this->get('/register/delivery')
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register/Delivery'));
        $this->get('/register/business')
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register/Business'));
    }

    public function test_logged_in_users_cannot_open_the_registration(): void
    {
        $this->actingAs(User::factory()->create())->get('/register/client')->assertRedirect();
    }

    // --- Inscription client ---

    public function test_client_registers_as_pending_and_is_sent_to_the_pending_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pendingAdmin = User::factory()->pending()->create(['role' => 'admin']);

        $response = $this->post('/register/client', $this->payload());

        $user = User::where('email', 'marie@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('account.pending', absolute: false));

        $this->assertSame(Role::Client, $user->role);
        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertNull($user->approved_at);
        $this->assertSame($this->neighborhood->id, $user->neighborhood_id);
        $this->assertSame('Près de la pharmacie du Bon Secours, portail bleu', $user->address_landmarks);

        // Tous les admins validés sont prévenus.
        $notification = $admin->notifications()->sole();
        $this->assertSame('Nouveau compte client à valider', $notification->data['title']);
        $this->assertSame('warning', $notification->data['type']);
        $this->assertStringContainsString('Marie Ndong', $notification->data['message']);
        $this->assertSame(0, $pendingAdmin->notifications()->count());
        $this->assertSame(0, $user->notifications()->count());

        $this->get('/account/pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Pending')
                ->where('submission.account.name', 'Marie Ndong')
                ->where('submission.account.neighborhood', 'Glass'));
    }

    public function test_clients_are_approved_directly_when_auto_approve_is_enabled(): void
    {
        config(['gogab.auto_approve_clients' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post('/register/client', $this->payload())
            ->assertRedirect(route('home', absolute: false))
            ->assertSessionHas('success');

        $user = User::where('email', 'marie@example.com')->sole();
        $this->assertSame(AccountStatus::Approved, $user->account_status);
        $this->assertNotNull($user->approved_at);
        $this->assertSame('Nouveau client inscrit', $admin->notifications()->sole()->data['title']);
    }

    public function test_role_and_status_cannot_be_forced(): void
    {
        $this->post('/register/client', $this->payload(['role' => 'admin', 'account_status' => 'approved']));

        $this->assertDatabaseHas('users', ['email' => 'marie@example.com', 'role' => 'client', 'account_status' => 'pending']);
    }

    public function test_phone_is_normalized_and_must_be_unique(): void
    {
        $this->post('/register/client', $this->payload(['phone' => '+241 77 12 34 56']));
        $this->assertDatabaseHas('users', ['email' => 'marie@example.com', 'phone' => '077 12 34 56']);

        auth()->logout();

        // Même numéro saisi autrement : refusé.
        $this->post('/register/client', $this->payload(['email' => 'autre@example.com', 'phone' => '077123456']))
            ->assertSessionHasErrors(['phone' => 'Ce numéro de téléphone est déjà utilisé par un autre compte.']);
        $this->assertDatabaseMissing('users', ['email' => 'autre@example.com']);
    }

    public function test_validation_messages_are_in_french(): void
    {
        User::factory()->create(['email' => 'marie@example.com']);

        $this->post('/register/client', [
            'name' => '',
            'phone' => '12',
            'email' => 'marie@example.com',
            'password' => 'password',
            'password_confirmation' => 'autre',
            'neighborhood_id' => 999,
            'address_landmarks' => 'court',
        ])->assertSessionHasErrors([
            'name' => 'Indiquez votre nom complet.',
            'phone' => 'Numéro invalide : saisissez un numéro gabonais à 9 chiffres (ex. 077 12 34 56).',
            'email' => 'Un compte existe déjà avec cette adresse e-mail. Connectez-vous plutôt.',
            'password' => 'Les deux mots de passe ne correspondent pas.',
            'neighborhood_id' => 'Ce quartier n’est pas desservi.',
            'address_landmarks' => 'Soyez un peu plus précis (10 caractères minimum).',
        ]);

        $this->assertGuest();
    }
}
