<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Produits de démonstration par boutique, prix en FCFA.
     * Nécessite StoreSeeder.
     */
    public function run(): void
    {
        $catalog = [
            'Chez Maman Ngoye' => [
                ['Poulet nyembwe', 'Poulet mijoté à la sauce de noix de palme, servi avec riz ou bâtons de manioc.', 4500],
                ['Feuilles de manioc au poisson fumé', "Feuilles de manioc pilées cuites à l'huile de palme avec poisson fumé.", 3500],
                ['Bouillon de viande', 'Bouillon de bœuf épicé aux légumes du marché.', 3000],
                ['Riz sauce arachide', "Riz blanc et sauce à la pâte d'arachide avec morceaux de poulet.", 2500],
                ['Brochettes de bœuf (x5)', 'Cinq brochettes grillées, oignons et piment.', 2000],
            ],
            'Le Braisé du Bord de Mer' => [
                ['Capitaine braisé', 'Capitaine entier braisé, accompagné de banane plantain et piment.', 7500],
                ['Crevettes grillées', "Crevettes grillées à l'ail, sauce maison.", 8000],
                ['Poulet DG', 'Poulet sauté aux plantains mûrs, carottes et haricots verts.', 6500],
                ['Bar braisé et bâtons de manioc', 'Bar braisé servi avec trois bâtons de manioc.', 5500],
                ['Alloco', 'Portion de bananes plantains frites.', 1000],
                ['Jus de bissap (50 cl)', "Jus d'hibiscus maison, servi frais.", 1000],
            ],
            'Pharmacie du Bon Secours' => [
                ['Paracétamol 500 mg (boîte de 16)', 'Antalgique et antipyrétique. Lire la notice avant usage.', 1000],
                ['Antipaludique artéméther-luméfantrine', 'Traitement du paludisme simple, boîte de 24 comprimés. Sur avis médical.', 4500],
                ['Test de diagnostic rapide du paludisme', "Autotest à résultat en 15 minutes.", 2500],
                ['Moustiquaire imprégnée', 'Moustiquaire 2 places imprégnée longue durée.', 7500],
                ['Sels de réhydratation orale (x10)', 'Sachets à diluer, en cas de diarrhée ou de déshydratation.', 1500],
            ],
            'Pharmacie Santé Nzeng-Ayong' => [
                ['Sirop contre la toux (125 ml)', 'Sirop adulte pour toux sèche et grasse.', 3500],
                ['Vitamine C 1000 mg (tube de 20)', 'Comprimés effervescents.', 2000],
                ['Crème anti-moustiques (100 ml)', 'Protection jusqu’à 6 heures.', 4000],
                ['Thermomètre digital', 'Thermomètre électronique à embout souple.', 6000],
                ['Tensiomètre de poignet', 'Mesure automatique de la tension artérielle.', 15000],
                ['Pansements assortis (x30)', 'Boîte de pansements de tailles variées.', 500],
            ],
            'Épicerie du Quartier Louis' => [
                ['Riz parfumé (5 kg)', 'Sac de riz long grain parfumé.', 6500],
                ['Huile végétale (1 L)', 'Huile de cuisson raffinée.', 1800],
                ['Sucre en poudre (1 kg)', 'Sucre blanc cristallisé.', 1000],
                ['Lait en poudre (400 g)', 'Lait entier en poudre.', 3500],
                ["Pack d'eau minérale (6 x 1,5 L)", 'Eau minérale naturelle.', 2500],
            ],
            'Supérette Akanda Express' => [
                ["Sardines à l'huile (125 g)", 'Boîte de sardines.', 700],
                ['Spaghetti (500 g)', 'Pâtes de blé dur.', 600],
                ['Concentré de tomate (400 g)', 'Double concentré de tomate.', 900],
                ['Savon de ménage (x4)', 'Lot de quatre savons.', 1200],
                ['Lessive en poudre (1 kg)', 'Lessive pour lavage à la main et en machine.', 2200],
                ['Piment frais (sachet)', 'Piment local, environ 100 g.', 300],
            ],
            "Boulangerie L'Épi d'Owendo" => [
                ['Baguette', 'Baguette tradition cuite du jour.', 250],
                ['Pain complet', 'Pain de mie complet, 500 g.', 1000],
                ['Croissant', 'Croissant pur beurre.', 500],
                ['Pain au chocolat', 'Viennoiserie au chocolat.', 500],
                ['Gâteau au chocolat (6 parts)', 'Gâteau moelleux au chocolat.', 6000],
            ],
        ];

        foreach ($catalog as $storeName => $products) {
            $store = Store::where('name', $storeName)->firstOrFail();

            foreach ($products as [$name, $description, $price]) {
                $store->products()->updateOrCreate(
                    ['name' => $name],
                    [
                        'description' => $description,
                        'price' => $price,
                        'image' => StoreSeeder::placeholder($storeName.' '.$name, 400, 400),
                    ],
                );
            }
        }
    }
}
