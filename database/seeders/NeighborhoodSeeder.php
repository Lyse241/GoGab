<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class NeighborhoodSeeder extends Seeder
{
    /**
     * Quartiers de Libreville desservis par Gogab.
     */
    public function run(): void
    {
        $neighborhoods = [
            'Louis',
            'Angondjé',
            'Glass',
            'Nzeng-Ayong',
            'Akanda',
            'Charbonnages',
            'Lalala',
            'Awendjé',
            'Owendo',
            'Nombakélé',
            'Mont-Bouët',
            'Batterie IV',
        ];

        foreach ($neighborhoods as $name) {
            Neighborhood::firstOrCreate(['name' => $name]);
        }
    }
}
