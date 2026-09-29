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
     * Boutiques de démonstration (noms fictifs) à Libreville, sans compte entreprise.
     * Nécessite CategorySeeder et NeighborhoodSeeder.
     *
     * Horaires variés pour la démo : fermés le dimanche, créneau passant minuit,
     * pharmacie 7j/7, et une supérette en fermeture temporaire (interrupteur manuel).
     */
    public function run(): void
    {
        $stores = [
            // [nom, catégorie, quartier, horaires, ouvert (interrupteur)]
            ['Chez Maman Ngoye', 'restaurant', 'Nombakélé', StoreHours::everyDay('10:00', '22:00', [7]), true],
            ['Le Braisé du Bord de Mer', 'restaurant', 'Louis', StoreHours::everyDay('18:00', '02:00', [1]), true],
            ['Pharmacie du Bon Secours', 'pharmacie', 'Glass', StoreHours::everyDay('08:00', '21:00', [7]), true],
            ['Pharmacie Santé Nzeng-Ayong', 'pharmacie', 'Nzeng-Ayong', StoreHours::everyDay('08:00', '22:00'), true],
            ['Épicerie du Quartier Louis', 'epicerie-courses', 'Louis', StoreHours::everyDay('07:30', '21:30', [7]), true],
            ['Supérette Akanda Express', 'epicerie-courses', 'Akanda', StoreHours::everyDay('08:00', '22:00'), false],
            ["Boulangerie L'Épi d'Owendo", 'boulangerie', 'Owendo', StoreHours::everyDay('06:00', '20:00'), true],
        ];

        $categories = Category::pluck('id', 'slug');
        $neighborhoods = Neighborhood::pluck('id', 'name');

        foreach ($stores as [$name, $category, $neighborhood, $hours, $isOpen]) {
            $store = Store::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$category],
                    'neighborhood_id' => $neighborhoods[$neighborhood],
                    'cover_image' => self::placeholder($name),
                    'is_open' => $isOpen,
                    'is_active' => true,
                ],
            );

            StoreHours::sync($store, $hours);
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
