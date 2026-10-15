<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un commerce sans photo reçoit l'icône de sa catégorie (image de remplacement de StoreCover).
 */
class StoreCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_receive_the_category_icon_of_a_store_without_cover(): void
    {
        $category = Category::factory()->create(['name' => 'Pharmacie', 'icon' => 'pill']);
        $store = Store::factory()->create(['name' => 'Pharmacie sans photo', 'category_id' => $category->id, 'cover_image' => null]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('stores.0.cover_image', null)
            ->where('stores.0.category_icon', 'pill'));

        $this->get("/stores/{$store->id}")->assertInertia(fn ($page) => $page
            ->where('store.cover_image', null)
            ->where('store.category_icon', 'pill'));

        $this->get('/search?q=Pharmacie')->assertInertia(fn ($page) => $page
            ->where('stores.0.category_icon', 'pill'));
    }

    public function test_every_category_icon_has_a_component_on_the_front(): void
    {
        // L'icône d'un commerce sans photo vient de config('gogab.category_icons') : chaque nom doit
        // exister dans resources/js/utils/categoryIcons.js, sinon l'icône « boutique » s'affiche.
        $front = file_get_contents(resource_path('js/utils/categoryIcons.js'));

        foreach (config('gogab.category_icons') as $icon) {
            $this->assertMatchesRegularExpression("/['\"]?".preg_quote($icon, '/')."['\"]?\\s*:/", $front, "Icône sans composant : {$icon}");
        }
    }
}
