<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Neighborhood $neighborhood;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->neighborhood = Neighborhood::create(['name' => 'Nombakélé', 'zone' => 'Centre']);
        $this->category = Category::create(['name' => 'Restaurant', 'slug' => 'restaurant']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Marie Ngoye',
            'phone' => '077 20 30 40',
            'email' => 'marie@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'store_name' => 'Chez Tante Marie',
            'category_id' => $this->category->id,
            'description' => 'Cuisine gabonaise maison.',
            'store_phone' => '+241 74 20 30 40',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Face à la pharmacie, bâtiment bleu',
            'opening_hours' => StoreHours::everyDay('10:00', '22:00', [7]),
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
            'documents' => [
                'business_registration' => $this->fakePdf('rccm.pdf', 200),
                'tax_id' => UploadedFile::fake()->image('nif.jpg'),
                'id_card' => $this->fakePdf('cin.pdf', 200),
            ],
            ...$overrides,
        ];
    }

    public function test_required_and_optional_business_documents(): void
    {
        $this->assertSame(
            [DocumentType::BusinessRegistration, DocumentType::TaxId, DocumentType::IdCard],
            DocumentType::requiredFor(Role::Business),
        );
        $this->assertSame([DocumentType::HealthPermit, DocumentType::Other], DocumentType::optionalFor(Role::Business));
    }

    public function test_registration_page_receives_categories_hours_and_documents(): void
    {
        $this->get('/register/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register/Business')
                ->has('categories', 1)
                ->has('neighborhoods', 1)
                ->has('openingHours', 7)
                ->where('requiredDocuments', ['business_registration', 'tax_id', 'id_card'])
                ->where('optionalDocuments', ['health_permit', 'other']));
    }

    public function test_business_registers_with_an_inactive_store(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = $this->payload();
        $payload['documents']['health_permit'] = $this->fakePdf('sanitaire.pdf', 100);

        $response = $this->post('/register/business', $payload);

        $user = User::where('email', 'marie@example.com')->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('account.pending', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Business, $user->role);
        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertSame($this->neighborhood->id, $user->neighborhood_id);

        $store = $user->store;
        $this->assertSame('Chez Tante Marie', $store->name);
        $this->assertSame($this->category->id, $store->category_id);
        $this->assertSame('074 20 30 40', $store->phone); // normalisé
        $this->assertSame('Face à la pharmacie, bâtiment bleu', $store->address_landmarks);
        $this->assertFalse($store->is_active);
        $this->assertTrue($store->is_open);
        $this->assertStringStartsWith('stores/logos/', $store->logo);
        Storage::disk('public')->assertExists($store->logo);

        $store->load('openingHours');
        $this->assertCount(7, $store->openingHours);
        $this->assertTrue($store->openingHours->firstWhere('day_of_week', 7)->is_closed);
        $this->assertSame('10:00:00', $store->openingHours->firstWhere('day_of_week', 1)->opens_at);

        // 3 obligatoires + l'autorisation sanitaire facultative, sur le disque privé.
        $this->assertEqualsCanonicalizing(
            ['business_registration', 'tax_id', 'id_card', 'health_permit'],
            $user->documents->map(fn ($document) => $document->type->value)->all(),
        );
        $user->documents->each(fn ($document) => Storage::disk('local')->assertExists($document->file_path));

        $notification = $admin->notifications()->sole();
        $this->assertSame('Nouvelle entreprise à valider', $notification->data['title']);
        $this->assertStringContainsString('Chez Tante Marie', $notification->data['message']);

        $this->get('/account/pending')
            ->assertInertia(fn (Assert $page) => $page
                ->where('justRegistered', true)
                ->where('submission.store.name', 'Chez Tante Marie')
                ->where('submission.store.category', 'Restaurant')
                ->has('submission.documents', 4));
    }

    public function test_pending_store_is_invisible_to_the_public(): void
    {
        $this->post('/register/business', $this->payload())->assertSessionHasNoErrors();
        $store = Store::where('name', 'Chez Tante Marie')->sole();
        $visible = Store::factory()->create(['name' => 'Commerce validé']);
        $store->products()->create(['name' => 'Poulet nyembwe', 'price' => 4500]);
        auth()->logout();

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stores', 1)
                ->where('stores.0.id', $visible->id));

        $this->get('/?q=nyembwe')->assertInertia(fn (Assert $page) => $page->has('stores', 0));
        $this->get("/stores/{$store->id}")->assertNotFound();
        $this->getJson("/stores/{$store->id}/status")->assertNotFound();
        $this->assertFalse(Store::visible()->whereKey($store->id)->exists());
    }

    public function test_required_documents_and_fields_are_checked(): void
    {
        $payload = $this->payload();
        unset($payload['documents']['tax_id']);

        $this->post('/register/business', $payload)
            ->assertSessionHasErrors(['documents.tax_id' => "Document manquant : Numéro d'identification fiscale (NIF)."]);

        $this->post('/register/business', $this->payload(['store_name' => '', 'category_id' => 999, 'logo' => $this->fakePdf('logo.pdf', 10)]))
            ->assertSessionHasErrors([
                'store_name' => 'Indiquez le nom commercial.',
                'category_id' => 'Cette catégorie n’existe pas.',
                'logo',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('stores', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_opening_hours_must_be_complete(): void
    {
        $hours = StoreHours::everyDay('10:00', '22:00');
        $hours[2]['closes_at'] = '';

        $this->post('/register/business', $this->payload(['opening_hours' => $hours]))
            ->assertSessionHasErrors(['opening_hours.2.closes_at' => 'Mercredi : indiquez l’heure de fermeture, ou cochez « Fermé ce jour ».']);

        $this->post('/register/business', $this->payload(['opening_hours' => []]))
            ->assertSessionHasErrors('opening_hours');
    }

    public function test_steps_are_checked_before_moving_on(): void
    {
        User::factory()->create(['phone' => '077 20 30 40']);
        $payload = $this->payload();
        unset($payload['documents'], $payload['logo']);

        $this->postJson('/register/business/check', ['step' => 1, ...$payload])
            ->assertJsonValidationErrors(['phone' => 'Ce numéro de téléphone est déjà utilisé par un autre compte.']);

        $this->postJson('/register/business/check', ['step' => 2, ...$payload])->assertNoContent();

        $hours = StoreHours::everyDay('10:00', '22:00');
        $hours[0]['opens_at'] = '';
        $this->postJson('/register/business/check', ['step' => 2, ...$payload, 'opening_hours' => $hours])
            ->assertJsonValidationErrors(['opening_hours.0.opens_at']);
    }
}
