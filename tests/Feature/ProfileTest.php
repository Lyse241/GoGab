<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\AccountDeletionService;
use App\Services\AccountValidationService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * « Mon profil » pour tous les rôles : informations, mot de passe, documents (livreur,
 * entreprise), suppression du compte par anonymisation.
 */
class ProfileTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->setUpOrderWorld();
    }

    private function info(User $user, array $overrides = []): array
    {
        return [
            'name' => 'Nouveau Nom',
            'phone' => '+241 77 11 22 33',
            'email' => 'nouveau@gogab.ga',
            'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Derrière la boulangerie, portail vert',
            ...$overrides,
        ];
    }

    private function sendDocument(User $user, DocumentType $type, ?UploadedFile $file = null)
    {
        return $this->actingAs($user)
            ->from('/profile')
            ->post(route('profile.documents.replace', $type->value), ['file' => $file ?? $this->fakePdf()]);
    }

    private function giveDocuments(User $user, string $status = 'approved'): void
    {
        foreach (app(AccountValidationService::class)->requiredDocuments($user) as $type) {
            $user->documents()->create([
                'type' => $type,
                'file_path' => "documents/{$user->id}/{$type->value}.pdf",
                'original_name' => "{$type->value}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 1000,
                'status' => $status,
            ]);
            Storage::disk('local')->put("documents/{$user->id}/{$type->value}.pdf", 'x');
        }
    }

    // --- Informations et mot de passe, pour chaque rôle ---

    public function test_every_role_sees_the_profile_in_its_own_layout(): void
    {
        foreach ([$this->client, $this->owner, $this->courier, $this->admin] as $user) {
            $this->actingAs($user)
                ->get('/profile')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Profile/Edit')
                    ->where('auth.role', $user->role->value)
                    ->where('profile.email', $user->email)
                    ->where('profile.address_required', $user->isClient())
                    ->where('documents', fn ($documents) => ($documents !== null) === ($user->isDelivery() || $user->isBusiness())));
        }
    }

    public function test_every_role_can_update_its_information(): void
    {
        foreach ([$this->client, $this->owner, $this->courier, $this->admin] as $index => $user) {
            $this->actingAs($user)
                ->patch('/profile', $this->info($user, ['email' => "nouveau{$index}@gogab.ga", 'phone' => "077 11 22 3{$index}"]))
                ->assertSessionHasNoErrors()
                ->assertRedirect('/profile')
                ->assertSessionHas('success');

            $user->refresh();
            $this->assertSame('Nouveau Nom', $user->name);
            $this->assertSame("nouveau{$index}@gogab.ga", $user->email);
            $this->assertSame("077 11 22 3{$index}", $user->phone);
            $this->assertNull($user->email_verified_at); // e-mail changé : à revérifier
        }
    }

    public function test_profile_rules(): void
    {
        $this->actingAs($this->client)
            ->patch('/profile', $this->info($this->client, ['phone' => '12345', 'email' => $this->owner->email, 'neighborhood_id' => '', 'address_landmarks' => '']))
            ->assertSessionHasErrors(['phone', 'email', 'neighborhood_id', 'address_landmarks']);

        // Quartier facultatif hors client ; e-mail inchangé : vérification conservée.
        $this->actingAs($this->courier)
            ->patch('/profile', $this->info($this->courier, ['email' => $this->courier->email, 'neighborhood_id' => '', 'address_landmarks' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($this->courier->fresh()->email_verified_at);
    }

    public function test_every_role_can_change_its_password(): void
    {
        foreach ([$this->client, $this->owner, $this->courier, $this->admin] as $user) {
            $this->actingAs($user)
                ->from('/profile')
                ->put('/password', ['current_password' => 'password', 'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe'])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success', 'Mot de passe modifié.');

            $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->fresh()->password));
        }

        $this->actingAs($this->client)
            ->from('/profile')
            ->put('/password', ['current_password' => 'faux', 'password' => 'autre-mot-de-passe', 'password_confirmation' => 'autre-mot-de-passe'])
            ->assertSessionHasErrors('current_password');
    }

    // --- Documents (livreur, entreprise) ---

    public function test_documents_show_their_status_and_what_can_be_resent(): void
    {
        $this->giveDocuments($this->courier);
        $this->courier->documents()->where('type', DocumentType::DrivingLicense)->update(['status' => 'rejected', 'rejection_reason' => 'Permis expiré']);

        $this->actingAs($this->courier)
            ->get('/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.account_approved', true)
                ->where('documents.can_replace', true)
                ->where('documents.items.0.type', 'id_card')
                ->where('documents.items.0.status', 'approved')
                ->where('documents.items.0.triggers_revalidation', true)
                ->where('documents.items.1.status', 'rejected')
                ->where('documents.items.1.rejection_reason', 'Permis expiré')
                ->where('documents.items.1.uploader.type', 'driving_license'));

        $this->actingAs($this->client)->post(route('profile.documents.replace', 'id_card'), ['file' => $this->fakePdf()])->assertForbidden();
    }

    public function test_a_courier_can_replace_a_refused_mandatory_document_and_the_account_is_revalidated(): void
    {
        $this->giveDocuments($this->courier);
        $this->courier->documents()->where('type', DocumentType::DrivingLicense)->update(['status' => 'rejected', 'rejection_reason' => 'Permis expiré']);

        $this->sendDocument($this->courier, DocumentType::DrivingLicense, $this->fakePdf('permis.pdf'))
            ->assertRedirect(route('account.pending'))
            ->assertSessionHas('warning');

        $courier = $this->courier->fresh();
        $document = $courier->documents()->where('type', DocumentType::DrivingLicense)->sole();
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertNull($document->rejection_reason);
        $this->assertSame('permis.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('local')->assertMissing("documents/{$courier->id}/driving_license.pdf");

        $this->assertSame(AccountStatus::Pending, $courier->account_status);
        $this->assertFalse($courier->deliveryProfile->is_available);
        $this->assertSame(['Document à revalider'], $this->notificationTitles($this->admin));
        $this->assertDatabaseHas('account_decisions', ['user_id' => $courier->id, 'action' => 'document_replaced']);

        // Revalidation : l'espace livreur est fermé jusqu'à la décision.
        $this->actingAs($courier)->get('/delivery')->assertRedirect('/account/pending');
    }

    public function test_an_optional_document_does_not_trigger_revalidation(): void
    {
        $this->giveDocuments($this->owner);

        $this->sendDocument($this->owner, DocumentType::HealthPermit)
            ->assertRedirect('/profile')
            ->assertSessionHas('success');

        $this->assertSame(AccountStatus::Approved, $this->owner->fresh()->account_status);
        $this->assertSame(DocumentStatus::Pending, $this->owner->documents()->where('type', DocumentType::HealthPermit)->sole()->status);
        $this->assertSame(['Nouveau document à vérifier'], $this->notificationTitles($this->admin));
    }

    public function test_a_pending_account_stays_pending_when_it_sends_a_document(): void
    {
        $pending = $this->makeCourier('En attente', Neighborhood::firstWhere('name', 'Glass'), state: 'pending');

        $this->sendDocument($pending, DocumentType::IdCard)->assertRedirect('/profile');
        $this->assertSame(AccountStatus::Pending, $pending->fresh()->account_status);
    }

    public function test_document_replacement_rules(): void
    {
        $this->giveDocuments($this->courier);

        // Pas d'un autre profil (document d'entreprise), mauvais format.
        $this->sendDocument($this->courier, DocumentType::BusinessRegistration)->assertSessionHasErrors('document');
        $this->sendDocument($this->courier, DocumentType::VehiclePhotoFront, $this->fakePdf())->assertSessionHasErrors('file');

        // Course en cours : un document obligatoire ne peut pas être remplacé (revalidation).
        $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $this->sendDocument($this->courier, DocumentType::IdCard)->assertSessionHasErrors('document');
        $this->assertSame(AccountStatus::Approved, $this->courier->fresh()->account_status);

        // Inscription refusée : correction par la page dédiée.
        $rejected = $this->makeCourier('Refusé', Neighborhood::firstWhere('name', 'Glass'), state: 'rejected');
        $this->sendDocument($rejected, DocumentType::IdCard)->assertSessionHasErrors('document');
    }

    // --- Suppression du compte ---

    public function test_user_can_delete_their_account_and_it_is_anonymized(): void
    {
        $this->giveDocuments($this->courier);
        $this->courier->notify(new AppNotification('Test', 'Message'));

        $this->actingAs($this->courier)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $courier = $this->courier->fresh();
        $this->assertNotNull($courier); // la ligne reste
        $this->assertTrue($courier->isDeleted());
        $this->assertSame(AccountDeletionService::DELETED_NAME, $courier->name);
        $this->assertStringEndsWith('@gogab.invalid', $courier->email);
        $this->assertNull($courier->phone);
        $this->assertFalse(Hash::check('password', $courier->password));
        $this->assertFalse($courier->deliveryProfile->is_available);
        $this->assertSame(0, $courier->documents()->count());
        $this->assertSame([], Storage::disk('local')->allFiles("documents/{$courier->id}"));
        $this->assertSame(0, $courier->notifications()->count());

        // Plus de connexion possible, plus d'annonces.
        $this->post('/login', ['email' => $this->courier->email, 'password' => 'password']);
        $this->assertGuest();
        $this->assertFalse(app(OrderWorkflow::class)->couriersForZone('Centre')->contains('id', $courier->id));
    }

    public function test_deleting_an_account_keeps_the_order_history_of_the_other_parties(): void
    {
        $order = $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);
        $order->recordStatus(OrderStatus::Delivered, $this->courier);

        foreach ([$this->client, $this->courier] as $user) {
            $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
        }

        $order->refresh();
        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame($this->courier->id, $order->delivery_id);
        $this->assertSame(1, $order->statusHistories()->count());

        // L'entreprise et l'admin voient toujours la commande (sans les données personnelles).
        $this->actingAs($this->owner)
            ->get(route('business.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.client', AccountDeletionService::DELETED_NAME)
                ->where('order.courier.name', AccountDeletionService::DELETED_NAME)
                ->where('order.courier.phone', null)
                ->where('order.history.0.author', AccountDeletionService::DELETED_NAME));
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_a_business_deleting_its_account_removes_the_store_from_the_catalog(): void
    {
        $order = $this->makeOrder(OrderStatus::Delivered);

        $this->actingAs($this->owner)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $store = $this->store->fresh();
        $this->assertFalse($store->is_active);
        $this->assertFalse($store->isVisible());
        $this->assertSame($this->owner->id, $store->owner_id);
        $this->get(route('stores.show', $store))->assertNotFound();
        $this->assertSame($store->id, $order->fresh()->store_id);
    }

    public function test_deletion_is_refused_during_an_order_and_for_the_last_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::Accepted);

        $this->actingAs($this->client)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrors('password');
        $this->assertFalse($this->client->fresh()->isDeleted());

        $order->update(['status' => OrderStatus::Delivered]);
        $this->actingAs($this->client)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        // Dernier administrateur validé.
        $this->actingAs($this->admin)->from('/profile')->delete('/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertFalse($this->admin->fresh()->isDeleted());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $this->actingAs($this->client)
            ->from('/profile')
            ->delete('/profile', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertFalse($this->client->fresh()->isDeleted());
    }

    public function test_deleted_accounts_leave_the_admin_lists(): void
    {
        $pending = User::factory()->pending()->create(['role' => 'client']);
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('stats.pending_accounts', 1));

        $this->actingAs($pending)->delete('/profile', ['password' => 'password']);

        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('stats.pending_accounts', 0));
        $this->actingAs($this->admin)->get('/admin/clients')->assertInertia(fn (Assert $page) => $page->where('accounts.data', fn ($accounts) => ! collect($accounts)->contains('id', $pending->id)));
    }
}
