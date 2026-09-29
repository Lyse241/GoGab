<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_only_admins_can_manage_categories(): void
    {
        $category = Category::factory()->create();

        $this->get('/admin/categories')->assertRedirect(route('login', absolute: false));

        foreach (['client', 'delivery', 'business'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/admin/categories')->assertSessionHas('error');
            $this->actingAs($user)->post('/admin/categories', ['name' => 'Piratée', 'icon' => 'store']);
            $this->actingAs($user)->delete("/admin/categories/{$category->id}");
        }

        $this->assertDatabaseMissing('categories', ['name' => 'Piratée']);
        $this->assertModelExists($category);
    }

    public function test_index_lists_categories_in_display_order_with_store_counts(): void
    {
        $second = Category::factory()->create(['name' => 'Restaurant', 'icon' => 'utensils', 'sort_order' => 20]);
        Category::factory()->create(['name' => 'Pharmacie', 'icon' => 'pill', 'sort_order' => 10]);
        Store::factory()->count(2)->create(['category_id' => $second->id]);

        $this->actingAs($this->admin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Categories/Index')
                ->where('categories.0.name', 'Pharmacie')
                ->where('categories.0.stores_count', 0)
                ->where('categories.1.name', 'Restaurant')
                ->where('categories.1.stores_count', 2)
                ->where('icons', config('gogab.category_icons')));
    }

    public function test_admin_creates_a_category_with_a_unique_slug_placed_last_by_default(): void
    {
        Category::factory()->create(['name' => 'Fast food', 'slug' => 'boulangerie', 'sort_order' => 40]);

        $this->actingAs($this->admin)
            ->post('/admin/categories', ['name' => '  Boulangerie ', 'icon' => 'croissant'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Boulangerie',
            'slug' => 'boulangerie-2',
            'icon' => 'croissant',
            'sort_order' => 50,
        ]);
    }

    public function test_name_must_be_unique_and_icon_must_be_in_the_list(): void
    {
        Category::factory()->create(['name' => 'Pharmacie']);

        $this->actingAs($this->admin)
            ->post('/admin/categories', ['name' => 'Pharmacie', 'icon' => 'skull'])
            ->assertSessionHasErrors(['name', 'icon']);

        $this->assertSame(1, Category::count());
    }

    public function test_admin_updates_a_category_without_changing_its_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Épicerie', 'slug' => 'epicerie', 'icon' => 'shopping-basket', 'sort_order' => 30]);

        $this->actingAs($this->admin)
            ->put("/admin/categories/{$category->id}", ['name' => 'Épicerie fine', 'icon' => 'apple', 'sort_order' => 5])
            ->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('Épicerie fine', $category->name);
        $this->assertSame('apple', $category->icon);
        $this->assertSame(5, $category->sort_order);
        $this->assertSame('epicerie', $category->slug);

        // Garder son propre nom n'est pas un doublon.
        $this->actingAs($this->admin)
            ->put("/admin/categories/{$category->id}", ['name' => 'Épicerie fine', 'icon' => 'apple'])
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $category->fresh()->sort_order);
    }

    public function test_unused_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->delete("/admin/categories/{$category->id}")
            ->assertSessionHas('success');

        $this->assertModelMissing($category);
    }

    public function test_category_used_by_a_store_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Restaurant']);
        Store::factory()->count(2)->create(['category_id' => $category->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/categories/{$category->id}")
            ->assertSessionHas('error', 'Impossible de supprimer « Restaurant » : elle est utilisée par 2 commerces.');

        $this->assertModelExists($category);
    }

    public function test_new_category_is_offered_on_business_registration(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/categories', ['name' => 'Fleuriste', 'icon' => 'flower', 'sort_order' => 1]);

        auth()->logout();

        $this->get('/register/business')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register/Business')
                ->where('categories.0.name', 'Fleuriste'));
    }
}
