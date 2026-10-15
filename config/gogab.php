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
    | Couverture des livreurs
    |--------------------------------------------------------------------------
    |
    | Chaque livreur choisit un rayon (km à vol d'oiseau autour de son quartier de départ) et reçoit
    | les courses des commerces situés dans ce rayon. Une annonce restée sans livreur après
    | `widen_after_minutes` est élargie à tous les livreurs disponibles à moins de `widened_km`
    | (le Grand Libreville) : App\Services\CourierCoverage.
    |
    */

    'coverage' => [
        'options' => [5, 10, 15, 25],
        'default_km' => (int) env('GOGAB_COVERAGE_DEFAULT_KM', 10),
        'widen_after_minutes' => (int) env('GOGAB_WIDEN_AFTER_MINUTES', 5),
        'widened_km' => (int) env('GOGAB_WIDENED_KM', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission de la plateforme
    |--------------------------------------------------------------------------
    |
    | Pourcentage prélevé sur le sous-total (hors frais de livraison) de chaque commande
    | livrée (App\Services\WalletService). Les frais de livraison reviennent au livreur.
    |
    */

    'commission_rate' => (float) env('GOGAB_COMMISSION_RATE', 15),

    /*
    |--------------------------------------------------------------------------
    | Notifications push (Web Push)
    |--------------------------------------------------------------------------
    |
    | Clés VAPID générées par `php artisan gogab:vapid --write`. Sans clés, les push sont
    | désactivés (les notifications in-app continuent). `subject` : contact exigé par les
    | services de notification (adresse https:// ou mailto:).
    |
    */

    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
    ],

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
