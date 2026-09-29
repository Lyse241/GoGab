<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountDecisionAction;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Document;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Neighborhood $neighborhood;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Gogab']);
        $this->neighborhood = Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre']);
    }

    /**
     * Livreur à moto en attente, avec ses 7 documents (fichiers réels sur le disque privé simulé).
     */
    private function courier(string $state = 'pending'): User
    {
        $courier = User::factory()->{$state}()->create([
            'role' => 'delivery',
            'name' => 'Paul Obame',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Derrière la station Total',
        ]);
        $courier->deliveryProfile()->create([
            'vehicle_type' => VehicleType::Moto,
            'vehicle_brand' => 'Yamaha',
            'plate_number' => 'GA-1234-LBV',
            'license_number' => 'P-1',
            'base_neighborhood_id' => $this->neighborhood->id,
        ]);

        foreach (DocumentType::requiredFor(Role::Delivery, VehicleType::Moto) as $type) {
            $path = "documents/{$courier->id}/{$type->value}.jpg";
            Storage::disk('local')->put($path, 'contenu');
            $courier->documents()->create([
                'type' => $type,
                'file_path' => $path,
                'original_name' => "{$type->value}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 7,
                'status' => DocumentStatus::Pending,
            ]);
        }

        return $courier;
    }

    private function approveAllDocuments(User $account): void
    {
        foreach ($account->documents as $document) {
            $this->actingAs($this->admin)->post("/admin/documents/{$document->id}/approve")->assertSessionHasNoErrors();
        }
    }

    // --- Accès ---

    public function test_only_approved_admins_can_review_and_never_an_admin_account(): void
    {
        $courier = $this->courier();

        $this->actingAs(User::factory()->create(['role' => 'client']))->get("/admin/accounts/{$courier->id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->get("/admin/accounts/{$this->admin->id}")->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/accounts/'.User::factory()->create(['role' => 'admin'])->id)->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/accounts/'.User::factory()->create(['role' => 'admin'])->id.'/approve')->assertForbidden();
    }

    public function test_document_files_are_served_only_to_their_owner_and_admins(): void
    {
        $courier = $this->courier();
        $document = $courier->documents->first();

        $this->get("/documents/{$document->id}")->assertRedirect(route('login', absolute: false));
        $this->actingAs(User::factory()->create(['role' => 'client']))->get("/documents/{$document->id}")->assertForbidden();

        $this->actingAs($courier)->get("/documents/{$document->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get("/documents/{$document->id}")->assertOk();

        Storage::disk('local')->delete($document->file_path);
        $this->actingAs($this->admin)->get("/documents/{$document->id}")->assertNotFound();
    }

    // --- Page de validation ---

    public function test_validation_page_shows_everything_about_a_courier(): void
    {
        $courier = $this->courier();
        $courier->documents()->where('type', DocumentType::VehiclePhotoLeft)->delete(); // un document manque

        $this->actingAs($this->admin)
            ->get("/admin/accounts/{$courier->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Show')
                ->where('account.name', 'Paul Obame')
                ->where('account.neighborhood', 'Louis')
                ->where('deliveryProfile.plate_number', 'GA-1234-LBV')
                ->where('store', null)
                ->has('documents', 7)
                ->where('documents.0.type', 'id_card')
                ->where('documents.0.status', 'pending')
                ->where('documents.0.url', route('documents.show', $courier->documents()->where('type', DocumentType::IdCard)->value('id')))
                ->where('documents.5.type', 'vehicle_photo_left')
                ->where('documents.5.missing', true)
                ->where('decision.can_approve', false)
                ->has('decision.missing', 7));
    }

    // --- Documents ---

    public function test_documents_can_be_approved_or_rejected_with_a_reason(): void
    {
        $courier = $this->courier();
        [$first, $second] = $courier->documents->take(2)->all();

        $this->actingAs($this->admin)->post("/admin/documents/{$first->id}/approve")->assertSessionHas('success');
        $this->assertSame(DocumentStatus::Approved, $first->fresh()->status);
        $this->assertSame($this->admin->id, $first->fresh()->reviewed_by);

        $this->actingAs($this->admin)->post("/admin/documents/{$second->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Indiquez le motif du refus : il sera montré à l’utilisateur.']);
        $this->assertSame(DocumentStatus::Pending, $second->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/documents/{$second->id}/reject", ['reason' => 'Photo floue, permis illisible.']);
        $this->assertSame(DocumentStatus::Rejected, $second->fresh()->status);
        $this->assertSame('Photo floue, permis illisible.', $second->fresh()->rejection_reason);

        $this->assertSame(
            [AccountDecisionAction::DocumentRejected, AccountDecisionAction::DocumentApproved],
            $courier->decisions()->pluck('action')->all(),
        );
        $this->assertSame('Photo floue, permis illisible.', $courier->decisions()->first()->note);
        $this->assertSame($this->admin->id, $courier->decisions()->first()->actor_id);
    }

    // --- Décision sur le compte ---

    public function test_account_cannot_be_approved_until_required_documents_are_approved(): void
    {
        $courier = $this->courier();

        $this->actingAs($this->admin)->post("/admin/accounts/{$courier->id}/approve")
            ->assertSessionHasErrors('account');
        $this->assertSame(AccountStatus::Pending, $courier->fresh()->account_status);

        $this->approveAllDocuments($courier);

        $this->actingAs($this->admin)->post("/admin/accounts/{$courier->id}/approve")->assertSessionHas('success');

        $courier->refresh();
        $this->assertSame(AccountStatus::Approved, $courier->account_status);
        $this->assertNotNull($courier->approved_at);
        $this->assertSame($this->admin->id, $courier->approved_by);
        $this->assertSame(AccountDecisionAction::AccountApproved, $courier->decisions()->first()->action);

        $welcome = $courier->notifications()->sole();
        $this->assertSame('Bienvenue parmi les livreurs Gogab !', $welcome->data['title']);
        $this->assertSame('success', $welcome->data['type']);
        $this->assertSame('/delivery', $welcome->data['url']);

        // Déjà validé : pas de seconde validation.
        $this->actingAs($this->admin)->post("/admin/accounts/{$courier->id}/approve")->assertSessionHasErrors('account');
    }

    public function test_client_without_documents_can_be_approved_directly(): void
    {
        $client = User::factory()->pending()->create(['role' => 'client']);

        $this->actingAs($this->admin)->get("/admin/accounts/{$client->id}")
            ->assertInertia(fn (Assert $page) => $page->has('documents', 0)->where('decision.can_approve', true));

        $this->actingAs($this->admin)->post("/admin/accounts/{$client->id}/approve")->assertSessionHasNoErrors();

        $this->assertTrue($client->fresh()->isApproved());
        $this->assertSame('/', $client->notifications()->sole()->data['url']);
    }

    public function test_approving_a_business_puts_its_store_online(): void
    {
        $owner = User::factory()->pending()->create(['role' => 'business']);
        $store = Store::factory()->create(['owner_id' => $owner->id, 'is_active' => false, 'name' => 'Chez Tante Marie']);
        foreach (DocumentType::requiredFor(Role::Business) as $type) {
            $owner->documents()->create([
                'type' => $type, 'file_path' => "documents/{$owner->id}/{$type->value}.pdf", 'original_name' => 'x.pdf',
                'mime_type' => 'application/pdf', 'size' => 10, 'status' => DocumentStatus::Approved,
            ]);
        }

        $this->actingAs($this->admin)->get("/admin/accounts/{$owner->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.name', 'Chez Tante Marie')
                ->where('store.is_active', false)
                ->has('store.opening_hours', 7));

        $this->actingAs($this->admin)->post("/admin/accounts/{$owner->id}/approve")->assertSessionHasNoErrors();

        $this->assertTrue($store->fresh()->is_active);
        $this->assertTrue(Store::visible()->whereKey($store->id)->exists());
        $this->assertSame('Votre commerce est en ligne !', $owner->notifications()->sole()->data['title']);
    }

    public function test_rejecting_an_account_requires_a_reason_and_notifies_the_user(): void
    {
        $courier = $this->courier();

        $this->actingAs($this->admin)->post("/admin/accounts/{$courier->id}/reject", ['reason' => 'non'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)->post("/admin/accounts/{$courier->id}/reject", ['reason' => 'Le permis est expiré depuis 2024.'])
            ->assertSessionHas('success');

        $courier->refresh();
        $this->assertSame(AccountStatus::Rejected, $courier->account_status);
        $this->assertSame('Le permis est expiré depuis 2024.', $courier->rejection_reason);

        $notification = $courier->notifications()->sole();
        $this->assertSame('Votre inscription n’a pas été validée', $notification->data['title']);
        $this->assertStringContainsString('Le permis est expiré depuis 2024.', $notification->data['message']);
        $this->assertSame('/account/rejected', $notification->data['url']);

        $this->actingAs($this->admin)->get("/admin/accounts/{$courier->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('history.0.action', 'account_rejected')
                ->where('history.0.actor', 'Admin Gogab')
                ->where('history.0.note', 'Le permis est expiré depuis 2024.'));
    }

    // --- Correction par l'utilisateur ---

    public function test_rejected_courier_corrects_and_resends_rejected_documents(): void
    {
        Storage::fake('local');
        $courier = $this->courier();
        $idCard = $courier->documents()->where('type', DocumentType::IdCard)->sole();
        $oldPath = $idCard->file_path;

        $this->actingAs($this->admin)->post("/admin/documents/{$idCard->id}/reject", ['reason' => 'CIN illisible.']);
        $this->actingAs($this->admin)->post("/admin/accounts/{$courier->id}/reject", ['reason' => 'Merci de renvoyer une CIN lisible.']);
        $courier->refresh(); // actingAs() réutilise l'objet en mémoire, pas la base

        $this->actingAs($courier)->get('/account/rejected/correction')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Correction')
                ->where('rejectionReason', 'Merci de renvoyer une CIN lisible.')
                ->where('values.plate_number', 'GA-1234-LBV')
                ->has('documents', 7)
                ->where('documents.0.spec.type', 'id_card')
                ->where('documents.0.must_resend', true)
                ->where('documents.0.current.rejection_reason', 'CIN illisible.')
                ->where('documents.1.must_resend', false));

        $payload = [
            'name' => 'Paul Obame Nze',
            'phone' => '077 55 66 77',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Derrière la station Total, portail noir',
            'vehicle_brand' => 'Yamaha Crypton',
            'plate_number' => 'ga-1234-lbv',
            'license_number' => 'P-1',
            'base_neighborhood_id' => $this->neighborhood->id,
        ];

        // La CIN refusée doit être renvoyée.
        $this->actingAs($courier)->post('/account/rejected/correction', $payload)
            ->assertSessionHasErrors(['documents.id_card' => "Document manquant : Carte d'identité (CIN)."]);

        $this->actingAs($courier)
            ->post('/account/rejected/correction', [...$payload, 'documents' => ['id_card' => $this->fakePdf('cin.pdf', 100)]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.pending', absolute: false));

        $courier->refresh();
        $this->assertSame(AccountStatus::Pending, $courier->account_status);
        $this->assertNull($courier->rejection_reason);
        $this->assertSame('Paul Obame Nze', $courier->name);
        $this->assertSame('Yamaha Crypton', $courier->deliveryProfile->vehicle_brand);

        $idCard->refresh();
        $this->assertSame(DocumentStatus::Pending, $idCard->status);
        $this->assertNull($idCard->rejection_reason);
        $this->assertSame('cin.pdf', $idCard->original_name);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($idCard->file_path);
        $this->assertSame(7, $courier->documents()->count());

        $this->assertSame(AccountDecisionAction::Resubmitted, $courier->decisions()->first()->action);
        $this->assertSame('Dossier corrigé à revalider', $this->admin->notifications()->sole()->data['title']);
        $this->assertSame("/admin/accounts/{$courier->id}", $this->admin->notifications()->sole()->data['url']);
    }

    public function test_rejected_business_corrects_its_store(): void
    {
        $owner = User::factory()->rejected('Adresse imprécise.')->create(['role' => 'business', 'neighborhood_id' => $this->neighborhood->id]);
        $store = Store::factory()->create(['owner_id' => $owner->id, 'is_active' => false]);

        $this->actingAs($owner)->post('/account/rejected/correction', [
            'name' => $owner->name,
            'phone' => '074 11 22 33',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Face à la pharmacie, bâtiment bleu',
            'store_name' => 'Nouveau nom',
            'category_id' => $store->category_id,
            'store_phone' => '074 11 22 33',
            'opening_hours' => StoreHours::everyDay('09:00', '21:00', [7]),
            'documents' => [
                'business_registration' => $this->fakePdf('rccm.pdf', 50),
                'tax_id' => $this->fakePdf('nif.pdf', 50),
                'id_card' => $this->fakePdf('cin.pdf', 50),
            ],
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('Nouveau nom', $store->name);
        $this->assertSame('Face à la pharmacie, bâtiment bleu', $store->address_landmarks);
        $this->assertFalse($store->is_active);
        $this->assertNull($owner->fresh()->address_landmarks);
        $this->assertSame(3, $owner->documents()->count());
        $this->assertSame(AccountStatus::Pending, $owner->fresh()->account_status);
    }

    public function test_only_a_rejected_user_can_resubmit(): void
    {
        $pending = $this->courier();

        $this->actingAs($pending)->get('/account/rejected/correction')->assertRedirect('/account/pending');
        $this->actingAs($pending)->post('/account/rejected/correction', [])->assertForbidden();
    }

    public function test_registration_starts_the_history(): void
    {
        $this->post('/register/client', [
            'name' => 'Marie Ndong',
            'phone' => '077 12 34 56',
            'email' => 'marie@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'neighborhood_id' => $this->neighborhood->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
        ]);

        $user = User::where('email', 'marie@example.com')->sole();
        $decision = $user->decisions()->sole();
        $this->assertSame(AccountDecisionAction::Submitted, $decision->action);
        $this->assertSame($user->id, $decision->actor_id);
        $this->assertSame("/admin/accounts/{$user->id}", $this->admin->notifications()->sole()->data['url']);
    }
}
