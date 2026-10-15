<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Services\StoreHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreSeeder extends Seeder
{
    /**
     * Commerces de démonstration (noms fictifs) à Libreville, sans compte entreprise : voir
     * DemoCatalog (restaurants gabonais, fast-food, desserts, boissons, boulangeries, pharmacies,
     * épiceries, marché frais, boucherie et poissonnerie).
     * Nécessite CategorySeeder et NeighborhoodSeeder.
     *
     * Horaires variés pour la démo : fermés un jour de la semaine, créneaux passant minuit,
     * pharmacie 7j/7, et une supérette en fermeture temporaire (interrupteur manuel).
     */
    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');
        $neighborhoods = Neighborhood::pluck('id', 'name');

        foreach (DemoCatalog::stores() as $data) {
            $store = Store::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category_id' => $categories[$data['category']],
                    'neighborhood_id' => $neighborhoods[$data['neighborhood']],
                    'description' => $data['description'],
                    'address_landmarks' => $data['landmarks'],
                    'phone' => $data['phone'],
                    'cover_image' => DemoCatalog::COVER_IMAGES."{$data['cover']}.jpg",
                    'is_open' => $data['open'],
                    'is_active' => true,
                ],
            );

            StoreHours::sync($store, $data['hours']);
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
