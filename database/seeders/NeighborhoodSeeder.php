<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class NeighborhoodSeeder extends Seeder
{
    /**
     * Quartiers desservis par Gogab, regroupés par commune (la « zone » d'activité des livreurs).
     */
    public function run(): void
    {
        $zones = [
            'Libreville' => [
                'Charbonnages', 'Louis', 'Glass', 'Mont-Bouët', 'Nombakélé', 'Batterie IV',
                'Nzeng-Ayong', 'Sibang', 'PK8', 'Lalala', 'Awendjé', 'Akébé',
            ],
            'Akanda' => ['Angondjé', 'Akanda', 'Okala'],
            'Owendo' => ['Owendo'],
        ];

        // Centre de chaque quartier (latitude, longitude), d'après OpenStreetMap : place le commerce et
        // l'adresse de livraison sur la carte de suivi (position approximative, sans adresse exacte).
        $centers = [
            'Angondjé' => [0.5288199, 9.4143906], 'Akanda' => [0.5164243, 9.3882201], 'Charbonnages' => [0.4361651, 9.4462423],
            'Okala' => [0.4869150, 9.4094238], 'Louis' => [0.4105973, 9.4328279], 'Glass' => [0.3771157, 9.4532994],
            'Mont-Bouët' => [0.4017445, 9.4493184], 'Nombakélé' => [0.3870807, 9.4478432], 'Batterie IV' => [0.4192309, 9.4287895],
            'Nzeng-Ayong' => [0.4271217, 9.4822195], 'Sibang' => [0.4123922, 9.4944959], 'PK8' => [0.4111531, 9.4942390],
            'Lalala' => [0.3626376, 9.4688578], 'Awendjé' => [0.3896753, 9.4734663], 'Akébé' => [0.3931702, 9.4642603],
            'Owendo' => [0.3222583, 9.4979557],
        ];

        foreach ($zones as $zone => $names) {
            foreach ($names as $name) {
                Neighborhood::updateOrCreate(['name' => $name], [
                    'zone' => $zone,
                    'latitude' => $centers[$name][0] ?? null,
                    'longitude' => $centers[$name][1] ?? null,
                ]);
            }
        }
    }
}
