<?php

namespace Tests\Feature\Business;

use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->owner = User::factory()->create(['role' => 'business', 'name' => 'Marie Ngoye']);
        $this->store = Store::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Chez Maman Ngoye',
            'neighborhood_id' => Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre'])->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Chez Maman Ngoye & Fils',
            'category_id' => Category::firstOrCreate(['slug' => 'restaurant'], ['name' => 'Restaurant'])->id,
            'description' => 'Cuisine gabonaise maison.',
            'phone' => '+241 74 12 34 56',
            'neighborhood_id' => Neighborhood::firstOrCreate(['name' => 'Akanda'], ['zone' => 'Nord'])->id,
            'address_landmarks' => 'Face à la pharmacie, bâtiment bleu',
            'opening_hours' => StoreHours::everyDay('09:00', '21:00'),
            ...$overrides,
        ];
    }

    public function test_only_an_approved_business_reaches_its_space(): void
    {
        $this->get('/business')->assertRedirect('/login');

        foreach (['client', 'delivery', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/business')->assertSessionHas('error');
        }

        foreach (['pending' => '/account/pending', 'rejected' => '/account/rejected', 'suspended' => '/account/suspended'] as $state => $page) {
            $business = User::factory()->{$state}()->create(['role' => 'business']);
            Store::factory()->create(['owner_id' => $business->id]);

            $this->actingAs($business)->get('/business')->assertRedirect($page);
            $this->actingAs($business)->get('/business/store')->assertRedirect($page);
            $this->actingAs($business)->patch('/business/store/open', ['is_open' => false])->assertRedirect($page);
        }

        $this->actingAs($this->owner)
            ->get('/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Dashboard')
                ->where('store.name', 'Chez Maman Ngoye')
                ->where('store.is_open', true)
                ->has('store.is_open_now')
                ->has('today.label'));
    }

    public function test_placeholder_sections_are_reachable(): void
    {
        foreach (['/business/orders' => 'Commandes', '/business/products' => 'Produits'] as $url => $title) {
            $this->actingAs($this->owner)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('ComingSoon')->where('title', $title));
        }
    }

    public function test_open_switch_is_saved_and_combined_with_opening_hours(): void
    {
        // Mardi 10h à Libreville : dans les horaires 09:00–21:00.
        $now = CarbonImmutable::parse('2026-09-29 10:00', 'Africa/Libreville');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        StoreHours::sync($this->store, StoreHours::everyDay('09:00', '21:00'));

        $this->actingAs($this->owner)
            ->patch('/business/store/open', ['is_open' => false])
            ->assertSessionHas('success');

        $this->assertFalse($this->store->fresh()->is_open);
        $this->assertFalse($this->store->fresh()->isOpenNow()); // fermé malgré les horaires

        $this->actingAs($this->owner)->get('/business')
            ->assertInertia(fn (Assert $page) => $page->where('store.is_open', false)->where('store.is_open_now', false));

        $this->actingAs($this->owner)->patch('/business/store/open', ['is_open' => true]);
        $this->assertTrue($this->store->fresh()->isOpenNow());

        // Ouvert via l'interrupteur mais hors horaires : toujours fermé.
        $late = $now->setTime(23, 0);
        Carbon::setTestNow($late);
        CarbonImmutable::setTestNow($late);
        $this->assertFalse($this->store->fresh()->isOpenNow());

        $this->actingAs($this->owner)
            ->patch('/business/store/open', ['is_open' => 'peut-être'])
            ->assertSessionHasErrors('is_open');
    }

    public function test_edit_page_receives_the_store_and_its_hours(): void
    {
        $this->actingAs($this->owner)
            ->get('/business/store')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Store/Edit')
                ->where('store.name', 'Chez Maman Ngoye')
                ->has('openingHours', 7)
                ->has('categories')
                ->has('neighborhoods'));
    }

    public function test_business_updates_its_store_with_logo_and_cover(): void
    {
        $this->actingAs($this->owner)
            ->post('/business/store', $this->validPayload([
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
                'cover_image' => UploadedFile::fake()->image('couverture.jpg', 1200, 600),
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $store = $this->store->fresh();
        $this->assertSame('Chez Maman Ngoye & Fils', $store->name);
        $this->assertSame('074 12 34 56', $store->phone);
        $this->assertSame('Akanda', $store->neighborhood->name);
        $this->assertSame('09:00:00', $store->openingHours->first()->opens_at);
        Storage::disk('public')->assertExists($store->logo);
        Storage::disk('public')->assertExists($store->cover_image);
        $this->assertStringStartsWith('stores/logos/', $store->logo);

        // Nouvelle couverture : l'ancienne est supprimée, le logo est conservé.
        $oldCover = $store->cover_image;
        $this->actingAs($this->owner)
            ->post('/business/store', $this->validPayload(['cover_image' => UploadedFile::fake()->image('neuve.jpg', 800, 400)]))
            ->assertSessionHasNoErrors();

        $store->refresh();
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertExists($store->cover_image);
        Storage::disk('public')->assertExists($store->logo);

        // Retirer le logo.
        $logo = $store->logo;
        $this->actingAs($this->owner)
            ->post('/business/store', $this->validPayload(['remove_logo' => 1]))
            ->assertSessionHasNoErrors();

        $this->assertNull($store->fresh()->logo);
        Storage::disk('public')->assertMissing($logo);
    }

    public function test_store_update_is_validated(): void
    {
        $this->actingAs($this->owner)
            ->post('/business/store', $this->validPayload([
                'name' => '',
                'phone' => '12',
                'address_landmarks' => 'court',
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
                'cover_image' => UploadedFile::fake()->image('petite.jpg', 100, 50),
                'opening_hours' => [...array_slice(StoreHours::everyDay('09:00', '21:00'), 0, 6), ['day_of_week' => 7, 'is_closed' => false, 'opens_at' => '', 'closes_at' => '']],
            ]))
            ->assertSessionHasErrors(['name', 'phone', 'address_landmarks', 'logo', 'cover_image']);

        $this->assertSame('Chez Maman Ngoye', $this->store->fresh()->name);

        $this->actingAs($this->owner)
            ->post('/business/store', $this->validPayload([
                'opening_hours' => [...array_slice(StoreHours::everyDay('09:00', '21:00'), 0, 6), ['day_of_week' => 7, 'is_closed' => false, 'opens_at' => '', 'closes_at' => '']],
            ]))
            ->assertSessionHasErrors(['opening_hours.6.opens_at', 'opening_hours.6.closes_at']);
    }

    public function test_business_without_store_gets_not_found(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'business']))->get('/business/store')->assertNotFound();
    }

    public function test_visible_scope_requires_active_store_and_approved_owner(): void
    {
        $visible = fn (Store $store) => Store::visible()->whereKey($store->id)->exists();

        $this->assertTrue($visible($this->store));
        $this->assertTrue($this->store->isVisible());

        // Commerce seedé sans propriétaire : visible s'il est actif.
        $this->assertTrue($visible(Store::factory()->create(['owner_id' => null])));

        // Inactif.
        $this->store->update(['is_active' => false]);
        $this->assertFalse($visible($this->store));
        $this->store->update(['is_active' => true]);

        // Propriétaire suspendu, en attente ou refusé.
        foreach (['suspended', 'pending', 'rejected'] as $status) {
            $this->owner->update(['account_status' => $status]);
            $this->assertFalse($visible($this->store), "Compte {$status}");
            $this->assertFalse($this->store->fresh()->isVisible());
            $this->assertFalse($this->store->fresh()->isOpenNow()); // non commandable
        }
    }
}
