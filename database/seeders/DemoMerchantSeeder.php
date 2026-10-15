<?php

namespace Database\Seeders;

use App\Services\DemoMerchantAccounts;
use Illuminate\Database\Seeder;

/**
 * Un compte entreprise par commerce de démonstration : nomducommerce@gogab.com / password.
 * À lancer après StoreSeeder et TestAccountsSeeder (qui donne « Chez Maman Ngoye » à entreprise@gogab.ga).
 */
class DemoMerchantSeeder extends Seeder
{
    public function run(): void
    {
        app(DemoMerchantAccounts::class)->ensure();
    }
}
