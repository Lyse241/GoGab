<?php

namespace Tests\Feature\Business;

use App\Enums\OrderStatus;
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

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->owner = User::factory()->create(['role' => 'business']);
        $this->store = Store::factory()->create(['owner_id' => $this->owner->id]);
    }

    private function otherProduct(): Product
    {
        $other = User::factory()->create(['role' => 'business']);

        return Product::factory()->create([
            'store_id' => Store::factory()->create(['owner_id' => $other->id])->id,
            'name' => 'Produit concurrent',
        ]);
    }

    public function test_list_is_grouped_by_section_and_only_shows_own_products(): void
    {
        Product::factory()->for($this->store)->inSection('Plats')->create(['name' => 'Poulet nyembwe', 'price' => 4500]);
        Product::factory()->for($this->store)->inSection('Boissons')->create(['name' => 'Jus de bissap']);
        Product::factory()->for($this->store)->unavailable()->create(['name' => 'Alloco']);
        $this->otherProduct();

        $this->actingAs($this->owner)
            ->get('/business/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Products/Index')
                ->has('products', 3)
                // Sections par ordre alphabétique, produits sans section en dernier.
                ->where('products.0.name', 'Jus de bissap')
                ->where('products.1.name', 'Poulet nyembwe')
                ->where('products.1.price', 4500)
                ->where('products.2.name', 'Alloco')
                ->where('products.2.is_available', false)
                ->where('sections', ['Boissons', 'Plats'])
                ->where('totals', ['all' => 3, 'available' => 2]));
    }

    public function test_search_and_section_filter(): void
    {
        Product::factory()->for($this->store)->inSection('Plats')->create(['name' => 'Poulet nyembwe']);
        Product::factory()->for($this->store)->inSection('Plats')->create(['name' => 'Poulet DG']);
        Product::factory()->for($this->store)->inSection('Boissons')->create(['name' => 'Jus de bissap', 'description' => 'Hibiscus maison']);
        Product::factory()->for($this->store)->create(['name' => 'Piment']);

        $names = fn (string $query) => $this->actingAs($this->owner)->get('/business/products'.$query)
            ->viewData('page')['props']['products'];

        $this->assertSame(['Poulet DG', 'Poulet nyembwe'], array_column($names('?q=poulet'), 'name'));
        $this->assertSame(['Jus de bissap'], array_column($names('?q=hibiscus'), 'name'));
        $this->assertSame(['Jus de bissap'], array_column($names('?section=Boissons'), 'name'));
        $this->assertSame(['Poulet nyembwe'], array_column($names('?section=Plats&q=nyembwe'), 'name'));
        $this->assertSame(['Piment'], array_column($names('?section=__none'), 'name'));
        $this->assertSame([], $names('?q=pizza'));
    }

    public function test_business_adds_a_product_with_photo(): void
    {
        $this->actingAs($this->owner)->get('/business/products/create')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Business/Products/Form')->where('product', null));

        $this->actingAs($this->owner)
            ->post('/business/products', [
                'name' => '  Poulet   nyembwe ',
                'description' => 'Sauce noix de palme.',
                'price' => '4 500',
                'menu_section' => ' plats ',
                'is_available' => '1',
                'image' => UploadedFile::fake()->image('poulet.jpg', 600, 600),
            ])
            ->assertRedirect('/business/products')
            ->assertSessionHas('success');

        $product = $this->store->products()->sole();
        $this->assertSame('Poulet nyembwe', $product->name);
        $this->assertSame(4500, (int) $product->price);
        $this->assertSame('Plats', $product->menu_section);
        $this->assertTrue($product->is_available);
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_business_edits_a_product_and_replaces_or_removes_its_photo(): void
    {
        $product = Product::factory()->for($this->store)->create(['name' => 'Poulet', 'image' => UploadedFile::fake()->image('a.jpg')->store('products', 'public')]);
        $oldImage = $product->image;

        $this->actingAs($this->owner)->get("/business/products/{$product->id}/edit")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('product.name', 'Poulet'));

        $payload = ['name' => 'Poulet braisé', 'price' => 5000, 'menu_section' => 'Grillades', 'is_available' => '0'];

        $this->actingAs($this->owner)
            ->post("/business/products/{$product->id}", [...$payload, 'image' => UploadedFile::fake()->image('b.jpg')])
            ->assertRedirect('/business/products');

        $product->refresh();
        $this->assertSame('Poulet braisé', $product->name);
        $this->assertFalse($product->is_available);
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($product->image);

        $current = $product->image;
        $this->actingAs($this->owner)->post("/business/products/{$product->id}", [...$payload, 'remove_image' => '1']);

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($current);
    }

    public function test_availability_switch_updates_immediately(): void
    {
        $product = Product::factory()->for($this->store)->create();

        $this->actingAs($this->owner)
            ->patch("/business/products/{$product->id}/availability", ['is_available' => false])
            ->assertSessionHas('success');
        $this->assertFalse($product->fresh()->is_available);

        $this->actingAs($this->owner)->get('/business/products')
            ->assertInertia(fn (Assert $page) => $page->where('products.0.is_available', false)->where('totals.available', 0));

        $this->actingAs($this->owner)->patch("/business/products/{$product->id}/availability", ['is_available' => true]);
        $this->assertTrue($product->fresh()->is_available);
    }

    public function test_product_is_validated_in_french(): void
    {
        $this->actingAs($this->owner)
            ->post('/business/products', [
                'name' => '',
                'price' => '45,50',
                'is_available' => '1',
                'image' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors([
                'name' => 'Indiquez le nom du produit.',
                'price' => 'Le prix doit être un nombre entier de FCFA (sans centimes).',
                'image' => 'La photo doit être une image.',
            ]);

        $this->actingAs($this->owner)
            ->post('/business/products', ['name' => 'Gratuit', 'price' => 0, 'is_available' => '1'])
            ->assertSessionHasErrors(['price' => 'Le prix doit être d’au moins 1 FCFA.']);

        $this->assertSame(0, Product::count());
    }

    public function test_business_deletes_a_product_but_not_one_already_ordered(): void
    {
        $product = Product::factory()->for($this->store)->create(['image' => UploadedFile::fake()->image('a.jpg')->store('products', 'public')]);

        $this->actingAs($this->owner)
            ->delete("/business/products/{$product->id}")
            ->assertSessionHas('success');
        $this->assertModelMissing($product);
        Storage::disk('public')->assertMissing($product->image);

        $ordered = Product::factory()->for($this->store)->create(['name' => 'Poulet DG']);
        $order = Order::create([
            'store_id' => $this->store->id,
            'client_id' => User::factory()->create()->id,
            'neighborhood_id' => Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre'])->id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 6500,
            'total_price' => 6500,
            'payment_method' => 'cash',
            'status' => OrderStatus::Delivered,
        ]);
        $order->items()->create(['product_id' => $ordered->id, 'quantity' => 1, 'price' => 6500]);

        $this->actingAs($this->owner)
            ->delete("/business/products/{$ordered->id}")
            ->assertSessionHas('error');
        $this->assertModelExists($ordered);
    }

    public function test_business_cannot_open_or_change_another_business_product(): void
    {
        $product = $this->otherProduct();

        $this->actingAs($this->owner)->get("/business/products/{$product->id}/edit")->assertForbidden();
        $this->actingAs($this->owner)->post("/business/products/{$product->id}", ['name' => 'Piraté', 'price' => 1, 'is_available' => '1'])->assertForbidden();
        $this->actingAs($this->owner)->patch("/business/products/{$product->id}/availability", ['is_available' => false])->assertForbidden();
        $this->actingAs($this->owner)->delete("/business/products/{$product->id}")->assertForbidden();

        $product->refresh();
        $this->assertSame('Produit concurrent', $product->name);
        $this->assertTrue($product->is_available);
    }

    public function test_other_roles_and_unapproved_businesses_have_no_access(): void
    {
        $product = Product::factory()->for($this->store)->create();

        foreach (['client', 'delivery', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/business/products')->assertSessionHas('error');
        }

        $this->owner->update(['account_status' => 'suspended']);
        $this->actingAs($this->owner->fresh())
            ->patch("/business/products/{$product->id}/availability", ['is_available' => false])
            ->assertRedirect('/account/suspended');
        $this->assertTrue($product->fresh()->is_available);
    }
}
