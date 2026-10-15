<?php

namespace Database\Seeders;

use App\Services\StoreHours;

/**
 * Catalogue de démonstration : commerces de Libreville (noms fictifs) et leurs produits, avec les
 * photos de public/images/demo (sources et licences : public/images/demo/CREDITS.md).
 *
 * Un produit est soit une ligne complète [nom, description, prix FCFA, section, image], soit une
 * référence ['@clé', prix?] vers COMMUN (articles repris par plusieurs commerces, prix modifiable).
 */
class DemoCatalog
{
    public const PRODUCT_IMAGES = '/images/demo/products/';

    public const COVER_IMAGES = '/images/demo/stores/';

    /**
     * Articles communs à plusieurs commerces : clé => [nom, description, prix, section, image].
     *
     * @return array<string, array{0: string, 1: string, 2: int, 3: ?string, 4: string}>
     */
    private static function common(): array
    {
        return [
            'coca' => ['Coca-Cola (33 cl)', 'Bouteille bien fraîche.', 700, 'Boissons', 'coca'],
            'soda' => ['Soda (33 cl)', 'Boisson gazeuse bien fraîche.', 700, 'Boissons', 'soda-verre'],
            'eau' => ['Eau minérale (1,5 L)', 'Eau minérale naturelle.', 700, 'Boissons', 'eau-minerale'],
            'biere' => ['Bière blonde (65 cl)', 'Bière blonde servie bien fraîche (réservé aux adultes).', 1500, 'Boissons', 'biere-bouteille'],
            'bissap' => ['Jus de bissap (50 cl)', "Jus d'hibiscus maison, servi frais.", 1000, 'Boissons', 'jus-bissap'],
            'gingembre' => ['Jus de gingembre (50 cl)', 'Gingembre frais pressé, citron et une pointe de piment.', 1000, 'Boissons', 'gingembre'],
            'jus-orange' => ['Jus d’orange pressé (40 cl)', 'Oranges pressées minute, sans sucre ajouté.', 2000, 'Boissons', 'jus-orange'],
            'jus-ananas' => ['Jus d’ananas frais (40 cl)', 'Ananas pressé du jour.', 1500, 'Boissons', 'jus-ananas-frais'],
            'jus-mangue' => ['Jus de mangue (40 cl)', 'Mangues mûres mixées, onctueux.', 1800, 'Boissons', 'jus-mangue'],
            'jus-goyave' => ['Jus de goyave (40 cl)', 'Goyave fraîche, doux et crémeux.', 1800, 'Boissons', 'jus-goyave'],
            'limonade' => ['Limonade maison (40 cl)', 'Citron vert, sucre de canne et menthe.', 1200, 'Boissons', 'limonade'],
            'cafe' => ['Café noir', 'Café fraîchement moulu.', 800, 'Boissons', 'cafe'],
            'cappuccino' => ['Cappuccino', 'Espresso et mousse de lait onctueuse.', 1500, 'Boissons', 'cappuccino'],
            'the' => ['Thé chaud', 'Thé noir ou infusion au choix.', 800, 'Boissons', 'the'],
            'smoothie-fraise' => ['Milkshake à la fraise (40 cl)', 'Fraises, lait et glace vanille.', 2500, 'Boissons', 'smoothie-fraise'],
            'frites' => ['Frites maison', 'Portion de frites croustillantes.', 1500, 'Accompagnements', 'frites'],
            'alloco' => ['Beignets', 'Portion de beignets de banane plantain, frits et dorés.', 1000, 'Accompagnements', 'alloco'],
            'plantain' => ['Banane plantain frite', 'Plantains mûrs frits, dorés et fondants.', 1000, 'Accompagnements', 'plantain-frit'],
            'igname' => ['Igname bouillie', "Igname tendre bouillie, à accompagner d'une sauce.", 1500, 'Accompagnements', 'igname'],
            'salade-fruits' => ['Salade de fruits frais', 'Fruits de saison coupés minute.', 2000, 'Desserts', 'salade-fruits'],
            'glace-vanille' => ['Glace vanille (2 boules)', 'Crème glacée à la vanille.', 1500, 'Desserts', 'glace-vanille'],
            'cheesecake' => ['Cheesecake (part)', 'Fromage frais, biscuit sablé et coulis de fruits rouges.', 3000, 'Desserts', 'cheesecake'],
            'mousse' => ['Mousse au chocolat', 'Mousse au chocolat noir, onctueuse.', 2500, 'Desserts', 'mousse-chocolat'],
            'beignets' => ['Beignets sucrés (x6)', 'Beignets de farine frits, légèrement sucrés.', 1000, 'Desserts', 'beignets-sucres'],
            'croissant' => ['Croissant', 'Croissant pur beurre.', 500, 'Viennoiseries', 'croissant'],
            'pain-choc' => ['Pain au chocolat', 'Viennoiserie au chocolat.', 500, 'Viennoiseries', 'pain-chocolat'],
            'baguette' => ['Baguette', 'Baguette tradition cuite du jour.', 250, 'Pains', 'baguette'],
            'cookies' => ['Cookies aux pépites (x4)', 'Cookies moelleux aux pépites de chocolat.', 1500, 'Douceurs', 'cookies'],
            'ailes' => ['Ailes de poulet (x8)', 'Ailes de poulet marinées et frites.', 3500, 'Poulet', 'ailes-poulet'],
            'nuggets' => ['Nuggets de poulet (x9)', 'Nuggets croustillants et sauce au choix.', 3000, 'Poulet', 'nuggets'],
            'brochettes' => ['Brochettes de bœuf (x5)', 'Cinq brochettes grillées, oignons et piment.', 2000, 'Grillades', 'brochettes-boeuf'],
            'riz-saute' => ['Riz sauté au poulet', 'Riz sauté aux légumes et morceaux de poulet.', 3500, 'Plats', 'riz-saute'],
            'salade' => ['Salade composée', 'Salade, tomates, concombre, avocat et vinaigrette maison.', 2500, 'Entrées', 'salade-composee'],
        ];
    }

    /**
     * @return list<array<string, mixed>> commerces : name, category (slug), neighborhood, hours, open,
     *                                    cover, description, landmarks, phone, products
     */
    public static function stores(): array
    {
        return [
            // ───────────── Restaurants gabonais ─────────────
            [
                'name' => 'Chez Maman Ngoye', 'category' => 'restaurant', 'neighborhood' => 'Nombakélé',
                'hours' => StoreHours::everyDay('10:00', '22:00', [7]), 'open' => true, 'cover' => 'cover-moambe',
                'description' => 'La cuisine de maman Ngoye : nyembwe, saka-saka et sauces mijotées au feu de bois, comme à la maison.',
                'landmarks' => 'Face à la station-service, portail vert', 'phone' => '077 21 04 18',
                'products' => [
                    ['Poulet nyembwe', 'Poulet mijoté dans la sauce onctueuse de noix de palme, servi avec du riz blanc.', 4500, 'Plats gabonais', 'moambe'],
                    ['Poulet nyembwe et bâtons de manioc', 'Le grand classique : poulet au nyembwe accompagné de bâtons de manioc.', 5000, 'Plats gabonais', 'nyembwe-manioc'],
                    ['Saka-saka et banane plantain', "Feuilles de manioc pilées, mijotées à l'huile de palme, avec plantains mûrs.", 3500, 'Plats gabonais', 'saka-plantain'],
                    ['Feuilles de manioc au poisson fumé', 'Saka-saka au poisson fumé, servi avec du manioc.', 3500, 'Plats gabonais', 'saka-manioc'],
                    ['Sauce gombo aux crabes', 'Gombo, crabe et poisson dans une sauce relevée.', 6000, 'Plats gabonais', 'gombo-crabe'],
                    ['Poulet sauce d’arachide', "Poulet tendre dans une sauce à la pâte d'arachide.", 4000, 'Plats gabonais', 'sauce-arachide'],
                    ['Poulet à l’épinard et pâte d’arachide', "Poulet, épinards et pâte d'arachide, onctueux.", 4500, 'Plats gabonais', 'arachide-epinard'],
                    ['Dindon au nyembwe', 'Dindon mijoté lentement dans la sauce de noix de palme.', 6500, 'Plats gabonais', 'dindon-nyembwe'],
                    ['Atanga fumé et manioc', 'Atanga fumé (safou) servi avec du manioc.', 3000, 'Plats gabonais', 'atanga-manioc'],
                    ['Poisson fumé en sauce', 'Poisson fumé cuit dans une sauce de tomates et de piment.', 4500, 'Plats gabonais', 'poisson-fume'],
                    ['Sauce d’épinard et riz', "Épinards cuisinés à l'huile de palme, riz blanc.", 3000, 'Plats gabonais', 'epinard-riz'],
                    ['@brochettes', 2000],
                    ['@igname'], ['@plantain'],
                    ['@bissap'], ['@gingembre'], ['@eau'],
                    ['@beignets'],
                    ['Bouillie de maïs sucrée', 'Bouillie de maïs chaude, légèrement sucrée.', 1000, 'Desserts', 'bouillie-mais'],
                ],
            ],
            [
                'name' => 'Le Braisé du Bord de Mer', 'category' => 'restaurant', 'neighborhood' => 'Louis',
                'hours' => StoreHours::everyDay('18:00', '02:00', [1]), 'open' => true, 'cover' => 'braise-bord-de-mer',
                'description' => 'Poissons et volailles braisés sur la braise, face à la mer. Ambiance du soir à Libreville.',
                'landmarks' => 'Sur le front de mer, près du rond-point', 'phone' => '066 33 12 90',
                'products' => [
                    ['Capitaine braisé', 'Capitaine entier braisé, accompagné de banane plantain et piment.', 7500, 'Braisés', 'capitaine-braise'],
                    ['Bar braisé et bâtons de manioc', 'Bar braisé servi avec trois bâtons de manioc.', 5500, 'Braisés', 'bar-braise'],
                    ['Crevettes grillées', "Crevettes grillées à l'ail, sauce maison.", 8000, 'Braisés', 'crevettes-grillees'],
                    ['Demi-poulet braisé', 'Poulet mariné aux épices, braisé au charbon de bois.', 5500, 'Braisés', 'poulet-roti'],
                    ['@brochettes', 2500],
                    ['Poulet DG', 'Poulet sauté aux plantains mûrs, carottes et haricots verts.', 6500, 'Plats', 'poulet-dg'],
                    ['Poisson pané et frites', 'Filets de poisson panés et frites croustillantes.', 5000, 'Plats', 'poisson-frit-frites'],
                    ['@salade'], ['@alloco'], ['@frites'],
                    ['@bissap'], ['@jus-ananas'], ['@limonade'], ['@biere'], ['@eau'],
                    ['@salade-fruits'], ['@glace-vanille'],
                ],
            ],
            [
                'name' => 'Maquis Chez Tonton Jo', 'category' => 'restaurant', 'neighborhood' => 'Mont-Bouët',
                'hours' => StoreHours::everyDay('17:00', '03:00'), 'open' => true, 'cover' => 'maquis-grill',
                'description' => 'Le maquis du quartier : braisés, brochettes et bières bien fraîches jusqu’au bout de la nuit.',
                'landmarks' => 'Derrière le marché de Mont-Bouët', 'phone' => '062 48 77 05',
                'products' => [
                    ['Poulet braisé entier', 'Poulet entier mariné et braisé, piment à part.', 9000, 'Braisés', 'poulet-roti'],
                    ['Demi-poulet à la broche', 'Poulet rôti à la broche, peau dorée.', 5000, 'Braisés', 'poulet-rotisserie'],
                    ['Capitaine braisé', 'Capitaine braisé au charbon, sauce piment.', 7000, 'Braisés', 'capitaine-braise'],
                    ['@brochettes', 2500],
                    ['Saucisses grillées (x3)', 'Trois saucisses grillées, moutarde et oignons.', 2000, 'Grillades', 'saucisses'],
                    ['Sauce gombo aux crabes', 'Gombo, crabe et poisson dans une sauce relevée.', 6000, 'Plats gabonais', 'gombo-crabe'],
                    ['Atanga fumé et manioc', 'Atanga fumé (safou) servi avec du manioc.', 3000, 'Plats gabonais', 'atanga-manioc'],
                    ['Poisson fumé en sauce', 'Poisson fumé mijoté dans une sauce pimentée.', 4500, 'Plats gabonais', 'poisson-fume'],
                    ['@plantain'], ['@alloco'], ['@frites'],
                    ['@biere'],
                    ['Seau de 6 bières', 'Six bières blondes dans un seau de glace (réservé aux adultes).', 8000, 'Boissons', 'biere'],
                    ['@coca'], ['@eau'],
                ],
            ],
            [
                'name' => 'La Case du Haut-Ogooué', 'category' => 'restaurant', 'neighborhood' => 'Batterie IV',
                'hours' => StoreHours::everyDay('11:00', '21:30', [7]), 'open' => true, 'cover' => 'cuisine-village',
                'description' => 'Cuisine du terroir gabonais : sauces traditionnelles, manioc, igname et tous les plats des grandes occasions.',
                'landmarks' => 'À côté de l’école primaire, rue des Palmiers', 'phone' => '074 90 32 11',
                'products' => [
                    ['Poulet nyembwe', 'Poulet mijoté dans la sauce de noix de palme, riz blanc.', 4500, 'Plats gabonais', 'moambe'],
                    ['Dindon au nyembwe', 'Dindon mijoté lentement dans la sauce de noix de palme.', 6500, 'Plats gabonais', 'dindon-nyembwe'],
                    ['Saka-saka et banane plantain', 'Feuilles de manioc pilées, plantains mûrs.', 3500, 'Plats gabonais', 'saka-plantain'],
                    ['Feuilles de manioc et bâtons de manioc', 'Saka-saka accompagné de bâtons de manioc.', 3000, 'Plats gabonais', 'feuilles-manioc-batons'],
                    ['Aubergines en sauce', 'Aubergines africaines mijotées aux oignons et tomates.', 3000, 'Plats gabonais', 'aubergines-sauce'],
                    ['Sauce d’épinard et riz', "Épinards à l'huile de palme, riz blanc.", 3000, 'Plats gabonais', 'epinard-riz'],
                    ['Oseille et riz blanc', "Oseille sauvage cuisinée à l'huile de palme.", 2500, 'Plats gabonais', 'oseille-riz'],
                    ['Atanga fumé et manioc', 'Atanga fumé (safou), manioc.', 3000, 'Plats gabonais', 'atanga-manioc'],
                    ['Poisson fumé en sauce', 'Poisson fumé en sauce de tomates et piment.', 4500, 'Plats gabonais', 'poisson-fume'],
                    ['Sauce gombo aux crabes', 'Gombo, crabe et poisson.', 6000, 'Plats gabonais', 'gombo-crabe'],
                    ['@igname'],
                    ['Bouillie de maïs sucrée', 'Bouillie de maïs chaude, légèrement sucrée.', 1000, 'Desserts', 'bouillie-mais'],
                    ['@beignets'],
                    ['@gingembre'], ['@bissap'], ['@jus-goyave'],
                ],
            ],
            [
                'name' => 'Chez Dame Pauline', 'category' => 'restaurant', 'neighborhood' => 'Akébé',
                'hours' => StoreHours::everyDay('10:30', '21:00', [7]), 'open' => true, 'cover' => 'cover-gombo-crabe',
                'description' => 'Traiteur de quartier : plats du jour cuisinés à la commande, portions généreuses et sauces maison.',
                'landmarks' => 'Carrefour Akébé-Plaine, immeuble jaune', 'phone' => '077 65 40 22',
                'products' => [
                    ['Sauce gombo aux crabes', 'Gombo, crabe et poisson dans une sauce relevée.', 6000, 'Plats du jour', 'gombo-crabe'],
                    ['Poulet sauce d’arachide', "Poulet dans une sauce à la pâte d'arachide.", 4000, 'Plats du jour', 'sauce-arachide'],
                    ['Poulet à l’épinard et pâte d’arachide', "Poulet, épinards et pâte d'arachide.", 4500, 'Plats du jour', 'arachide-epinard'],
                    ['Saka-saka et banane plantain', 'Feuilles de manioc pilées et plantains mûrs.', 3500, 'Plats du jour', 'saka-plantain'],
                    ['Poisson frit et frites', 'Poisson frit, frites et salade.', 5000, 'Plats du jour', 'poisson-frit-frites'],
                    ['@riz-saute'],
                    ['@salade'], ['@brochettes'],
                    ['@igname'], ['@plantain'], ['@alloco'],
                    ['@jus-ananas'], ['@bissap'], ['@gingembre'],
                    ['@beignets'],
                ],
            ],
            [
                'name' => 'Le Fang Gourmand', 'category' => 'restaurant', 'neighborhood' => 'Lalala',
                'hours' => StoreHours::everyDay('11:00', '22:00', [1]), 'open' => true, 'cover' => 'cover-nyembwe',
                'description' => 'Recettes fang et du Woleu-Ntem : nyembwe, pâte d’arachide, poisson fumé et aubergines.',
                'landmarks' => 'Lalala à gauche, après le carrefour de la pharmacie', 'phone' => '066 12 88 40',
                'products' => [
                    ['Poulet nyembwe', 'Poulet dans la sauce de noix de palme, riz.', 5000, 'Plats', 'moambe'],
                    ['Poulet nyembwe et bâtons de manioc', 'Poulet nyembwe accompagné de bâtons de manioc.', 5500, 'Plats', 'nyembwe-manioc'],
                    ['Dindon au nyembwe', 'Dindon mijoté dans la sauce de noix de palme.', 7000, 'Plats', 'dindon-nyembwe'],
                    ['Poulet à l’épinard et pâte d’arachide', "Poulet, épinards, pâte d'arachide.", 4500, 'Plats', 'arachide-epinard'],
                    ['Poulet sauce d’arachide', "Poulet dans la sauce d'arachide.", 4200, 'Plats', 'sauce-arachide'],
                    ['Aubergines en sauce', 'Aubergines africaines mijotées.', 3000, 'Plats', 'aubergines-sauce'],
                    ['Feuilles de manioc au poisson fumé', 'Saka-saka au poisson fumé et manioc.', 3500, 'Plats', 'saka-manioc'],
                    ['Atanga fumé et manioc', 'Atanga fumé, manioc.', 3500, 'Plats', 'atanga-manioc'],
                    ['Poisson fumé en sauce', 'Poisson fumé en sauce pimentée.', 5000, 'Plats', 'poisson-fume'],
                    ['@igname'],
                    ['Bouillie de maïs sucrée', 'Bouillie de maïs chaude.', 1000, 'Desserts', 'bouillie-mais'],
                    ['@gingembre'], ['@bissap'], ['@eau'],
                ],
            ],
            [
                'name' => 'Saveurs d’Afrique Centrale', 'category' => 'restaurant', 'neighborhood' => 'Glass',
                'hours' => StoreHours::everyDay('11:30', '23:00'), 'open' => true, 'cover' => 'saveurs-afrique',
                'description' => 'Un tour d’Afrique centrale dans l’assiette : poulet DG, poissons braisés, sauces et grillades.',
                'landmarks' => 'Avenue du Colonel Parant, en face de la banque', 'phone' => '074 22 91 63',
                'products' => [
                    ['Poulet DG', 'Poulet sauté aux plantains mûrs, carottes et haricots verts.', 6500, 'Plats', 'poulet-dg'],
                    ['@riz-saute', 4000],
                    ['Poulet frit (3 pièces)', 'Trois pièces de poulet marinées et frites.', 4500, 'Plats', 'poulet-frit'],
                    ['Poulet sauce d’arachide', "Poulet dans une sauce à la pâte d'arachide.", 4000, 'Plats', 'sauce-arachide'],
                    ['Saka-saka et banane plantain', 'Feuilles de manioc pilées, plantains.', 3500, 'Plats', 'saka-plantain'],
                    ['Capitaine braisé', 'Capitaine braisé, banane plantain et piment.', 7500, 'Braisés', 'capitaine-braise'],
                    ['Crevettes grillées', "Crevettes grillées à l'ail.", 8000, 'Braisés', 'crevettes-grillees'],
                    ['@brochettes', 2500],
                    ['@salade'], ['@plantain'], ['@alloco'],
                    ['Jus tropical (40 cl)', 'Mélange de jus de fruits tropicaux.', 2000, 'Boissons', 'jus-tropicaux'],
                    ['@bissap'], ['@coca'],
                    ['@glace-vanille'], ['@salade-fruits'],
                ],
            ],
            [
                'name' => 'La Table de l’Estuaire', 'category' => 'restaurant', 'neighborhood' => 'Angondjé',
                'hours' => StoreHours::everyDay('11:00', '23:00', [7]), 'open' => true, 'cover' => 'table-estuaire',
                'description' => 'Restaurant bistronomique : viandes, poissons et desserts maison, dans un cadre calme.',
                'landmarks' => 'Route de l’Estuaire, après le rond-point d’Angondjé', 'phone' => '077 05 18 36',
                'products' => [
                    ['Steak frites', 'Steak de bœuf grillé, frites maison et sauce au poivre.', 8500, 'Plats', 'steak-frites'],
                    ['Spaghetti bolognaise', 'Spaghetti, sauce bolognaise mijotée.', 5500, 'Plats', 'bolognaise'],
                    ['Poulet rôti et légumes', 'Demi-poulet rôti au four, jus et légumes.', 7000, 'Plats', 'poulet-roti'],
                    ['Poisson pané et frites', 'Filets de poisson panés, frites et salade.', 5500, 'Plats', 'poisson-pane'],
                    ['Riz sauté aux crevettes', 'Riz sauté aux crevettes et légumes croquants.', 6500, 'Plats', 'riz-saute-2'],
                    ['Burger maison', 'Bœuf haché, cheddar, salade, tomate et frites.', 5000, 'Plats', 'burger-cheese'],
                    ['Omelette garnie', 'Omelette aux champignons, fromage et jambon.', 3500, 'Plats', 'omelette'],
                    ['Salade verte au poulet', 'Salade verte, poulet grillé, croûtons et vinaigrette.', 4500, 'Entrées', 'salade-verte'],
                    ['@mousse'], ['@cheesecake'],
                    ['Tarte aux fruits', 'Tartelettes aux fruits rouges.', 3000, 'Desserts', 'tarte-fruits'],
                    ['@salade-fruits'],
                    ['@jus-orange'], ['@cafe'], ['@cappuccino'], ['@coca', 800], ['@eau'],
                ],
            ],

            // ───────────── Fast-food ─────────────
            [
                'name' => 'Pizza Libreville', 'category' => 'fast-food', 'neighborhood' => 'Glass',
                'hours' => StoreHours::everyDay('11:00', '23:30'), 'open' => true, 'cover' => 'pizzeria',
                'description' => 'Pizzas cuites au feu de bois, pâte fraîche faite le matin. Livraison chaude garantie.',
                'landmarks' => 'Près du carrefour Glass, à côté du pressing', 'phone' => '062 70 11 54',
                'products' => [
                    ['Pizza Margherita', 'Tomate, mozzarella et basilic frais.', 6000, 'Pizzas', 'pizza-margherita'],
                    ['Pizza Pepperoni', 'Tomate, mozzarella et rondelles de pepperoni.', 7500, 'Pizzas', 'pizza-pepperoni'],
                    ['Pizza 4 fromages', 'Mozzarella, chèvre, emmental et bleu.', 8000, 'Pizzas', 'pizza-fromages'],
                    ['Pizza Royale', 'Tomate, mozzarella, jambon, champignons et poivrons.', 8500, 'Pizzas', 'pizza-royale'],
                    ['@ailes', 4000], ['@nuggets'],
                    ['@frites'],
                    ['Salade verte', 'Salade verte, tomates, concombre et vinaigrette.', 2500, 'Entrées', 'salade-verte'],
                    ['@coca', 800], ['@jus-orange'], ['@eau'],
                    ['@cheesecake'], ['@mousse'], ['@glace-vanille'],
                ],
            ],
            [
                'name' => 'Burger Station', 'category' => 'fast-food', 'neighborhood' => 'Louis',
                'hours' => StoreHours::everyDay('11:00', '00:00'), 'open' => true, 'cover' => 'burgers',
                'description' => 'Burgers juteux, pain brioché et frites croustillantes. Tout est préparé minute.',
                'landmarks' => 'Boulevard Triomphal, face à la station', 'phone' => '077 84 36 09',
                'products' => [
                    ['Cheeseburger', 'Bœuf, cheddar, salade, tomate, oignons.', 4000, 'Burgers', 'burger-cheese'],
                    ['Bacon burger', 'Bœuf, cheddar fondu, bacon croustillant et sauce maison.', 4800, 'Burgers', 'burger-bacon'],
                    ['Double burger', 'Deux steaks, double cheddar, cornichons.', 6000, 'Burgers', 'burger-double'],
                    ['Burger poulet', 'Poulet grillé, salade, tomate, mayonnaise.', 4200, 'Burgers', 'burger-poulet'],
                    ['Burger poulet croustillant', 'Filet de poulet pané croustillant, salade et sauce.', 4500, 'Burgers', 'burger-poulet-croustillant'],
                    ['Hot-dog', 'Saucisse grillée, oignons frits, ketchup et moutarde.', 2500, 'Burgers', 'hotdog'],
                    ['@frites'], ['@nuggets'], ['@ailes', 3500],
                    ['@coca', 800], ['@limonade'], ['@smoothie-fraise'],
                    ['Cookie glacé', 'Deux cookies et une boule de glace.', 2000, 'Desserts', 'glace-cookie'],
                    ['Cupcake', 'Cupcake chocolat et glaçage.', 1500, 'Desserts', 'cupcake'],
                ],
            ],
            [
                'name' => 'Shawarma & Wraps Express', 'category' => 'fast-food', 'neighborhood' => 'Nzeng-Ayong',
                'hours' => StoreHours::everyDay('10:30', '23:00'), 'open' => true, 'cover' => 'shawarma-cover',
                'description' => 'Shawarmas, wraps et tacos préparés devant vous. Sauces maison, légumes frais.',
                'landmarks' => 'Nzeng-Ayong, derrière la mairie annexe', 'phone' => '062 31 55 90',
                'products' => [
                    ['Shawarma poulet', 'Poulet mariné, crudités, sauce blanche, pain libanais.', 3000, 'Sandwichs', 'shawarma'],
                    ['Shawarma et frites', 'Shawarma poulet servi avec une portion de frites.', 4000, 'Sandwichs', 'shawarma-frites'],
                    ['Wrap poulet', 'Poulet grillé, salade, tomate, sauce ranch.', 3500, 'Wraps', 'wrap-poulet'],
                    ['Wrap poulet-crudités', 'Poulet, concombre, carottes et sauce yaourt.', 3500, 'Wraps', 'wrap-poulet-2'],
                    ['Tacos poulet', 'Galette garnie de poulet, frites et sauce fromagère.', 3500, 'Tacos', 'tacos'],
                    ['Tacos bœuf', 'Galette garnie de bœuf haché, frites et fromage.', 4000, 'Tacos', 'tacos-2'],
                    ['Sandwich poulet', 'Baguette, poulet, crudités et sauce.', 2500, 'Sandwichs', 'sandwich'],
                    ['Club sandwich', 'Pain de mie, poulet, œuf, salade et tomate.', 4000, 'Sandwichs', 'club-sandwich'],
                    ['Hot-dog', 'Saucisse grillée, oignons et moutarde.', 2000, 'Sandwichs', 'hotdog'],
                    ['Samoussas (x4)', 'Quatre samoussas croustillants à la viande.', 1500, 'Snacks', 'samoussa'],
                    ['@frites'],
                    ['@coca', 800], ['@limonade'], ['@jus-mangue'],
                ],
            ],
            [
                'name' => 'Poulet Braisé Express', 'category' => 'fast-food', 'neighborhood' => 'Akébé',
                'hours' => StoreHours::everyDay('11:00', '23:00'), 'open' => true, 'cover' => 'poulet-braise-cover',
                'description' => 'Poulet braisé à la broche, ailes et poulet frit. Livré encore chaud, avec beignets et piment.',
                'landmarks' => 'Akébé Plaine, face au grand arbre', 'phone' => '074 58 20 77',
                'products' => [
                    ['Demi-poulet braisé', 'Poulet mariné, rôti à la broche.', 4500, 'Poulet', 'poulet-rotisserie'],
                    ['Poulet braisé entier', 'Poulet entier, parfait pour 3 personnes.', 8500, 'Poulet', 'poulet-roti'],
                    ['Poulet frit (3 pièces)', 'Trois pièces de poulet marinées et frites.', 4000, 'Poulet', 'poulet-frit'],
                    ['@ailes'], ['@nuggets'],
                    ['@brochettes', 2500],
                    ['@riz-saute', 3000],
                    ['@alloco'], ['@frites'], ['@plantain'],
                    ['Salade fraîcheur', 'Salade, tomates et concombre.', 2000, 'Accompagnements', 'salade-verte'],
                    ['@coca', 800], ['@bissap'], ['@eau'],
                ],
            ],
            [
                'name' => 'Snack Le Carrefour', 'category' => 'fast-food', 'neighborhood' => 'Sibang',
                'hours' => StoreHours::everyDay('06:30', '22:00', [7]), 'open' => true, 'cover' => 'snack-cover',
                'description' => 'Petits-déjeuners, sandwichs et snacks pour toute la journée, au coin de la rue.',
                'landmarks' => 'Carrefour Sibang, à côté de l’arrêt de taxi', 'phone' => '066 90 14 28',
                'products' => [
                    ['Hot-dog', 'Saucisse grillée, oignons, moutarde.', 2000, 'Sandwichs', 'hotdog'],
                    ['Sandwich poulet-crudités', 'Baguette, poulet, crudités.', 2000, 'Sandwichs', 'sandwich'],
                    ['Sandwich omelette', 'Baguette, omelette au fromage.', 2500, 'Sandwichs', 'omelette'],
                    ['Samoussas (x4)', 'Quatre samoussas croustillants.', 1500, 'Snacks', 'samoussa'],
                    ['@beignets'],
                    ['Pancakes à la banane (x3)', 'Trois pancakes, banane et sirop.', 2500, 'Petit-déjeuner', 'pancakes-banane'],
                    ['@frites'], ['@nuggets', 2800],
                    ['@cafe'], ['@the'], ['@bissap'], ['@jus-orange'],
                ],
            ],

            // ───────────── Desserts & glaces ─────────────
            [
                'name' => 'Glacier Le Cap Lopez', 'category' => 'desserts-glaces', 'neighborhood' => 'Louis',
                'hours' => StoreHours::everyDay('11:00', '23:00'), 'open' => true, 'cover' => 'glacier',
                'description' => 'Glaces artisanales, coupes gourmandes et milkshakes pour se rafraîchir.',
                'landmarks' => 'Près du Cap Lopez, enseigne bleue', 'phone' => '077 40 63 15',
                'products' => [
                    ['@glace-vanille'],
                    ['Cornet fraise', 'Cornet gaufré, boule de glace à la fraise.', 1500, 'Glaces', 'glace-cornet'],
                    ['Coupe glacée brownie', 'Brownie tiède, glace vanille, chantilly et sauce chocolat.', 3500, 'Coupes', 'coupe-glacee'],
                    ['Cookie glacé', 'Deux cookies et une boule de glace.', 2000, 'Glaces', 'glace-cookie'],
                    ['Gaufre et glace', 'Gaufre chaude, glace vanille et fruits rouges.', 3500, 'Gaufres', 'gaufre'],
                    ['@smoothie-fraise'],
                    ['Smoothie kiwi-fraise (40 cl)', 'Kiwi, fraise et lait.', 2500, 'Boissons', 'smoothie-kiwi'],
                    ['@salade-fruits'],
                    ['Yaourt glacé fraise', 'Yaourt onctueux aux fraises fraîches.', 1500, 'Desserts', 'yaourt'],
                    ['Cupcake', 'Cupcake chocolat et glaçage.', 1500, 'Desserts', 'cupcake'],
                ],
            ],
            [
                'name' => 'Crêperie La Mangrove', 'category' => 'desserts-glaces', 'neighborhood' => 'Batterie IV',
                'hours' => StoreHours::everyDay('08:00', '22:00', [1]), 'open' => true, 'cover' => 'creperie',
                'description' => 'Crêpes, gaufres et pancakes préparés à la minute, avec des fruits frais.',
                'landmarks' => 'Batterie IV, à côté de la boulangerie', 'phone' => '062 19 84 70',
                'products' => [
                    ['Crêpe sucre', 'Crêpe fine, sucre et citron.', 1500, 'Crêpes', 'crepes-sucre'],
                    ['Crêpes aux fraises', 'Deux crêpes, fraises fraîches et chantilly.', 3000, 'Crêpes', 'crepes-fraises'],
                    ['Pancakes à la banane (x3)', 'Trois pancakes, banane et sirop.', 3000, 'Pancakes', 'pancakes-banane'],
                    ['Gaufre sucrée', 'Gaufre chaude au sucre glace.', 2500, 'Gaufres', 'gaufre-2'],
                    ['Gaufre aux fruits rouges', 'Gaufre, fruits rouges et glace vanille.', 3500, 'Gaufres', 'gaufre'],
                    ['@beignets'],
                    ['@salade-fruits'],
                    ['@cappuccino'], ['@cafe'], ['@the'],
                    ['@jus-orange'], ['@smoothie-fraise'],
                ],
            ],
            [
                'name' => 'Pâtisserie Délices d’Akanda', 'category' => 'desserts-glaces', 'neighborhood' => 'Akanda',
                'hours' => StoreHours::everyDay('08:00', '20:00', [7]), 'open' => true, 'cover' => 'patisserie',
                'description' => 'Gâteaux d’anniversaire, entremets et petites pâtisseries faits maison chaque matin.',
                'landmarks' => 'Akanda, route de la plage, immeuble rose', 'phone' => '077 52 09 31',
                'products' => [
                    ['Gâteau au chocolat (6 parts)', 'Gâteau moelleux au chocolat, idéal pour partager.', 6000, 'Gâteaux', 'gateau-chocolat'],
                    ['Part de gâteau au chocolat', 'Une part de gâteau chocolat avec crème.', 1500, 'Gâteaux', 'gateau-chocolat-2'],
                    ['Gâteau aux fraises (part)', 'Génoise, crème légère et fraises fraîches.', 3000, 'Gâteaux', 'gateau-fraise'],
                    ['@cheesecake'],
                    ['Tarte aux fruits', 'Tartelettes aux fruits rouges.', 3000, 'Tartes', 'tarte-fruits'],
                    ['Tarte au citron meringuée (part)', 'Crème de citron et meringue dorée.', 2500, 'Tartes', 'tarte-citron'],
                    ['Éclairs au chocolat (x3)', 'Trois éclairs fourrés crème pâtissière, glaçage chocolat.', 2500, 'Petites pâtisseries', 'eclairs'],
                    ['Cupcakes (x4)', 'Quatre cupcakes assortis.', 3000, 'Petites pâtisseries', 'cupcake'],
                    ['@mousse'],
                    ['Muffins (x2)', 'Deux muffins aux pépites de chocolat.', 1500, 'Petites pâtisseries', 'muffin'],
                    ['@cookies'],
                    ['Brioches à la cannelle (x3)', 'Trois brioches roulées à la cannelle.', 1500, 'Petites pâtisseries', 'brioches-cannelle'],
                ],
            ],

            // ───────────── Boissons & jus ─────────────
            [
                'name' => 'Bar à Jus Tropical', 'category' => 'boissons-jus', 'neighborhood' => 'Mont-Bouët',
                'hours' => StoreHours::everyDay('07:00', '21:00'), 'open' => true, 'cover' => 'bar-jus',
                'description' => 'Jus frais pressés minute, smoothies et boissons locales : bissap, gingembre, goyave.',
                'landmarks' => 'Entrée du marché de Mont-Bouët, côté fruits', 'phone' => '074 17 66 02',
                'products' => [
                    ['@jus-ananas'],
                    ['Jus d’ananas et gingembre (40 cl)', 'Ananas frais relevé de gingembre.', 1800, 'Jus frais', 'jus-ananas'],
                    ['@jus-mangue'], ['@jus-goyave'], ['@jus-orange'],
                    ['Jus tropical (40 cl)', 'Mélange de jus de fruits tropicaux.', 2000, 'Jus frais', 'jus-tropicaux'],
                    ['@bissap'], ['@gingembre'], ['@limonade'],
                    ['@smoothie-fraise'],
                    ['Smoothie kiwi-fraise (40 cl)', 'Kiwi, fraise et lait.', 2500, 'Smoothies', 'smoothie-kiwi'],
                    ['Jus rouge fruits rouges (40 cl)', 'Hibiscus, fraises et citron.', 2000, 'Smoothies', 'jus-rouge'],
                    ['Mojito sans alcool (40 cl)', 'Menthe, citron vert et eau pétillante.', 2500, 'Cocktails sans alcool', 'mojito'],
                    ['@salade-fruits'],
                ],
            ],
            [
                'name' => 'Café Océan', 'category' => 'boissons-jus', 'neighborhood' => 'Glass',
                'hours' => StoreHours::everyDay('07:00', '20:00'), 'open' => true, 'cover' => 'cafe-cover',
                'description' => 'Café de spécialité, viennoiseries et douceurs pour une pause ou un petit-déjeuner livré.',
                'landmarks' => 'Glass, en face de la galerie commerciale', 'phone' => '066 44 31 87',
                'products' => [
                    ['@cafe'], ['@cappuccino'], ['@the'],
                    ['@croissant'], ['@pain-choc'],
                    ['Muffin aux pépites', 'Muffin moelleux aux pépites de chocolat.', 1500, 'Viennoiseries', 'muffin'],
                    ['Brioches à la cannelle (x3)', 'Trois brioches roulées à la cannelle.', 1500, 'Viennoiseries', 'brioches-cannelle'],
                    ['@cookies'],
                    ['Part de gâteau au chocolat', 'Gâteau moelleux, crème fouettée.', 1500, 'Douceurs', 'gateau-chocolat-2'],
                    ['@cheesecake'],
                    ['Gaufre sucrée', 'Gaufre chaude au sucre glace.', 2500, 'Douceurs', 'gaufre-2'],
                    ['@jus-orange'], ['@smoothie-fraise'],
                ],
            ],
            [
                'name' => 'Dépôt Boissons du Centre', 'category' => 'boissons-jus', 'neighborhood' => 'Nombakélé',
                'hours' => StoreHours::everyDay('08:00', '22:30'), 'open' => true, 'cover' => 'depot-boissons',
                'description' => 'Boissons fraîches à l’unité ou au pack : sodas, eaux, jus, bières et lait.',
                'landmarks' => 'Nombakélé, face au rond-point', 'phone' => '077 36 28 59',
                'products' => [
                    ['@coca'], ['@soda'],
                    ['Coca-Cola (pack de 6)', 'Six bouteilles de 33 cl.', 3800, 'Sodas', 'coca-glace'],
                    ['Pack d’eau minérale (6 x 1,5 L)', 'Eau minérale naturelle.', 2500, 'Eaux', 'eau-minerale'],
                    ['@biere'],
                    ['Pack de 6 bières', 'Six bières blondes de 65 cl (réservé aux adultes).', 8000, 'Bières', 'biere'],
                    ['Lait frais (1 L)', 'Lait demi-écrémé.', 1200, 'Lait', 'lait'],
                    ['Jus d’orange (1 L)', 'Jus d’orange 100 % fruit.', 2000, 'Jus', 'jus-orange'],
                    ['Jus de mangue (1 L)', 'Jus de mangue.', 2500, 'Jus', 'jus-mangue'],
                    ['Jus d’ananas (1 L)', 'Jus d’ananas.', 2500, 'Jus', 'jus-ananas'],
                    ['Bissap (1 L)', "Jus d'hibiscus maison en bouteille.", 1800, 'Jus', 'jus-bissap'],
                    ['Yaourt (x4)', 'Quatre yaourts nature.', 1500, 'Lait', 'yaourt'],
                ],
            ],

            // ───────────── Boulangeries ─────────────
            [
                'name' => "Boulangerie L'Épi d'Owendo", 'category' => 'boulangerie', 'neighborhood' => 'Owendo',
                'hours' => StoreHours::everyDay('06:00', '20:00'), 'open' => true, 'cover' => 'boulangerie-owendo',
                'description' => 'Pains, viennoiseries et gâteaux cuits chaque jour au four à bois.',
                'landmarks' => 'Owendo centre, près du marché', 'phone' => '062 85 11 40',
                'products' => [
                    ['@baguette'],
                    ['Pain complet', 'Pain de mie complet, 500 g.', 1000, 'Pains', 'pain-complet'],
                    ['@croissant'], ['@pain-choc'],
                    ['Gâteau au chocolat (6 parts)', 'Gâteau moelleux au chocolat.', 6000, 'Gâteaux', 'gateau-chocolat'],
                    ['Éclairs au chocolat (x3)', 'Trois éclairs crème pâtissière.', 2500, 'Pâtisseries', 'eclairs'],
                    ['Muffins (x2)', 'Deux muffins aux pépites de chocolat.', 1500, 'Pâtisseries', 'muffin'],
                    ['@beignets'],
                ],
            ],
            [
                'name' => 'La Baguette d’Or', 'category' => 'boulangerie', 'neighborhood' => 'Akébé',
                'hours' => StoreHours::everyDay('05:30', '21:00'), 'open' => true, 'cover' => 'baguette-cover',
                'description' => 'Baguettes tradition, viennoiseries au beurre et sandwichs frais, du petit matin au soir.',
                'landmarks' => 'Akébé Plaine, à côté de la pharmacie', 'phone' => '074 73 25 96',
                'products' => [
                    ['Baguette tradition', 'Baguette à la croûte dorée, cuite du jour.', 300, 'Pains', 'baguette'],
                    ['Pain complet', 'Pain complet à la mie dense, 500 g.', 1100, 'Pains', 'pain-complet'],
                    ['@croissant'], ['@pain-choc'],
                    ['Brioches à la cannelle (x3)', 'Trois brioches roulées à la cannelle.', 1500, 'Viennoiseries', 'brioches-cannelle'],
                    ['Muffin aux pépites', 'Muffin moelleux aux pépites de chocolat.', 800, 'Viennoiseries', 'muffin'],
                    ['Sandwich poulet', 'Baguette, poulet, crudités et sauce.', 2000, 'Sandwichs', 'sandwich'],
                    ['Éclairs au chocolat (x3)', 'Trois éclairs crème pâtissière.', 2500, 'Pâtisseries', 'eclairs'],
                    ['Tarte aux fruits', 'Tartelettes aux fruits rouges.', 3000, 'Pâtisseries', 'tarte-fruits'],
                    ['@cafe'], ['@jus-orange'],
                ],
            ],
            [
                'name' => 'Fournil de l’Estuaire', 'category' => 'boulangerie', 'neighborhood' => 'Angondjé',
                'hours' => StoreHours::everyDay('06:00', '19:30', [7]), 'open' => true, 'cover' => 'fournil',
                'description' => 'Pains artisanaux au levain, cookies et gâteaux, pétris à la main chaque nuit.',
                'landmarks' => 'Angondjé, après la station', 'phone' => '066 57 90 13',
                'products' => [
                    ['Baguette de campagne', 'Baguette au levain, mie alvéolée.', 350, 'Pains', 'baguette'],
                    ['Pain complet', 'Pain complet au levain, 500 g.', 1200, 'Pains', 'pain-complet'],
                    ['@croissant', 600],
                    ['@pain-choc', 600],
                    ['@cookies', 1200],
                    ['Gâteau aux fraises (part)', 'Génoise, crème légère et fraises.', 3000, 'Gâteaux', 'gateau-fraise'],
                    ['Tarte au citron meringuée (part)', 'Crème de citron et meringue.', 2500, 'Gâteaux', 'tarte-citron'],
                    ['Cupcakes (x4)', 'Quatre cupcakes assortis.', 3000, 'Gâteaux', 'cupcake'],
                ],
            ],

            // ───────────── Pharmacies ─────────────
            [
                'name' => 'Pharmacie du Bon Secours', 'category' => 'pharmacie', 'neighborhood' => 'Glass',
                'hours' => StoreHours::everyDay('08:00', '21:00', [7]), 'open' => true, 'cover' => 'pharmacie-bon-secours',
                'description' => 'Médicaments, conseils et produits de santé. Une équipe de pharmaciens à votre écoute.',
                'landmarks' => 'Glass, face à la clinique', 'phone' => '011 74 20 33',
                'products' => self::pharmacy([1000, 4500, 2500, 7500, 1500, 3500, 6000, 500, 5000, 3000, 1500]),
            ],
            [
                'name' => 'Pharmacie Santé Nzeng-Ayong', 'category' => 'pharmacie', 'neighborhood' => 'Nzeng-Ayong',
                'hours' => StoreHours::everyDay('08:00', '22:00'), 'open' => true, 'cover' => 'pharmacie-nzeng-ayong',
                'description' => 'Ouverte 7 jours sur 7 : médicaments, parapharmacie et petit matériel médical.',
                'landmarks' => 'Nzeng-Ayong, après le grand carrefour', 'phone' => '011 76 38 12',
                'products' => self::pharmacy([1200, 4800, 2800, 8000, 1500, 3800, 6500, 500, 5500, 3200, 1800], true),
            ],
            [
                'name' => 'Pharmacie du Mont-Bouët', 'category' => 'pharmacie', 'neighborhood' => 'Mont-Bouët',
                'hours' => StoreHours::everyDay('07:30', '21:30'), 'open' => true, 'cover' => 'pharmacie-mont-bouet',
                'description' => 'Pharmacie de quartier : ordonnances, médicaments courants et conseils personnalisés.',
                'landmarks' => 'Mont-Bouët, à côté de la banque', 'phone' => '011 72 55 07',
                'products' => self::pharmacy([1000, 4500, 2500, 7000, 1500, 3500, 5500, 500, 4800, 2800, 1500]),
            ],
            [
                'name' => 'Pharmacie de l’Estuaire', 'category' => 'pharmacie', 'neighborhood' => 'Akanda',
                'hours' => StoreHours::everyDay('08:00', '20:00', [7]), 'open' => true, 'cover' => 'pharmacie-estuaire',
                'description' => 'Santé, hygiène et bien-être : une large gamme de produits, livrée rapidement à Akanda.',
                'landmarks' => 'Akanda, avenue principale', 'phone' => '011 78 91 46',
                'products' => self::pharmacy([1100, 4600, 2600, 7800, 1600, 3600, 6200, 600, 5200, 3000, 1700], true),
            ],

            // ───────────── Épiceries et supermarchés ─────────────
            [
                'name' => 'Épicerie du Quartier Louis', 'category' => 'epicerie-courses', 'neighborhood' => 'Louis',
                'hours' => StoreHours::everyDay('07:30', '21:30', [7]), 'open' => true, 'cover' => 'epicerie-louis',
                'description' => 'L’essentiel du quotidien à deux pas de chez vous : riz, huile, conserves et produits frais.',
                'landmarks' => 'Louis, à côté de la boulangerie', 'phone' => '066 18 40 25',
                'products' => self::grocery(0, [6500, 1800, 1000, 3500, 2500]),
            ],
            [
                'name' => 'Supérette Akanda Express', 'category' => 'epicerie-courses', 'neighborhood' => 'Akanda',
                'hours' => StoreHours::everyDay('08:00', '22:00'), 'open' => false, 'cover' => 'superette-akanda',
                'description' => 'Votre supérette d’Akanda : conserves, produits d’entretien et dépannage de nuit.',
                'landmarks' => 'Akanda, face à la station-service', 'phone' => '077 26 71 38',
                'products' => self::grocery(1, [6800, 1900, 1100, 3600, 2600]),
            ],
            [
                'name' => 'Supermarché Central Louis', 'category' => 'epicerie-courses', 'neighborhood' => 'Louis',
                'hours' => StoreHours::everyDay('08:00', '21:00'), 'open' => true, 'cover' => 'superette-akanda',
                'description' => 'Un grand choix de produits d’épicerie, d’hygiène, de boissons et de produits frais.',
                'landmarks' => 'Louis, grand bâtiment avec parking', 'phone' => '011 70 33 89',
                'products' => self::grocery(2, [6400, 1700, 1000, 3400, 2400]),
            ],
            [
                'name' => 'Mini-Marché de Sibang', 'category' => 'epicerie-courses', 'neighborhood' => 'Sibang',
                'hours' => StoreHours::everyDay('07:00', '22:00'), 'open' => true, 'cover' => 'mini-marche',
                'description' => 'Le mini-marché du quartier : produits de base, boissons fraîches et petit déjeuner.',
                'landmarks' => 'Sibang, après le carrefour', 'phone' => '062 94 12 66',
                'products' => self::grocery(3, [6600, 1800, 1100, 3500, 2500]),
            ],

            // ───────────── Fruits & légumes ─────────────
            [
                'name' => 'Marché Frais de Mont-Bouët', 'category' => 'fruits-legumes', 'neighborhood' => 'Mont-Bouët',
                'hours' => StoreHours::everyDay('06:30', '18:30'), 'open' => true, 'cover' => 'marche-legumes',
                'description' => 'Légumes et condiments du marché, choisis le matin et livrés frais chez vous.',
                'landmarks' => 'Marché de Mont-Bouët, allée des légumes', 'phone' => '074 31 09 54',
                'products' => [
                    ['Tomates (1 kg)', 'Tomates bien mûres du marché.', 1500, 'Légumes', 'tomates'],
                    ['Oignons rouges (1 kg)', 'Oignons rouges frais.', 1200, 'Légumes', 'oignons-rouges'],
                    ['Ail (250 g)', 'Têtes d’ail frais.', 800, 'Légumes', 'ail'],
                    ['Pommes de terre (1 kg)', 'Pommes de terre à chair ferme.', 1200, 'Légumes', 'pommes-terre'],
                    ['Carottes (1 kg)', 'Carottes croquantes.', 1000, 'Légumes', 'carottes'],
                    ['Concombres (x3)', 'Trois concombres frais.', 1000, 'Légumes', 'concombre'],
                    ['Chou vert', 'Chou vert pommé.', 1000, 'Légumes', 'chou'],
                    ['Aubergines africaines (500 g)', 'Petites aubergines pour sauces et ragoûts.', 1500, 'Légumes', 'aubergine-africaine'],
                    ['Gombo (500 g)', 'Gombo frais pour sauces.', 1500, 'Légumes', 'gombo'],
                    ['Poivrons (x4)', 'Poivrons rouges, jaunes et verts.', 1500, 'Légumes', 'poivrons'],
                    ['Piment frais (sachet)', 'Piment local, environ 100 g.', 300, 'Condiments', 'piment'],
                    ['Gingembre frais (250 g)', 'Racines de gingembre frais.', 1000, 'Condiments', 'gingembre-racine'],
                    ['Manioc frais (2 kg)', 'Tubercules de manioc épluchés.', 2000, 'Tubercules', 'manioc-marche'],
                    ['Régime de plantain', 'Plantains verts pour friture et cuisine.', 3500, 'Tubercules', 'plantain-regime'],
                    ['Avocats (x3)', 'Trois avocats mûrs à point.', 1500, 'Fruits', 'avocat'],
                    ['Citrons (x6)', 'Six citrons frais.', 1000, 'Fruits', 'citron'],
                ],
            ],
            [
                'name' => 'Fruits d’Afrique Centrale', 'category' => 'fruits-legumes', 'neighborhood' => 'Okala',
                'hours' => StoreHours::everyDay('07:00', '19:00', [7]), 'open' => true, 'cover' => 'fruits-cover',
                'description' => 'Fruits de saison, ananas, mangues et papayes, sélectionnés auprès des producteurs locaux.',
                'landmarks' => 'Okala, route principale, étal bleu', 'phone' => '066 61 47 28',
                'products' => [
                    ['Ananas', 'Ananas sucré et parfumé, entier.', 1500, 'Fruits', 'ananas'],
                    ['Mangues (x4)', 'Quatre mangues mûres.', 2000, 'Fruits', 'mangue'],
                    ['Papaye', 'Papaye mûre à point.', 1500, 'Fruits', 'papaye'],
                    ['Pastèque entière', 'Pastèque sucrée et rafraîchissante.', 3500, 'Fruits', 'pasteque'],
                    ['Bananes douces (1 kg)', 'Bananes mûres.', 1000, 'Fruits', 'bananes'],
                    ['Mandarines (1 kg)', 'Mandarines juteuses.', 1500, 'Fruits', 'mandarines'],
                    ['Avocats (x3)', 'Trois avocats mûrs.', 1500, 'Fruits', 'avocat'],
                    ['Citrons (x6)', 'Six citrons frais.', 1000, 'Fruits', 'citron'],
                    ['Régime de plantain', 'Plantains pour friture et cuisine.', 3500, 'Fruits', 'plantain-regime'],
                    ['Salade de fruits (barquette)', 'Fruits de saison coupés minute.', 2000, 'Prêt à manger', 'salade-fruits'],
                    ['Jus d’ananas frais (50 cl)', 'Ananas pressé du jour.', 1500, 'Prêt à manger', 'jus-ananas-frais'],
                ],
            ],

            // ───────────── Boucherie et poissonnerie ─────────────
            [
                'name' => 'Poissonnerie du Port-Môle', 'category' => 'boucherie-poissonnerie', 'neighborhood' => 'Owendo',
                'hours' => StoreHours::everyDay('06:00', '17:30', [7]), 'open' => true, 'cover' => 'poisson-glace-cover',
                'description' => 'Poissons et crevettes du jour, débarqués au port d’Owendo. Découpe et préparation sur demande.',
                'landmarks' => 'Port d’Owendo, entrée principale', 'phone' => '077 13 52 90',
                'products' => [
                    ['Capitaine frais (1 kg)', 'Capitaine entier, pêche du jour.', 6500, 'Poissons frais', 'poisson-glace'],
                    ['Bar frais (1 kg)', 'Bar entier, écaillé et vidé.', 5500, 'Poissons frais', 'poissons-glace'],
                    ['Crevettes crues (500 g)', 'Crevettes fraîches, non décortiquées.', 6000, 'Crustacés', 'crevettes-crues'],
                    ['Poisson fumé (lot)', 'Lot de poissons fumés au feu de bois.', 3500, 'Poissons fumés', 'poisson-fume'],
                    ['Capitaine braisé', 'Capitaine braisé, prêt à manger.', 7500, 'Prêt à manger', 'capitaine-braise'],
                    ["Sardines à l'huile (125 g)", 'Boîte de sardines.', 700, 'Conserves', 'sardines'],
                ],
            ],
            [
                'name' => 'Boucherie du Centre-Ville', 'category' => 'boucherie-poissonnerie', 'neighborhood' => 'Charbonnages',
                'hours' => StoreHours::everyDay('07:00', '19:00', [7]), 'open' => true, 'cover' => 'viande-comptoir',
                'description' => 'Viande de bœuf, volailles et charcuterie, découpées à la demande par notre boucher.',
                'landmarks' => 'Charbonnages, rue du marché', 'phone' => '062 08 74 31',
                'products' => [
                    ['Bœuf à ragoût (1 kg)', 'Morceaux de bœuf pour ragoûts et sauces.', 5500, 'Bœuf', 'boeuf'],
                    ['Entrecôte (500 g)', 'Entrecôte persillée, à griller.', 4500, 'Bœuf', 'entrecote'],
                    ['Saucisses (500 g)', 'Saucisses fraîches à griller.', 3500, 'Charcuterie', 'saucisses'],
                    ['Brochettes de bœuf prêtes à griller (x10)', 'Dix brochettes marinées.', 3500, 'Bœuf', 'brochettes-boeuf'],
                    ['Poulet braisé prêt à manger', 'Poulet rôti à la broche, chaud.', 5500, 'Volaille', 'poulet-rotisserie'],
                ],
            ],
        ];
    }

    /**
     * Rayon pharmacie. Prix dans l'ordre : paracétamol, antipaludique, test de paludisme, moustiquaire,
     * sels de réhydratation, sirop, thermomètre, pansements, vitamines, masques, gel (+ gamme étendue).
     *
     * @param  list<int>  $prices
     * @return list<array<int, mixed>>
     */
    private static function pharmacy(array $prices, bool $extended = false): array
    {
        [$para, $malaria, $test, $net, $ors, $syrup, $thermo, $plasters, $vitamins, $masks, $gel] = $prices;

        $items = [
            ['Paracétamol 500 mg (boîte de 16)', 'Antalgique et antipyrétique. Lire la notice avant usage.', $para, 'Médicaments', 'paracetamol'],
            ['Antipaludique artéméther-luméfantrine', 'Traitement du paludisme simple, boîte de 24 comprimés. Sur avis médical.', $malaria, 'Médicaments', 'antipaludique'],
            ['Sirop contre la toux (125 ml)', 'Sirop adulte pour toux sèche et grasse.', $syrup, 'Médicaments', 'sirop-toux'],
            ['Sels de réhydratation orale (x10)', 'Sachets à diluer, en cas de diarrhée ou de déshydratation.', $ors, 'Médicaments', 'sels-rehydratation'],
            ['Complexe de vitamines (30 gélules)', 'Complément multivitaminé pour le quotidien.', $vitamins, 'Santé', 'vitamines'],
            ['Test de diagnostic rapide du paludisme', 'Autotest à résultat en 15 minutes.', $test, 'Matériel', 'test-paludisme'],
            ['Moustiquaire imprégnée', 'Moustiquaire 2 places imprégnée longue durée.', $net, 'Prévention', 'moustiquaire'],
            ['Thermomètre digital', 'Thermomètre électronique à embout souple.', $thermo, 'Matériel', 'thermometre'],
            ['Pansements assortis (x30)', 'Boîte de pansements de tailles variées.', $plasters, 'Matériel', 'pansements'],
            ['Masques chirurgicaux (x50)', 'Boîte de 50 masques à usage unique.', $masks, 'Hygiène', 'masques'],
            ['Gel hydroalcoolique (100 ml)', 'Gel désinfectant pour les mains.', $gel, 'Hygiène', 'gel-hydro'],
        ];

        if ($extended) {
            $items[] = ['Vitamine C 1000 mg (tube de 20)', 'Comprimés effervescents.', 2000, 'Santé', 'vitamine-c'];
            $items[] = ['Crème anti-moustiques (100 ml)', 'Protection jusqu’à 6 heures.', 4000, 'Prévention', 'creme-anti-moustiques'];
            $items[] = ['Tensiomètre de poignet', 'Mesure automatique de la tension artérielle.', 15000, 'Matériel', 'tensiometre'];
            $items[] = ['Dentifrice protection complète', 'Dentifrice fluoré, 75 ml.', 1500, 'Hygiène', 'dentifrice'];
            $items[] = ['Shampoing doux (250 ml)', 'Shampoing pour usage quotidien.', 3500, 'Hygiène', 'shampoing'];
        }

        return $items;
    }

    /**
     * Rayons d'épicerie. $variant (0 à 3) change les articles proposés pour que les commerces ne soient
     * pas identiques ; $staples = prix du riz, de l'huile, du sucre, du lait en poudre et de l'eau.
     *
     * @param  list<int>  $staples
     * @return list<array<int, mixed>>
     */
    private static function grocery(int $variant, array $staples): array
    {
        [$rice, $oil, $sugar, $milk, $water] = $staples;

        $base = [
            ['Riz parfumé (5 kg)', 'Sac de riz long grain parfumé.', $rice, 'Épicerie', 'riz'],
            ['Huile végétale (1 L)', 'Huile de cuisson raffinée.', $oil, 'Épicerie', 'huile'],
            ['Sucre en poudre (1 kg)', 'Sucre blanc cristallisé.', $sugar, 'Épicerie', 'sucre'],
            ['Lait en poudre (400 g)', 'Lait entier en poudre.', $milk, 'Épicerie', 'lait-poudre'],
            ["Pack d'eau minérale (6 x 1,5 L)", 'Eau minérale naturelle.', $water, 'Boissons', 'eau-minerale'],
            ['Spaghetti (500 g)', 'Pâtes de blé dur.', 600, 'Épicerie', 'spaghetti'],
            ['Concentré de tomate (400 g)', 'Double concentré de tomate.', 900, 'Épicerie', 'concentre-tomate'],
            ["Sardines à l'huile (125 g)", 'Boîte de sardines.', 700, 'Conserves', 'sardines'],
        ];

        $extras = [
            0 => [
                ['Farine de blé (1 kg)', 'Farine pour pains, beignets et gâteaux.', 1200, 'Épicerie', 'farine'],
                ['Lentilles corail (500 g)', 'Lentilles à cuisson rapide.', 1500, 'Épicerie', 'lentilles'],
                ['Café en grains (250 g)', 'Café torréfié, à moudre.', 3000, 'Petit-déjeuner', 'cafe-grains'],
                ['Thé en sachets (boîte)', 'Assortiment de thés et infusions.', 2500, 'Petit-déjeuner', 'the-boites'],
                ['Biscuits (paquet)', 'Biscuits au chocolat.', 800, 'Petit-déjeuner', 'biscuits'],
                ['Céréales du matin (500 g)', 'Céréales croquantes aux fruits.', 3500, 'Petit-déjeuner', 'cereales'],
                ['Confiture de fraise (370 g)', 'Confiture de fraise.', 2500, 'Petit-déjeuner', 'confiture'],
                ['Œufs (plateau de 30)', 'Œufs frais calibre moyen.', 4500, 'Produits frais', 'oeufs'],
                ['Piment frais (sachet)', 'Piment local, environ 100 g.', 300, 'Produits frais', 'piment'],
            ],
            1 => [
                ['Savon de ménage (x4)', 'Lot de quatre savons.', 1200, 'Entretien', 'savon'],
                ['Lessive en poudre (1 kg)', 'Lessive pour lavage à la main et en machine.', 2200, 'Entretien', 'lessive'],
                ['Piment frais (sachet)', 'Piment local, environ 100 g.', 300, 'Produits frais', 'piment'],
                ['Chocolat en tablette (100 g)', 'Chocolat au lait.', 1200, 'Douceurs', 'chocolat'],
                ['Biscuits (paquet)', 'Biscuits au chocolat.', 800, 'Douceurs', 'biscuits'],
                ['Dentifrice protection complète', 'Dentifrice fluoré, 75 ml.', 1500, 'Hygiène', 'dentifrice'],
                ['Shampoing doux (250 ml)', 'Shampoing pour usage quotidien.', 3500, 'Hygiène', 'shampoing'],
            ],
            2 => [
                ['Farine de blé (1 kg)', 'Farine pour pains, beignets et gâteaux.', 1100, 'Épicerie', 'farine'],
                ['Lentilles corail (500 g)', 'Lentilles à cuisson rapide.', 1400, 'Épicerie', 'lentilles'],
                ['Café en grains (250 g)', 'Café torréfié, à moudre.', 2900, 'Petit-déjeuner', 'cafe-grains'],
                ['Thé en sachets (boîte)', 'Assortiment de thés et infusions.', 2400, 'Petit-déjeuner', 'the-boites'],
                ['Céréales du matin (500 g)', 'Céréales croquantes aux fruits.', 3400, 'Petit-déjeuner', 'cereales'],
                ['Confiture de fraise (370 g)', 'Confiture de fraise.', 2400, 'Petit-déjeuner', 'confiture'],
                ['Biscuits (paquet)', 'Biscuits au chocolat.', 700, 'Douceurs', 'biscuits'],
                ['Cookies aux pépites (paquet)', 'Cookies moelleux.', 1200, 'Douceurs', 'cookies'],
                ['Chocolat en tablette (100 g)', 'Chocolat au lait.', 1100, 'Douceurs', 'chocolat'],
                ['Œufs (plateau de 30)', 'Œufs frais calibre moyen.', 4400, 'Produits frais', 'oeufs'],
                ['Yaourt nature (x4)', 'Quatre yaourts nature.', 1500, 'Produits frais', 'yaourt'],
                ['Bananes douces (1 kg)', 'Bananes mûres.', 1000, 'Fruits et légumes', 'bananes'],
                ['Tomates (1 kg)', 'Tomates bien mûres.', 1500, 'Fruits et légumes', 'tomates'],
                ['Oignons rouges (1 kg)', 'Oignons rouges frais.', 1200, 'Fruits et légumes', 'oignons-rouges'],
                ['Savon de ménage (x4)', 'Lot de quatre savons.', 1200, 'Entretien', 'savon'],
                ['Lessive en poudre (1 kg)', 'Lessive pour lavage à la main et en machine.', 2200, 'Entretien', 'lessive'],
                ['Dentifrice protection complète', 'Dentifrice fluoré, 75 ml.', 1500, 'Hygiène', 'dentifrice'],
                ['Shampoing doux (250 ml)', 'Shampoing pour usage quotidien.', 3500, 'Hygiène', 'shampoing'],
                ['Jus d’orange (1 L)', 'Jus d’orange 100 % fruit.', 2000, 'Boissons', 'jus-orange'],
                ['Coca-Cola (pack de 6)', 'Six bouteilles de 33 cl.', 3800, 'Boissons', 'coca-glace'],
            ],
            3 => [
                ['Farine de blé (1 kg)', 'Farine pour pains, beignets et gâteaux.', 1200, 'Épicerie', 'farine'],
                ['Café en grains (250 g)', 'Café torréfié, à moudre.', 3000, 'Petit-déjeuner', 'cafe-grains'],
                ['Biscuits (paquet)', 'Biscuits au chocolat.', 800, 'Petit-déjeuner', 'biscuits'],
                ['Confiture de fraise (370 g)', 'Confiture de fraise.', 2500, 'Petit-déjeuner', 'confiture'],
                ['Œufs (plateau de 30)', 'Œufs frais calibre moyen.', 4500, 'Produits frais', 'oeufs'],
                ['Yaourt nature (x4)', 'Quatre yaourts nature.', 1500, 'Produits frais', 'yaourt'],
                ['Jus d’orange (1 L)', 'Jus d’orange 100 % fruit.', 2000, 'Boissons', 'jus-orange'],
                ['Savon de ménage (x4)', 'Lot de quatre savons.', 1200, 'Entretien', 'savon'],
            ],
        ];

        return [...$base, ...$extras[$variant]];
    }

    /**
     * Produits d'un commerce, références '@clé' développées.
     *
     * @param  list<array<int, mixed>>  $products
     * @return list<array{0: string, 1: string, 2: int, 3: ?string, 4: string}>
     */
    public static function expand(array $products): array
    {
        $common = self::common();

        return array_map(function (array $row) use ($common) {
            if (is_string($row[0]) && str_starts_with($row[0], '@')) {
                $item = $common[substr($row[0], 1)] ?? throw new \LogicException("Article commun inconnu : {$row[0]}");
                if (isset($row[1])) {
                    $item[2] = $row[1];
                }

                return $item;
            }

            return $row;
        }, $products);
    }
}
