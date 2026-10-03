<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
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

    public function test_the_back_button_leads_to_the_space_of_the_role(): void
    {
        $this->get('/page-qui-n-existe-pas')
            ->assertInertia(fn (Assert $page) => $page->where('home', ['url' => route('home'), 'label' => 'Retour à l’accueil']));

        $cases = [
            'client' => [route('home'), 'Retour au catalogue'],
            'delivery' => [route('delivery.dashboard'), 'Retour à mes courses'],
            'business' => [route('business.dashboard'), 'Retour à mon commerce'],
            'admin' => [route('admin.dashboard'), 'Retour à l’administration'],
        ];

        foreach ($cases as $role => [$url, $label]) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/page-qui-n-existe-pas')
                ->assertNotFound()
                ->assertInertia(fn (Assert $page) => $page->component('Error')->where('home', ['url' => $url, 'label' => $label]));
        }

        $this->actingAs(User::factory()->pending()->create(['role' => 'delivery']))
            ->get('/page-qui-n-existe-pas')
            ->assertInertia(fn (Assert $page) => $page->where('home', ['url' => route('account.pending'), 'label' => 'Suivre mon inscription']));
    }

    public function test_expired_page_shows_the_419_page_or_goes_back_for_an_inertia_form(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));

        $this->post('/_test/expired')
            ->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 419));

        $this->from('/cart')
            ->post('/_test/expired', [], ['X-Inertia' => 'true'])
            ->assertRedirect('/cart')
            ->assertSessionHas('error', 'La page a expiré. Réessayez.');
    }

    public function test_server_errors_show_the_500_page_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/boom', fn () => throw new \RuntimeException('Boom'));

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/_test/boom')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 500)->where('home.label', 'Retour au catalogue'));

        // Les appels JSON gardent une réponse JSON.
        $this->getJson('/_test/boom')->assertStatus(500)->assertJsonStructure(['message']);
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
