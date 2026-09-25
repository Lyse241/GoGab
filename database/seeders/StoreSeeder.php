<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreSeeder extends Seeder
{
    /**
     * Boutiques de démonstration (noms fictifs) à Libreville.
     */
    public function run(): void
    {
        $stores = [
            ['name' => 'Chez Maman Ngoye', 'category' => 'Restaurant'],
            ['name' => 'Le Braisé du Bord de Mer', 'category' => 'Restaurant'],
            ['name' => 'Pharmacie du Bon Secours', 'category' => 'Pharmacie'],
            ['name' => 'Pharmacie Santé Nzeng-Ayong', 'category' => 'Pharmacie'],
            ['name' => 'Épicerie du Quartier Louis', 'category' => 'Épicerie'],
            ['name' => 'Supérette Akanda Express', 'category' => 'Épicerie'],
            ['name' => "Boulangerie L'Épi d'Owendo", 'category' => 'Boulangerie'],
        ];

        foreach ($stores as $store) {
            Store::updateOrCreate(
                ['name' => $store['name']],
                [...$store, 'image' => self::placeholder($store['name'])],
            );
        }
    }

    /**
     * Image placeholder Picsum stable pour un nom donné (même nom = même image).
     */
    public static function placeholder(string $name, int $width = 600, int $height = 400): string
    {
        return 'https://picsum.photos/seed/'.Str::slug($name)."/{$width}/{$height}";
    }
}
