<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\User;
use Database\Seeders\NeighborhoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_is_public(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Cart/Index'));
    }

    public function test_checkout_requires_login(): void
    {
        $this->get('/checkout')->assertRedirect(route('login', absolute: false));
    }

    public function test_client_can_open_checkout_with_neighborhoods_and_payment_methods(): void
    {
        $this->seed(NeighborhoodSeeder::class);

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->has('neighborhoods', Neighborhood::count())
                ->where('neighborhoods.0.name', 'Akanda') // tri alphabétique
                ->where('paymentMethods', [
                    ['value' => 'airtel_money', 'label' => 'Airtel Money'],
                    ['value' => 'moov_money', 'label' => 'Moov Money'],
                    ['value' => 'cash_on_delivery', 'label' => 'Paiement à la livraison'],
                ]));
    }

    public function test_admin_and_delivery_cannot_checkout(): void
    {
        foreach (['admin', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/checkout')
                ->assertRedirect()
                ->assertSessionHas('error');
        }
    }
}
