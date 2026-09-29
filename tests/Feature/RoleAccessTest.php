<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_each_role_to_its_space(): void
    {
        $cases = [
            'client' => route('home', absolute: false),
            'delivery' => route('delivery.dashboard', absolute: false),
            'admin' => route('admin.dashboard', absolute: false),
        ];

        foreach ($cases as $role => $expected) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/dashboard')->assertRedirect($expected);
        }
    }

    public function test_each_role_can_open_its_own_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'delivery']))
            ->get('/delivery')->assertOk();
    }

    public function test_client_is_blocked_from_admin_and_delivery_spaces(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        foreach (['/admin', '/delivery'] as $url) {
            $this->actingAs($client)->get($url)
                ->assertRedirect(route('home', absolute: false))
                ->assertSessionHas('error');
        }
    }

    public function test_delivery_is_blocked_from_admin_space(): void
    {
        $delivery = User::factory()->create(['role' => 'delivery']);

        $this->actingAs($delivery)->get('/admin')
            ->assertRedirect(route('delivery.dashboard', absolute: false))
            ->assertSessionHas('error');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login', absolute: false));
        $this->get('/delivery')->assertRedirect(route('login', absolute: false));
    }

    public function test_role_is_shared_with_inertia(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'delivery']))
            ->get('/delivery')
            ->assertInertia(fn ($page) => $page
                ->component('Delivery/Dashboard')
                ->where('auth.role', 'delivery'));
    }
}
