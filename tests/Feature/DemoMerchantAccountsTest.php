<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\DemoMerchantAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMerchantAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_store_without_owner_gets_a_predictable_account(): void
    {
        $pizza = Store::factory()->create(['name' => 'Pizza Hut', 'owner_id' => null]);
        $mendoza = Store::factory()->create(['name' => 'Mendoza', 'owner_id' => null]);
        $accent = Store::factory()->create(['name' => 'Café de l’Évasion', 'owner_id' => null]);

        $rows = app(DemoMerchantAccounts::class)->ensure();

        $this->assertCount(3, $rows);
        $this->assertSame('pizzahut@gogab.com', $pizza->fresh()->owner->email);
        $this->assertSame('mendoza@gogab.com', $mendoza->fresh()->owner->email);
        $this->assertSame('cafedelevasion@gogab.com', $accent->fresh()->owner->email);

        $owner = User::where('email', 'pizzahut@gogab.com')->first();
        $this->assertSame(Role::Business, $owner->role);
        $this->assertTrue($owner->isApproved());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $owner->password));
    }

    public function test_the_login_works_and_leads_to_the_business_space(): void
    {
        Store::factory()->create(['name' => 'Pizza Hut', 'owner_id' => null]);
        app(DemoMerchantAccounts::class)->ensure();

        $this->post('/login', ['email' => 'pizzahut@gogab.com', 'password' => 'password'])->assertRedirect('/business');
        $this->get('/business')->assertOk();
    }

    public function test_it_is_idempotent_and_leaves_existing_owners_alone(): void
    {
        $existing = User::factory()->create(['role' => 'business', 'email' => 'entreprise@gogab.ga']);
        $owned = Store::factory()->create(['name' => 'Chez Maman Ngoye', 'owner_id' => $existing->id]);
        $ownerEmail = $owned->owner->email;
        Store::factory()->create(['name' => 'Pizza Hut', 'owner_id' => null]);

        app(DemoMerchantAccounts::class)->ensure();
        $count = User::count();
        app(DemoMerchantAccounts::class)->ensure();

        $this->assertSame($count, User::count());
        $this->assertSame($ownerEmail, $owned->fresh()->owner->email);
    }

    public function test_two_stores_with_the_same_name_get_distinct_accounts(): void
    {
        $a = Store::factory()->create(['name' => 'Chez Paul', 'owner_id' => null]);
        $b = Store::factory()->create(['name' => 'Chez Paul', 'owner_id' => null]);

        app(DemoMerchantAccounts::class)->ensure();

        $this->assertNotSame($a->fresh()->owner->email, $b->fresh()->owner->email);
    }
}
