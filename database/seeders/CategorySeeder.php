<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Catégories de commerces (icônes lucide).
     */
    public function run(): void
    {
        $categories = [
            ['Restaurant', 'utensils'],
            ['Fast-food', 'sandwich'],
            ['Pharmacie', 'pill'],
            ['Épicerie & courses', 'shopping-basket'],
            ['Boutique', 'shopping-bag'],
            ['Boulangerie', 'croissant'],
        ];

        foreach ($categories as $index => [$name, $icon]) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'sort_order' => ($index + 1) * 10],
            );
        }
    }
}
