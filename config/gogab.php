<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuseau horaire métier
    |--------------------------------------------------------------------------
    |
    | Fuseau de Libreville, utilisé pour tous les calculs d'horaires d'ouverture
    | (App\Services\StoreHours). L'application reste en UTC pour le stockage ;
    | l'heure du navigateur n'est jamais utilisée.
    |
    */

    'timezone' => env('GOGAB_TIMEZONE', 'Africa/Libreville'),

    /*
    |--------------------------------------------------------------------------
    | Validation automatique des clients
    |--------------------------------------------------------------------------
    |
    | false (par défaut) : un client inscrit reste « pending » jusqu'à validation
    | par un administrateur. true : il est approuvé immédiatement (pratique pour
    | une démonstration). Livreurs et entreprises restent toujours à valider.
    |
    */

    'auto_approve_clients' => (bool) env('GOGAB_AUTO_APPROVE_CLIENTS', false),

    /*
    |--------------------------------------------------------------------------
    | Frais de livraison
    |--------------------------------------------------------------------------
    |
    | Montant fixe en FCFA ajouté à chaque commande (App\Services\OrderPricing).
    |
    */

    'delivery_fee' => (int) env('GOGAB_DELIVERY_FEE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Commande bloquée en recherche de livreur
    |--------------------------------------------------------------------------
    |
    | Au-delà de ce délai (en minutes) sans livreur depuis l'annonce, la commande
    | est mise en évidence côté admin (App\Services\OrderSupervision) et peut être
    | relancée.
    |
    */

    'stuck_search_minutes' => (int) env('GOGAB_STUCK_SEARCH_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Icônes des catégories
    |--------------------------------------------------------------------------
    |
    | Noms d'icônes lucide autorisés pour une catégorie (choix de l'admin).
    | Chaque nom doit avoir son composant dans resources/js/utils/categoryIcons.js.
    |
    */

    'category_icons' => [
        'utensils', 'sandwich', 'pizza', 'beef', 'fish', 'soup', 'croissant', 'cake-slice',
        'ice-cream-cone', 'coffee', 'cup-soda', 'wine', 'pill', 'heart-pulse', 'baby',
        'shopping-basket', 'shopping-bag', 'apple', 'carrot', 'egg', 'shirt', 'smartphone',
        'flower', 'store',
    ],

];
