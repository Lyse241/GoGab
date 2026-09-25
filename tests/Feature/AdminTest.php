<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');
    }

    private function makeOrder(string $status, float $total, ?Product $product = null): Order
    {
        $product ??= Store::firstOrCreate(['name' => 'Chez Test'], ['category' => 'Restaurant'])
            ->products()->firstOrCreate(['name' => 'Poulet'], ['price' => 1000]);

        $order = Order::create([
            'client_id' => User::factory()->create(['role' => 'client'])->id,
            'neighborhood_id' => Neighborhood::firstOrCreate(['name' => 'Glass'])->id,
            'total_price' => $total,
            'address_landmarks' => 'Près de la pharmacie',
            'payment_method' => 'moov_money',
            'status' => $status,
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => $total]);

        return $order;
    }

    // --- Tableau de bord ---

    public function test_dashboard_shows_stats(): void
    {
        $this->makeOrder('en_attente', 1000);
        $this->makeOrder('en_attente', 2000);
        $this->makeOrder('livree', 5000);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.total_orders', 3)
                ->where('stats.revenue', 8000)
                ->where('stats.delivered_revenue', 5000)
                ->where('stats.by_status', [
                    ['value' => 'en_attente', 'label' => 'En attente', 'count' => 2],
                    ['value' => 'acceptee', 'label' => 'Acceptée', 'count' => 0],
                    ['value' => 'en_livraison', 'label' => 'En cours de livraison', 'count' => 0],
                    ['value' => 'livree', 'label' => 'Livrée', 'count' => 1],
                ])
                ->has('orders.data', 3)
                ->where('orders.data.0.store', 'Chez Test'));
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        $this->makeOrder('en_attente', 1000);
        $delivered = $this->makeOrder('livree', 5000);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard?status=livree')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'livree')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $delivered->id)
                ->where('stats.total_orders', 2)); // les stats restent globales

        $this->actingAs($this->admin)
            ->get('/admin/dashboard?status=inconnu')
            ->assertSessionHasErrors('status');
    }

    public function test_non_admins_cannot_access_admin_pages(): void
    {
        $store = Store::create(['name' => 'Chez Test', 'category' => 'Restaurant']);

        foreach (['client', 'delivery'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/admin/dashboard')->assertSessionHas('error');
            $this->actingAs($user)->get('/admin/stores')->assertSessionHas('error');
            $this->actingAs($user)->delete("/admin/stores/{$store->id}")->assertSessionHas('error');
        }

        $this->assertModelExists($store);
    }

    // --- Boutiques ---

    public function test_admin_can_create_a_store_with_an_image(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/stores', [
                'name' => 'Pharmacie Nouvelle',
                'category' => 'Pharmacie',
                'image' => UploadedFile::fake()->image('vitrine.jpg'),
            ])
            ->assertSessionHas('success');

        $store = Store::sole();
        $this->assertSame('Pharmacie Nouvelle', $store->name);
        $this->assertStringStartsWith('stores/', $store->image);
        Storage::disk('public')->assertExists($store->image);
    }

    public function test_store_validation(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/stores', [
                'name' => '',
                'category' => '',
                'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['name', 'category', 'image']);

        $this->assertDatabaseCount('stores', 0);
    }

    public function test_admin_can_update_a_store_and_replace_its_image(): void
    {
        $oldImage = UploadedFile::fake()->image('old.jpg')->store('stores', 'public');
        $store = Store::create(['name' => 'Ancien nom', 'category' => 'Restaurant', 'image' => $oldImage]);

        $this->actingAs($this->admin)
            ->put("/admin/stores/{$store->id}", [
                'name' => 'Nouveau nom',
                'category' => 'Restaurant',
                'image' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertSessionHas('success');

        $store->refresh();
        $this->assertSame('Nouveau nom', $store->name);
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($store->image);
    }

    public function test_updating_without_image_keeps_the_current_one(): void
    {
        $store = Store::create(['name' => 'Chez Test', 'category' => 'Restaurant', 'image' => 'https://picsum.photos/seed/x/600/400']);

        $this->actingAs($this->admin)
            ->put("/admin/stores/{$store->id}", ['name' => 'Chez Test', 'category' => 'Resto']);

        $this->assertSame('https://picsum.photos/seed/x/600/400', $store->fresh()->image);
    }

    public function test_admin_can_delete_a_store_without_orders(): void
    {
        $store = Store::create(['name' => 'Chez Test', 'category' => 'Restaurant']);
        $store->products()->create(['name' => 'Poulet', 'price' => 1000]);

        $this->actingAs($this->admin)
            ->delete("/admin/stores/{$store->id}")
            ->assertRedirect(route('admin.stores.index', absolute: false));

        $this->assertModelMissing($store);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_with_ordered_products_cannot_be_deleted(): void
    {
        $order = $this->makeOrder('livree', 1000);
        $store = Store::sole();

        $this->actingAs($this->admin)
            ->delete("/admin/stores/{$store->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($store);
        $this->assertModelExists($order);
    }

    // --- Produits ---

    public function test_admin_can_add_update_and_delete_a_product(): void
    {
        $store = Store::create(['name' => 'Chez Test', 'category' => 'Restaurant']);

        $this->actingAs($this->admin)
            ->post("/admin/stores/{$store->id}/products", [
                'name' => 'Poulet nyembwe',
                'description' => 'Plat traditionnel',
                'price' => 4500,
            ])
            ->assertRedirect(route('admin.stores.edit', $store, absolute: false));

        $product = Product::sole();
        $this->assertSame($store->id, $product->store_id);
        $this->assertSame('4500.00', $product->price);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$product->id}", ['name' => 'Poulet nyembwe', 'description' => '', 'price' => 5000])
            ->assertSessionHas('success');
        $this->assertSame('5000.00', $product->fresh()->price);
        $this->assertNull($product->fresh()->description);

        $this->actingAs($this->admin)
            ->delete("/admin/products/{$product->id}")
            ->assertSessionHas('success');
        $this->assertModelMissing($product);
    }

    public function test_product_validation(): void
    {
        $store = Store::create(['name' => 'Chez Test', 'category' => 'Restaurant']);

        $this->actingAs($this->admin)
            ->post("/admin/stores/{$store->id}/products", ['name' => '', 'price' => 0])
            ->assertSessionHasErrors(['name', 'price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_ordered_product_cannot_be_deleted_and_keeps_order_price(): void
    {
        $order = $this->makeOrder('livree', 1000);
        $product = Product::sole();

        $this->actingAs($this->admin)
            ->delete("/admin/products/{$product->id}")
            ->assertSessionHas('error');
        $this->assertModelExists($product);

        // Changer le prix ne modifie pas les commandes passées.
        $this->actingAs($this->admin)
            ->put("/admin/products/{$product->id}", ['name' => 'Poulet', 'price' => 9999]);
        $this->assertSame('1000.00', $order->items()->sole()->price);
    }
}
