<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_route_shows_the_gogab_404_page(): void
    {
        $this->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404));
    }

    public function test_unknown_store_and_order_show_the_404_page(): void
    {
        $this->get('/stores/999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error'));

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/orders/999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error'));
    }

    public function test_forbidden_action_shows_the_403_page(): void
    {
        // Une entreprise tente d'ouvrir le produit d'une autre entreprise (ProductPolicy).
        $product = Store::factory()->create(['owner_id' => User::factory()->create(['role' => 'business'])->id])
            ->products()->create(['name' => 'Poulet', 'price' => 4500]);
        $intruder = User::factory()->create(['role' => 'business']);
        Store::factory()->create(['owner_id' => $intruder->id]);

        $this->actingAs($intruder)
            ->get("/business/products/{$product->id}/edit")
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403));
    }

    public function test_validation_messages_are_in_french(): void
    {
        // Messages génériques (lang/fr/validation.php) ; l'inscription a ses propres messages (RegistrationTest).
        $this->post('/login', [])
            ->assertSessionHasErrors([
                'email' => 'Le champ e-mail est obligatoire.',
                'password' => 'Le champ mot de passe est obligatoire.',
            ]);
    }

    public function test_login_failure_message_is_in_french(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
    }
}
