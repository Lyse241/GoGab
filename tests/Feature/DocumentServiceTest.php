<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $documents;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->documents = app(DocumentService::class);
    }

    public function test_files_are_stored_privately_with_a_random_name(): void
    {
        $user = User::factory()->create(['role' => 'delivery']);

        $document = $this->documents->store($user, $this->fakePdf('ma-cin.pdf', 300), DocumentType::IdCard);

        $this->assertStringStartsWith("documents/{$user->id}/id_card-", $document->file_path);
        $this->assertStringNotContainsString('ma-cin', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());

        $this->assertSame('ma-cin.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame(DocumentStatus::Pending, $document->status);
    }

    public function test_a_new_upload_of_the_same_type_replaces_the_previous_one(): void
    {
        $user = User::factory()->create(['role' => 'delivery']);
        $first = $this->documents->store($user, UploadedFile::fake()->image('cin.jpg'), DocumentType::IdCard);
        $first->update(['status' => DocumentStatus::Rejected, 'rejection_reason' => 'Illisible.']);

        $second = $this->documents->store($user, UploadedFile::fake()->image('cin-nette.jpg'), DocumentType::IdCard);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $user->documents()->count());
        $this->assertSame(DocumentStatus::Pending, $second->status);
        $this->assertNull($second->rejection_reason);
        Storage::disk('local')->assertMissing($first->file_path);
        Storage::disk('local')->assertExists($second->file_path);

        // Un autre type ne remplace rien : les 4 photos du véhicule coexistent.
        $this->documents->store($user, UploadedFile::fake()->image('avant.jpg'), DocumentType::VehiclePhotoFront);
        $this->documents->store($user, UploadedFile::fake()->image('arriere.jpg'), DocumentType::VehiclePhotoBack);
        $this->assertSame(3, $user->documents()->count());
    }

    public function test_previous_file_is_kept_if_the_transaction_fails(): void
    {
        $user = User::factory()->create(['role' => 'delivery']);
        $first = $this->documents->store($user, UploadedFile::fake()->image('cin.jpg'), DocumentType::IdCard);

        try {
            DB::transaction(function () use ($user) {
                $this->documents->store($user, UploadedFile::fake()->image('cin-2.jpg'), DocumentType::IdCard);

                throw new RuntimeException('échec simulé');
            });
        } catch (RuntimeException) {
        }

        Storage::disk('local')->assertExists($first->file_path);
        $this->assertSame($first->file_path, $first->fresh()->file_path);
    }

    public function test_only_allowed_formats_and_sizes_pass_validation(): void
    {
        $validate = fn (DocumentType $type, UploadedFile $file) => Validator::make(['file' => $file], ['file' => DocumentService::rules($type)])->passes();

        $this->assertTrue($validate(DocumentType::IdCard, $this->fakePdf('cin.pdf', 100)));
        $this->assertTrue($validate(DocumentType::IdCard, UploadedFile::fake()->image('cin.png')));
        $this->assertTrue($validate(DocumentType::VehiclePhotoFront, UploadedFile::fake()->image('avant.jpg')));

        // Les photos n'acceptent pas le PDF.
        $this->assertFalse($validate(DocumentType::VehiclePhotoFront, $this->fakePdf('avant.pdf', 100)));
        // Plus de 5 Mo.
        $this->assertFalse($validate(DocumentType::IdCard, UploadedFile::fake()->image('cin.jpg')->size(DocumentType::MAX_KILOBYTES + 1)));
        // Mauvais format.
        $this->assertFalse($validate(DocumentType::IdCard, UploadedFile::fake()->create('cin.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')));
        // Faux JPEG : un fichier texte renommé en .jpg est refusé (vrai type contrôlé).
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', 'ceci n’est pas une image <?php echo 1; ?>');
        $this->assertFalse($validate(DocumentType::VehiclePhotoFront, $fake));
    }

    public function test_only_the_owner_and_admins_can_open_a_document(): void
    {
        $owner = User::factory()->create(['role' => 'delivery']);
        $document = $this->documents->store($owner, $this->fakePdf('cin.pdf', 10), DocumentType::IdCard);

        $this->actingAs($owner)->get("/documents/{$document->id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get("/documents/{$document->id}")->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'delivery']))->get("/documents/{$document->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'business']))->get("/documents/{$document->id}")->assertForbidden();

        auth()->logout();
        $this->get("/documents/{$document->id}")->assertRedirect(route('login', absolute: false));
    }
}
