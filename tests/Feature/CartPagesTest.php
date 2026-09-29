<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\NeighborhoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_is_public_and_can_focus_one_store(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Cart/Index')->where('openStore', null));

        $this->get('/cart?store=7')
            ->assertInertia(fn (Assert $page) => $page->where('openStore', 7));
    }

    public function test_checkout_is_per_store_and_requires_login(): void
    {
        $store = Store::factory()->create();

        $this->get("/checkout/{$store->id}")->assertRedirect(route('login', absolute: false));
    }

    public function test_client_can_open_the_checkout_of_one_store(): void
    {
        $this->seed(NeighborhoodSeeder::class);
        $store = Store::factory()->create(['name' => 'Chez Maman Ngoye', 'logo' => 'stores/logos/logo.png']);

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get("/checkout/{$store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->where('store', ['id' => $store->id, 'name' => 'Chez Maman Ngoye', 'logo' => 'stores/logos/logo.png'])
                ->has('neighborhoods', Neighborhood::count())
                ->where('neighborhoods.0.name', 'Akanda') // tri alphabétique
                ->where('paymentMethods', [
                    ['value' => 'airtel_money', 'label' => 'Airtel Money'],
                    ['value' => 'moov_money', 'label' => 'Moov Money'],
                    ['value' => 'cash', 'label' => 'Espèces à la livraison'],
                ]));
    }

    public function test_checkout_of_an_invisible_store_is_not_found(): void
    {
        $store = Store::factory()->create(['is_active' => false]);

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get("/checkout/{$store->id}")
            ->assertNotFound();
    }

    public function test_old_checkout_url_leads_to_the_carts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/checkout')
            ->assertRedirect(route('cart', absolute: false));
    }

    public function test_admin_and_delivery_cannot_checkout(): void
    {
        $store = Store::factory()->create();

        foreach (['admin', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get("/checkout/{$store->id}")
                ->assertRedirect()
                ->assertSessionHas('error');
        }
    }

    public function test_guest_ordering_logs_in_then_returns_to_that_cart(): void
    {
        $store = Store::factory()->create();
        $client = User::factory()->create(['role' => 'client']);

        $this->get("/cart/{$store->id}/login")->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['email' => $client->email, 'password' => 'password'])
            ->assertRedirect(route('cart', ['store' => $store->id], absolute: false));
    }

    public function test_cart_status_flags_unavailable_and_deleted_products(): void
    {
        $store = Store::factory()->create();
        $available = Product::factory()->for($store)->create();
        $soldOut = Product::factory()->for($store)->unavailable()->create();
        $elsewhere = Product::factory()->create(); // produit d'un autre commerce

        $this->getJson("/stores/{$store->id}/status?products={$available->id},{$soldOut->id},999,{$elsewhere->id}")
            ->assertOk()
            ->assertJsonPath('is_open_now', true)
            ->assertJsonPath('unavailable_product_ids', [$soldOut->id, 999, $elsewhere->id]);
    }
}
