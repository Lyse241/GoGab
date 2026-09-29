<?php

namespace Tests\Feature\Admin;

use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NeighborhoodTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_only_admins_can_manage_neighborhoods(): void
    {
        $neighborhood = Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);

        foreach (['client', 'delivery', 'business'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/admin/neighborhoods')->assertSessionHas('error');
            $this->actingAs($user)->put("/admin/neighborhoods/{$neighborhood->id}", ['name' => 'Akanda', 'zone' => 'Sud']);
        }

        $this->assertSame('Nord', $neighborhood->fresh()->zone);
    }

    public function test_index_lists_neighborhoods_with_zones_and_counts(): void
    {
        $akanda = Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);
        Neighborhood::create(['name' => 'Owendo', 'zone' => 'Sud']);
        User::factory()->create(['role' => 'client', 'neighborhood_id' => $akanda->id]);

        $this->actingAs($this->admin)
            ->get('/admin/neighborhoods')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Neighborhoods/Index')
                ->where('zones', Neighborhood::ZONES)
                ->has('neighborhoods', 2)
                ->where('neighborhoods.0.name', 'Akanda')
                ->where('neighborhoods.0.zone', 'Nord')
                ->where('neighborhoods.0.users_count', 1)
                ->where('neighborhoods.0.stores_count', 0)
                ->where('neighborhoods.0.delivery_profiles_count', 0));
    }

    public function test_admin_adds_a_neighborhood(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/neighborhoods', ['name' => ' Nzeng  Ayong ', 'zone' => 'Est'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('neighborhoods', ['name' => 'Nzeng Ayong', 'zone' => 'Est']);
    }

    public function test_name_must_be_unique_and_zone_known(): void
    {
        Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);

        $this->actingAs($this->admin)
            ->post('/admin/neighborhoods', ['name' => 'Akanda', 'zone' => 'Ouest'])
            ->assertSessionHasErrors(['name', 'zone']);

        $this->assertSame(1, Neighborhood::count());
    }

    public function test_changing_the_zone_is_taken_into_account_everywhere(): void
    {
        $neighborhood = Neighborhood::create(['name' => 'Owendo', 'zone' => 'Sud']);

        $this->actingAs($this->admin)
            ->put("/admin/neighborhoods/{$neighborhood->id}", ['name' => 'Owendo', 'zone' => 'Centre'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '« Owendo » passe de la zone Sud à la zone Centre.');

        $this->assertSame('Centre', $neighborhood->fresh()->zone);

        // La nouvelle zone est celle proposée à l'inscription.
        auth()->logout();
        $this->get('/register/delivery')
            ->assertInertia(fn (Assert $page) => $page
                ->where('neighborhoods.0.name', 'Owendo')
                ->where('neighborhoods.0.zone', 'Centre'));
    }
}
