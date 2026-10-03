<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Parcours critique 3 — accès : un rôle n'entre jamais dans l'espace d'un autre ; un document
 * n'est visible que par son propriétaire et les admins.
 */
class AccessControlTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    /**
     * Pages de chaque espace (lecture et écriture).
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const SPACES = [
        'client' => [['get', '/orders'], ['get', '/checkout/{store}']],
        'business' => [['get', '/business'], ['get', '/business/orders'], ['get', '/business/products'], ['get', '/business/store'], ['post', '/business/products'], ['patch', '/business/store/open']],
        'delivery' => [['get', '/delivery'], ['get', '/delivery/offers'], ['get', '/delivery/current'], ['get', '/delivery/history'], ['get', '/delivery/profile'], ['patch', '/delivery/availability']],
        'admin' => [['get', '/admin'], ['get', '/admin/accounts'], ['get', '/admin/orders'], ['get', '/admin/reports'], ['get', '/admin/stores'], ['get', '/admin/orders/export'], ['post', '/admin/categories']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->setUpOrderWorld();
    }

    /**
     * @return array<string, User>
     */
    private function users(): array
    {
        return ['client' => $this->client, 'business' => $this->owner, 'delivery' => $this->courier, 'admin' => $this->admin];
    }

    public function test_each_role_reaches_its_own_space(): void
    {
        foreach ($this->users() as $role => $user) {
            foreach (self::SPACES[$role] as [$method, $url]) {
                if ($method !== 'get') {
                    continue;
                }
                $this->actingAs($user)->get(str_replace('{store}', $this->store->id, $url))->assertOk();
            }
        }
    }

    public function test_no_role_ever_enters_the_space_of_another(): void
    {
        $checked = 0;
        $categories = Category::count();
        $products = $this->store->products()->count();

        foreach ($this->users() as $role => $user) {
            foreach (self::SPACES as $space => $pages) {
                if ($space === $role) {
                    continue;
                }

                foreach ($pages as [$method, $url]) {
                    $response = $this->actingAs($user)->{$method}(str_replace('{store}', $this->store->id, $url));

                    // Renvoyé vers son propre espace avec un message, jamais servi.
                    $this->assertTrue(
                        $response->isRedirect() || $response->isForbidden(),
                        "{$role} ne doit pas accéder à {$method} {$url} (statut {$response->getStatusCode()})",
                    );
                    $checked++;
                }
            }
        }

        // Rien n'a été créé ni modifié par ces tentatives.
        $this->assertSame($categories, Category::count());
        $this->assertSame($products, $this->store->products()->count());
        $this->assertGreaterThan(50, $checked);
    }

    public function test_guests_are_sent_to_login_from_every_space(): void
    {
        foreach (self::SPACES as $pages) {
            foreach ($pages as [$method, $url]) {
                $this->{$method}(str_replace('{store}', $this->store->id, $url))->assertRedirect('/login');
            }
        }
    }

    public function test_each_order_step_is_refused_to_every_wrong_role(): void
    {
        // Étape => [statut de départ, statut visé, rôle autorisé].
        $steps = [
            'accepter' => [OrderStatus::Pending, 'acceptee', 'business'],
            'refuser' => [OrderStatus::Pending, 'refusee', 'business'],
            'préparer' => [OrderStatus::Accepted, 'en_preparation', 'business'],
            'annoncer' => [OrderStatus::Preparing, 'en_recherche_livreur', 'business'],
            'prendre la course' => [OrderStatus::SearchingCourier, 'livreur_assigne', 'delivery'],
            'récupérer' => [OrderStatus::CourierAssigned, 'en_livraison', 'delivery'],
            'arriver' => [OrderStatus::Delivering, 'arrive', 'delivery'],
            'livrer' => [OrderStatus::Arrived, 'livree', 'delivery'],
        ];

        foreach ($steps as $label => [$from, $to, $allowed]) {
            $assigned = in_array($from, [OrderStatus::CourierAssigned, OrderStatus::Delivering, OrderStatus::Arrived], true);
            $order = $this->makeOrder($from, $assigned ? ['delivery_id' => $this->courier->id] : []);

            foreach ($this->users() as $role => $user) {
                if ($role === $allowed || $role === 'admin') {
                    continue; // l'admin n'a que l'annulation, testée ailleurs
                }

                $this->actingAs($user)
                    ->from('/')
                    ->put(route('orders.status.update', $order), ['status' => $to, 'note' => 'Motif', 'cash_collected' => true]);

                $this->assertSame($from, $order->fresh()->status, "« {$label} » ne doit pas être permis au rôle {$role}.");
            }
        }
    }

    public function test_an_order_is_only_visible_to_its_parties(): void
    {
        $order = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $otherClient = User::factory()->create(['role' => 'client']);
        $otherOwner = User::factory()->create(['role' => 'business']);
        Store::factory()->create(['owner_id' => $otherOwner->id]);

        $this->actingAs($this->client)->get(route('orders.show', $order))->assertOk();
        $this->actingAs($otherClient)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($this->owner)->get(route('business.orders.show', $order))->assertOk();
        $this->actingAs($otherOwner)->get(route('business.orders.show', $order))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_a_document_is_only_visible_to_its_owner_and_the_admins(): void
    {
        Storage::disk('local')->put('documents/cin.pdf', '%PDF-1.4 contenu');
        $document = $this->courier->documents()->create([
            'type' => DocumentType::IdCard,
            'file_path' => 'documents/cin.pdf',
            'original_name' => 'cin.pdf',
            'mime_type' => 'application/pdf',
            'size' => 16,
            'status' => 'pending',
        ]);
        $url = route('documents.show', $document);

        $this->actingAs($this->courier)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get($url)->assertOk();

        foreach ([$this->client, $this->owner, $this->farCourier, User::factory()->pending()->create(['role' => 'admin'])] as $intruder) {
            $this->actingAs($intruder)->get($url)->assertForbidden();
        }

        auth()->logout();
        $this->get($url)->assertRedirect('/login');

        // Jamais de lien public vers le fichier.
        $this->assertStringNotContainsString('storage/', $url);
    }
}
