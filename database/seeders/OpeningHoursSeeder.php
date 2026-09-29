<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Services\StoreHours;
use Illuminate\Database\Seeder;

class OpeningHoursSeeder extends Seeder
{
    /**
     * Horaires par défaut (08:00 – 22:00, fermé le dimanche) pour tout commerce qui n'en a pas.
     * Les horaires déjà saisis ne sont jamais écrasés.
     *
     *     php artisan db:seed --class=OpeningHoursSeeder
     */
    public function run(): void
    {
        Store::doesntHave('openingHours')->each(
            fn (Store $store) => StoreHours::sync($store, StoreHours::everyDay('08:00', '22:00', [7])),
        );
    }
}
