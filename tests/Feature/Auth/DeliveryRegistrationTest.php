<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\DeliveryProfile;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Neighborhood $home;

    private Neighborhood $base;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->home = Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);
        $this->base = Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre']);
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function documents(VehicleType $vehicle): array
    {
        $files = [];

        foreach (DocumentType::requiredFor(Role::Delivery, $vehicle) as $type) {
            $files[$type->value] = $type->isPhoto()
                ? UploadedFile::fake()->image("{$type->value}.jpg", 800, 600)
                : $this->fakePdf("{$type->value}.pdf", 300);
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(VehicleType $vehicle = VehicleType::Moto, array $overrides = []): array
    {
        return [
            'name' => 'Paul Obame',
            'phone' => '077 55 44 33',
            'email' => 'paul@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'neighborhood_id' => $this->home->id,
            'address_landmarks' => 'Derrière la station Total, maison jaune',
            'vehicle_type' => $vehicle->value,
            'vehicle_brand' => 'Yamaha Crypton',
            'plate_number' => 'ga-1234-lbv',
            'license_number' => 'P-0456789',
            'base_neighborhood_id' => $this->base->id,
            'documents' => $this->documents($vehicle),
            ...$overrides,
        ];
    }

    // --- Documents obligatoires (source unique) ---

    public function test_required_documents_depend_on_the_vehicle(): void
    {
        $photos = [
            DocumentType::VehiclePhotoFront,
            DocumentType::VehiclePhotoBack,
            DocumentType::VehiclePhotoLeft,
            DocumentType::VehiclePhotoRight,
        ];

        foreach ([VehicleType::Moto, VehicleType::Car] as $vehicle) {
            $this->assertSame(
                [DocumentType::IdCard, DocumentType::DrivingLicense, DocumentType::LicensePlatePhoto, ...$photos],
                DocumentType::requiredFor(Role::Delivery, $vehicle),
            );
        }

        $this->assertSame([DocumentType::IdCard, ...$photos], DocumentType::requiredFor(Role::Delivery, VehicleType::Bicycle));
        $this->assertSame([], DocumentType::requiredFor(Role::Client));

        // Photos : images uniquement ; CIN et permis : images ou PDF.
        $this->assertSame(['jpg', 'jpeg', 'png'], DocumentType::VehiclePhotoLeft->extensions());
        $this->assertSame(['jpg', 'jpeg', 'png', 'pdf'], DocumentType::IdCard->extensions());
    }

    public function test_registration_page_receives_the_requirements_from_the_server(): void
    {
        $this->get('/register/delivery')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register/Delivery')
                ->has('neighborhoods', 2)
                ->has('vehicleTypes', 3)
                ->has('requiredDocuments.moto', 7)
                ->has('requiredDocuments.car', 7)
                ->has('requiredDocuments.bicycle', 5)
                ->where('documentTypes.license_plate_photo.photo', true)
                ->where('documentTypes.license_plate_photo.accept', 'image/jpeg,image/png')
                ->where('documentTypes.id_card.accept', 'image/jpeg,image/png,application/pdf'));
    }

    // --- Inscription complète ---

    public function test_courier_registers_with_vehicle_and_documents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->post('/register/delivery', $this->payload());

        $user = User::where('email', 'paul@example.com')->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('account.pending', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->assertSame(Role::Delivery, $user->role);
        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertSame($this->home->id, $user->neighborhood_id);

        $profile = $user->deliveryProfile;
        $this->assertSame(VehicleType::Moto, $profile->vehicle_type);
        $this->assertSame('GA-1234-LBV', $profile->plate_number);
        $this->assertSame('P-0456789', $profile->license_number);
        $this->assertSame($this->base->id, $profile->base_neighborhood_id);
        $this->assertFalse($profile->is_available);

        // 7 documents, un par type : les 4 photos ne se remplacent pas.
        $documents = $user->documents;
        $this->assertCount(7, $documents);
        $this->assertCount(7, $documents->pluck('type')->unique());
        foreach ($documents as $document) {
            $this->assertSame(DocumentStatus::Pending, $document->status);
            $this->assertStringStartsWith("documents/{$user->id}/{$document->type->value}-", $document->file_path);
            Storage::disk('local')->assertExists($document->file_path);
        }

        $notification = $admin->notifications()->sole();
        $this->assertSame('Nouveau livreur à valider', $notification->data['title']);
        $this->assertStringContainsString('Paul Obame', $notification->data['message']);

        $this->get('/account/pending')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Pending')
                ->where('justRegistered', true)
                ->where('submission.delivery_profile.plate_number', 'GA-1234-LBV')
                ->has('submission.documents', 7));
    }

    public function test_bicycle_needs_neither_plate_nor_license(): void
    {
        $payload = $this->payload(VehicleType::Bicycle, ['vehicle_brand' => '', 'plate_number' => '', 'license_number' => '']);
        // Un fichier en trop (permis) est ignoré.
        $payload['documents']['driving_license'] = $this->fakePdf('permis.pdf', 100);

        $this->post('/register/delivery', $payload)->assertSessionHasNoErrors();

        $user = User::where('email', 'paul@example.com')->sole();
        $this->assertNull($user->deliveryProfile->plate_number);
        $this->assertNull($user->deliveryProfile->license_number);
        $this->assertEqualsCanonicalizing(
            array_map(fn (DocumentType $type) => $type->value, DocumentType::requiredFor(Role::Delivery, VehicleType::Bicycle)),
            $user->documents->map(fn ($document) => $document->type->value)->all(),
        );
    }

    public function test_plate_and_license_are_required_for_a_moto(): void
    {
        $this->post('/register/delivery', $this->payload(VehicleType::Moto, ['plate_number' => '', 'license_number' => '']))
            ->assertSessionHasErrors([
                'plate_number' => 'Indiquez le numéro de plaque d’immatriculation.',
                'license_number' => 'Indiquez le numéro de votre permis de conduire.',
            ]);

        $this->assertGuest();
    }

    public function test_a_missing_document_is_named_and_nothing_is_saved(): void
    {
        $payload = $this->payload();
        unset($payload['documents']['vehicle_photo_back']);

        $this->post('/register/delivery', $payload)
            ->assertSessionHasErrors(['documents.vehicle_photo_back' => 'Document manquant : Photo du véhicule (arrière).']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('delivery_profiles', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertGuest();
    }

    public function test_photos_only_accept_images_but_id_card_accepts_pdf(): void
    {
        $payload = $this->payload();
        $payload['documents']['license_plate_photo'] = $this->fakePdf('plaque.pdf', 100);

        $this->post('/register/delivery', $payload)
            ->assertSessionHasErrors(['documents.license_plate_photo' => "Photo de la plaque d'immatriculation : format non accepté (JPG ou PNG uniquement)."])
            ->assertSessionDoesntHaveErrors('documents.id_card'); // PDF accepté pour la CIN

        $payload = $this->payload();
        $payload['documents']['vehicle_photo_front'] = UploadedFile::fake()->image('avant.jpg')->size(DocumentType::MAX_KILOBYTES + 1);

        $this->post('/register/delivery', $payload)
            ->assertSessionHasErrors(['documents.vehicle_photo_front' => 'Photo du véhicule (avant) : fichier trop lourd (5 Mo maximum).']);
    }

    public function test_plate_number_must_be_unique(): void
    {
        $other = User::factory()->create(['role' => 'delivery']);
        DeliveryProfile::create([
            'user_id' => $other->id,
            'vehicle_type' => VehicleType::Moto,
            'plate_number' => 'GA-1234-LBV',
            'base_neighborhood_id' => $this->base->id,
        ]);

        $this->post('/register/delivery', $this->payload())
            ->assertSessionHasErrors(['plate_number' => 'Cette plaque est déjà enregistrée pour un autre livreur.']);
    }

    // --- Vérification étape par étape ---

    public function test_each_step_can_be_checked_before_moving_on(): void
    {
        User::factory()->create(['email' => 'paul@example.com']);
        $payload = $this->payload();
        unset($payload['documents']);

        $this->postJson('/register/delivery/check', ['step' => 1, ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Un compte existe déjà avec cette adresse e-mail. Connectez-vous plutôt.'])
            ->assertJsonMissingValidationErrors(['plate_number']);

        $this->postJson('/register/delivery/check', ['step' => 2, ...$payload])->assertNoContent();

        $this->postJson('/register/delivery/check', ['step' => 2, ...$payload, 'plate_number' => ''])
            ->assertJsonValidationErrors('plate_number');

        $this->postJson('/register/delivery/check', ['step' => 3])->assertJsonValidationErrors('step');
        $this->assertDatabaseCount('delivery_profiles', 0);
    }
}
