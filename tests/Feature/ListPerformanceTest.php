<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Report;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Listes avec plusieurs lignes : aucune requête N+1 (Model::preventLazyLoading fait échouer le
 * test au moindre chargement à la volée sur un modèle issu d'une liste) et un nombre de requêtes
 * qui ne grandit pas avec la liste.
 */
class ListPerformanceTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $category = Category::factory()->create(['name' => 'Restaurant']);
        $this->store->update(['category_id' => $category->id, 'is_active' => true]);

        // Plusieurs commerces (dont un avec entreprise), produits, commandes à tous les statuts.
        foreach (range(1, 3) as $i) {
            $owner = User::factory()->create(['role' => 'business']);
            Store::factory()->create(['owner_id' => $owner->id, 'category_id' => $category->id, 'is_active' => true, 'name' => "Commerce {$i}", 'neighborhood_id' => Neighborhood::firstWhere('name', 'Louis')->id])
                ->products()->create(['name' => "Poulet {$i}", 'price' => 2000]);
        }
        foreach ([OrderStatus::Pending, OrderStatus::Preparing, OrderStatus::SearchingCourier, OrderStatus::SearchingCourier, OrderStatus::Delivered, OrderStatus::Delivered] as $status) {
            $order = $this->makeOrder($status, $status === OrderStatus::Delivered ? ['delivery_id' => $this->courier->id] : []);
            $order->recordStatus($status, $this->owner);
        }
        Report::create(['reporter_id' => $this->client->id, 'reported_user_id' => $this->courier->id, 'reason' => 'retard', 'description' => 'Retard important.', 'status' => 'open']);
        Report::create(['reporter_id' => $this->owner->id, 'reported_user_id' => $this->client->id, 'reason' => 'fraude', 'description' => 'Paiement refusé.', 'status' => 'open']);
        foreach ([$this->client, $this->admin] as $user) {
            Notifier::send($user, 'Un', 'Message');
            Notifier::send($user, 'Deux', 'Message');
        }
    }

    public function test_list_pages_have_no_n_plus_one_queries(): void
    {
        $pages = [
            [null, '/'],
            [null, '/search?q=poulet'],
            [null, "/stores/{$this->store->id}"],
            [$this->client, '/orders'],
            [$this->client, '/notifications'],
            [$this->owner, '/business/orders?tab=searching'],
            [$this->owner, '/business/orders?tab=finished'],
            [$this->owner, '/business/products'],
            [$this->courier, '/delivery/offers'],
            [$this->courier, '/delivery/history'],
            [$this->admin, '/admin'],
            [$this->admin, '/admin/orders'],
            [$this->admin, '/admin/reports'],
            [$this->admin, '/admin/stores'],
            [$this->admin, '/admin/clients'],
            [$this->admin, '/admin/accounts?status=approved'],
            [$this->admin, '/admin/moderation'],
        ];

        foreach ($pages as [$user, $url]) {
            $user ? $this->actingAs($user) : auth()->logout();
            $this->get($url)->assertOk();
        }
    }

    public function test_the_admin_order_list_query_count_does_not_grow_with_the_list(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/admin/orders')->assertOk();

            return count(DB::getQueryLog());
        };

        $before = $count();
        foreach (range(1, 8) as $i) {
            $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);
        }

        $this->assertSame($before, $count());
    }
}
