<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Produits de démonstration par boutique, prix en FCFA : voir DemoCatalog.
     * Nécessite StoreSeeder.
     *
     * Les produits d'une boutique de démonstration qui ne figurent plus au catalogue sont supprimés,
     * sauf s'ils apparaissent dans une commande (l'historique des commandes reste intact).
     */
    public function run(): void
    {
        $ordered = DB::table('order_items')->pluck('product_id')->all();

        foreach (DemoCatalog::stores() as $data) {
            $store = Store::where('name', $data['name'])->firstOrFail();
            $names = [];

            foreach (DemoCatalog::expand($data['products']) as [$name, $description, $price, $section, $image]) {
                $names[] = $name;

                $store->products()->updateOrCreate(
                    ['name' => $name],
                    [
                        'menu_section' => $section,
                        'description' => $description,
                        'price' => $price,
                        'image' => DemoCatalog::PRODUCT_IMAGES."{$image}.jpg",
                    ],
                );
            }

            $store->products()
                ->whereNotIn('name', $names)
                ->whereNotIn('id', $ordered)
                ->delete();
        }
    }
}
