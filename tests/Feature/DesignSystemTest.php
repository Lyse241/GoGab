<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_system_page_is_hidden_outside_local(): void
    {
        $this->get('/design-system')->assertNotFound();
    }

    public function test_design_system_page_is_available_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/design-system')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('DesignSystem'));
    }

    public function test_status_labels_and_colors_are_shared_from_the_enums(): void
    {
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('statuses.order', 10)
                ->where('statuses.order.en_attente', ['label' => 'En attente', 'color' => 'yellow'])
                ->where('statuses.order.livree', ['label' => 'Livrée', 'color' => 'green'])
                ->has('statuses.account', 4)
                ->where('statuses.account.pending', ['label' => 'En attente de validation', 'color' => 'yellow'])
                ->where('statuses.account.approved', ['label' => 'Validé', 'color' => 'green']));
    }

    public function test_neighborhoods_and_role_label_are_shared_for_the_header(): void
    {
        Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre']);
        Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);

        $this->actingAs(User::factory()->create(['role' => 'delivery']))
            ->get('/delivery')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.role', 'delivery')
                ->where('auth.role_label', 'Livreur')
                ->has('neighborhoods', 2)
                ->where('neighborhoods.0', fn ($neighborhood) => $neighborhood['name'] === 'Akanda' && $neighborhood['zone'] === 'Nord'));
    }
}
