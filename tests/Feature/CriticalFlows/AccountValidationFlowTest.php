<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Parcours critique 2 — validation par l'admin : valider, refuser (motif obligatoire), refus →
 * correction → revalidation ; un compte non validé ne commande pas, ne reçoit pas d'offres et ne
 * gère pas de commerce.
 */
class AccountValidationFlowTest extends TestCase
{
    use BuildsOrders;
    use CriticalFlowHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->setUpOrderWorld();
    }

    private function registerCourier(): User
    {
        $this->post('/register/delivery', $this->courierRegistration(Neighborhood::firstWhere('name', 'Glass'), Neighborhood::firstWhere('name', 'Louis')))
            ->assertSessionHasNoErrors();
        auth()->logout();

        return User::where('email', 'paul@example.com')->sole();
    }

    private function approveDocuments(User $account): void
    {
        foreach ($account->documents()->get() as $document) {
            $this->actingAs($this->admin)->post(route('admin.documents.approve', $document))->assertSessionHasNoErrors();
        }
    }

    public function test_an_admin_approves_a_registered_courier_who_can_then_work(): void
    {
        $courier = $this->registerCourier();

        // Pas de validation tant que les documents obligatoires ne sont pas approuvés.
        $this->actingAs($this->admin)->post(route('admin.accounts.approve', $courier))->assertSessionHasErrors();
        $this->assertSame(AccountStatus::Pending, $courier->fresh()->account_status);

        $this->approveDocuments($courier);
        $this->actingAs($this->admin)->post(route('admin.accounts.approve', $courier))->assertSessionHasNoErrors();

        $courier->refresh();
        $this->assertSame(AccountStatus::Approved, $courier->account_status);
        $this->assertSame($this->admin->id, $courier->approved_by);
        $this->assertNotNull($courier->approved_at);
        $this->assertContains('/delivery', $courier->notifications->pluck('data.url')->all());

        $this->actingAs($courier)->get('/delivery')->assertOk();
    }

    public function test_rejection_requires_a_reason_which_is_sent_to_the_user(): void
    {
        $courier = $this->registerCourier();

        $this->actingAs($this->admin)->post(route('admin.accounts.reject', $courier), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(AccountStatus::Pending, $courier->fresh()->account_status);

        $this->actingAs($this->admin)
            ->post(route('admin.accounts.reject', $courier), ['reason' => 'Votre permis est illisible.'])
            ->assertSessionHasNoErrors();

        $courier->refresh();
        $this->assertSame(AccountStatus::Rejected, $courier->account_status);
        $this->assertSame('Votre permis est illisible.', $courier->rejection_reason);
        $this->assertStringContainsString('Votre permis est illisible.', $courier->notifications()->latest()->first()->data['message']);

        // Le refus d'un document exige aussi un motif.
        $document = $courier->documents()->first();
        $this->actingAs($this->admin)->post(route('admin.documents.reject', $document), ['reason' => ''])->assertSessionHasErrors('reason');
    }

    public function test_rejection_then_correction_then_revalidation(): void
    {
        $courier = $this->registerCourier();
        $license = $courier->documents()->where('type', DocumentType::DrivingLicense)->sole();

        // 1. Refus du permis puis du compte.
        $this->actingAs($this->admin)->post(route('admin.documents.reject', $license), ['reason' => 'Permis expiré.']);
        $this->actingAs($this->admin)->post(route('admin.accounts.reject', $courier), ['reason' => 'Renvoyez un permis valide.']);
        $courier->refresh();
        $this->assertSame(AccountStatus::Rejected, $courier->account_status);
        $this->actingAs($courier)->get('/delivery')->assertRedirect('/account/rejected');

        // 2. Correction : le permis refusé doit être renvoyé.
        $fields = [
            'name' => 'Paul Obame',
            'phone' => '077 55 44 33',
            'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Derrière la station Total, maison jaune',
            'vehicle_brand' => 'Yamaha Crypton',
            'plate_number' => 'GA-1234-LBV',
            'license_number' => 'P-0999999',
            'base_neighborhood_id' => Neighborhood::firstWhere('name', 'Louis')->id,
        ];
        $this->actingAs($courier)->post('/account/rejected/correction', $fields)->assertSessionHasErrors('documents.driving_license');

        $this->actingAs($courier)
            ->post('/account/rejected/correction', [...$fields, 'documents' => ['driving_license' => $this->fakePdf('permis.pdf', 40)]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.pending', absolute: false));

        $courier->refresh();
        $this->assertSame(AccountStatus::Pending, $courier->account_status);
        $this->assertSame(DocumentStatus::Pending, $courier->documents()->where('type', DocumentType::DrivingLicense)->sole()->status);
        $this->assertContains('Dossier corrigé à revalider', $this->admin->notifications->pluck('data.title')->all());

        // 3. Revalidation.
        $this->approveDocuments($courier);
        $this->actingAs($this->admin)->post(route('admin.accounts.approve', $courier))->assertSessionHasNoErrors();
        $this->assertSame(AccountStatus::Approved, $courier->fresh()->account_status);
        $this->actingAs($courier->fresh())->get('/delivery')->assertOk();
    }

    public function test_a_pending_client_cannot_order(): void
    {
        $client = User::factory()->pending()->create(['role' => 'client']);
        $product = $this->store->products()->create(['name' => 'Poulet', 'price' => 4500]);

        $this->actingAs($client)
            ->post('/orders', [
                'store_id' => $this->store->id,
                'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
                'address_landmarks' => 'Près de la pharmacie, portail bleu',
                'payment_method' => 'airtel_money',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertRedirect('/account/pending');

        $this->assertSame(0, Order::count());
        $this->assertCount(0, $this->owner->notifications);
    }

    public function test_a_pending_courier_receives_no_offer(): void
    {
        $pending = $this->makeCourier('En attente', Neighborhood::firstWhere('name', 'Louis'), state: 'pending');
        $order = $this->makeOrder(OrderStatus::Preparing);

        $this->actingAs($this->owner)->put(route('orders.status.update', $order), ['status' => 'en_recherche_livreur']);

        $this->assertCount(0, $pending->notifications);
        $this->assertCount(1, $this->courier->notifications); // le livreur validé de la zone, lui, est prévenu
        $this->actingAs($pending)->get('/delivery/offers')->assertRedirect('/account/pending');
        $this->actingAs($pending)->post(route('delivery.orders.accept', $order))->assertRedirect('/account/pending');
        $this->assertNull($order->fresh()->delivery_id);
    }

    public function test_a_pending_business_cannot_manage_its_store(): void
    {
        $this->post('/register/business', $this->businessRegistration(Neighborhood::firstWhere('name', 'Louis'), Category::factory()->create()))
            ->assertSessionHasNoErrors();
        $owner = User::where('email', 'marie@example.com')->sole();

        foreach (['/business', '/business/orders', '/business/products', '/business/store'] as $url) {
            $this->actingAs($owner)->get($url)->assertRedirect('/account/pending');
        }
        $this->actingAs($owner)->post('/business/products', ['name' => 'Poulet', 'price' => 4500])->assertRedirect('/account/pending');
        $this->actingAs($owner)->patch('/business/store/open', ['is_open' => true])->assertRedirect('/account/pending');

        $this->assertSame(0, $owner->store->products()->count());
        $this->get(route('stores.show', $owner->store))->assertNotFound();
    }
}
