<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class NeighborhoodSeeder extends Seeder
{
    /**
     * Quartiers de Libreville desservis par Gogab, regroupés par zone.
     */
    public function run(): void
    {
        $zones = [
            'Nord' => ['Angondjé', 'Akanda', 'Charbonnages', 'Okala'],
            'Centre' => ['Louis', 'Glass', 'Mont-Bouët', 'Nombakélé', 'Batterie IV'],
            'Est' => ['Nzeng-Ayong', 'Sibang', 'PK8'],
            'Sud' => ['Lalala', 'Awendjé', 'Akébé', 'Owendo'],
        ];

        foreach ($zones as $zone => $names) {
            foreach ($names as $name) {
                Neighborhood::updateOrCreate(['name' => $name], ['zone' => $zone]);
            }
        }
    }
}
