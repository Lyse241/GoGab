<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Parcours critique 1 — inscriptions : client, livreur (avec documents) et entreprise (avec
 * documents) créent un compte en attente et préviennent les admins validés.
 */
class RegistrationFlowTest extends TestCase
{
    use CriticalFlowHelpers;
    use RefreshDatabase;

    private User $admin;

    private User $pendingAdmin;

    private Neighborhood $glass;

    private Neighborhood $louis;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->pendingAdmin = User::factory()->pending()->create(['role' => 'admin']);
        $this->glass = Neighborhood::create(['name' => 'Glass', 'zone' => 'Centre']);
        $this->louis = Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre']);
    }

    public function test_a_client_registers_as_pending_and_the_admins_are_notified(): void
    {
        $this->post('/register/client', $this->clientRegistration($this->glass))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.pending', absolute: false));

        $client = User::where('email', 'awa@example.com')->sole();
        $this->assertAuthenticatedAs($client);
        $this->assertSame(Role::Client, $client->role);
        $this->assertSame(AccountStatus::Pending, $client->account_status);

        $this->assertSame(['Nouveau compte client à valider'], $this->admin->notifications->pluck('data.title')->all());
        $this->assertSame(route('admin.accounts.show', $client, absolute: false), $this->admin->notifications->first()->data['url']);
        $this->assertCount(0, $this->pendingAdmin->notifications);
    }

    public function test_a_courier_registers_with_documents_as_pending_and_the_admins_are_notified(): void
    {
        $this->post('/register/delivery', $this->courierRegistration($this->glass, $this->louis))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.pending', absolute: false));

        $courier = User::where('email', 'paul@example.com')->sole();
        $this->assertSame(Role::Delivery, $courier->role);
        $this->assertSame(AccountStatus::Pending, $courier->account_status);
        $this->assertSame(VehicleType::Moto, $courier->deliveryProfile->vehicle_type);
        $this->assertSame($this->louis->id, $courier->deliveryProfile->base_neighborhood_id);

        // Les 7 documents obligatoires, en attente de vérification, sur le disque privé.
        $required = DocumentType::requiredFor(Role::Delivery, VehicleType::Moto);
        $this->assertCount(count($required), $courier->documents);
        foreach ($courier->documents as $document) {
            $this->assertContains($document->type, $required);
            $this->assertSame(DocumentStatus::Pending, $document->status);
            Storage::disk('local')->assertExists($document->file_path);
        }

        $this->assertSame(['Nouveau livreur à valider'], $this->admin->notifications->pluck('data.title')->all());
        $this->assertCount(0, $this->pendingAdmin->notifications);
    }

    public function test_a_business_registers_with_documents_and_an_offline_store_and_the_admins_are_notified(): void
    {
        $category = Category::factory()->create(['name' => 'Restaurant']);

        $this->post('/register/business', $this->businessRegistration($this->louis, $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.pending', absolute: false));

        $owner = User::where('email', 'marie@example.com')->sole();
        $this->assertSame(Role::Business, $owner->role);
        $this->assertSame(AccountStatus::Pending, $owner->account_status);

        $store = $owner->store;
        $this->assertSame('Chez Tante Marie', $store->name);
        $this->assertFalse($store->is_active);
        $this->assertFalse($store->isVisible());

        $this->assertEqualsCanonicalizing(
            DocumentType::requiredFor(Role::Business),
            $owner->documents->pluck('type')->all(),
        );
        $owner->documents->each(fn ($document) => Storage::disk('local')->assertExists($document->file_path));

        $this->assertSame(['Nouvelle entreprise à valider'], $this->admin->notifications->pluck('data.title')->all());
        $this->assertCount(0, $this->pendingAdmin->notifications);
    }

    public function test_a_registration_with_a_missing_document_creates_nothing(): void
    {
        $payload = $this->courierRegistration($this->glass, $this->louis);
        unset($payload['documents']['driving_license']);

        $this->post('/register/delivery', $payload)->assertSessionHasErrors('documents.driving_license');

        $this->assertDatabaseMissing('users', ['email' => 'paul@example.com']);
        $this->assertDatabaseCount('documents', 0);
        $this->assertCount(0, $this->admin->notifications);
    }
}
