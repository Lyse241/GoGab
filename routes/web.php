<?php

use App\Http\Controllers\AccountCorrectionController;
use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\AccountWarningsController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Business;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Delivery;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhotoCreditsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Catalogue public
Route::get('/', [StoreController::class, 'index'])->name('home');
Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');
// Crédits des photos de démonstration (auteurs et licences Creative Commons).
Route::get('/credits-photos', PhotoCreditsController::class)->name('credits.photos');
Route::get('/search', SearchController::class)->name('search');
Route::get('/stores/{store}/status', [StoreController::class, 'status'])->name('stores.status');

// Vitrine des composants UI, pour validation visuelle : uniquement en environnement local.
Route::get('/design-system', function () {
    abort_unless(app()->isLocal(), 404);

    return Inertia::render('DesignSystem');
})->name('design-system');

// Paniers : un par commerce, gérés côté React (localStorage). `?store=` ouvre celui d'un commerce.
Route::get('/cart', fn (Request $request) => Inertia::render('Cart/Index', [
    'openStore' => $request->integer('store') ?: null,
]))->name('cart');
// Visiteur qui veut commander : connexion puis retour sur ce panier.
Route::get('/cart/{store}/login', [CheckoutController::class, 'login'])->middleware('guest')->name('cart.login');

// Tunnel de commande : un checkout = le panier d'UN seul commerce. Un client en attente de
// validation voit la page (avec un message à la place du bouton) mais ne peut pas envoyer.
Route::get('/checkout/{store}', [CheckoutController::class, 'create'])
    ->middleware(['auth', 'role:client'])
    ->name('checkout');
// « Appliquer » un code promo (prix et réduction recalculés par le serveur).
// Limité : empêche d'essayer des codes au hasard jusqu'à en trouver un valide.
Route::post('/checkout/{store}/promo', [CheckoutController::class, 'promo'])
    ->middleware(['auth', 'role:client', 'throttle:20,1'])
    ->name('checkout.promo');

// Commande réservée aux clients dont le compte est validé
// (un client en attente peut parcourir le catalogue, pas commander).
Route::middleware(['auth', 'role:client', 'approved'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'legacy']);
    // Limité : une commande toutes les quelques secondes suffit à un client, pas à un robot.
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1')->name('orders.store');
});

// Mes commandes et suivi : réservés au client (OrderPolicy::view → 403 sur la commande d'un autre).
Route::middleware(['auth', 'role:client'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

// Point d'entrée « Mon espace » : l'espace du rôle, ou la page d'état d'un compte non validé.
Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->homeRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

// Comptes non validés : l'utilisateur peut se connecter et voir où en est son dossier.
Route::middleware('auth')->prefix('account')->name('account.')->controller(AccountStatusController::class)->group(function () {
    Route::get('/pending', 'pending')->name('pending');
    Route::get('/rejected', 'rejected')->name('rejected');
    Route::get('/suspended', 'suspended')->name('suspended');
});

// Correction d'un dossier refusé, puis renvoi pour validation (UserPolicy::resubmit).
Route::middleware('auth')->prefix('account/rejected')->name('account.')->controller(AccountCorrectionController::class)->group(function () {
    Route::get('/correction', 'edit')->name('correction');
    Route::post('/correction', 'update')->name('correction.update');
});

// Avertissements reçus de la modération (« Mes avertissements », « J'ai compris »).
Route::middleware('auth')->prefix('account/warnings')->name('account.warnings')->controller(AccountWarningsController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/{warning}/acknowledge', 'acknowledge')->name('.acknowledge');
});

// Pièces justificatives (disque privé) : propriétaire ou administrateur uniquement (DocumentPolicy::view).
Route::get('/documents/{document}', [DocumentController::class, 'show'])
    ->middleware(['auth', 'can:view,document'])
    ->name('documents.show');

// Anciennes adresses des espaces (favoris).
Route::permanentRedirect('/delivery/dashboard', '/delivery');
Route::permanentRedirect('/admin/dashboard', '/admin');

// Changement de statut d'une commande, pour tous les rôles : OrderWorkflow vérifie le rôle,
// le statut et que l'acteur est concerné (sinon message d'erreur, commande inchangée).
Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])
    ->middleware(['auth', 'approved'])
    ->name('orders.status.update');

// Suivi en direct : position du livreur et arrivée estimée (OrderPolicy::view).
Route::get('/orders/{order}/tracking', [TrackingController::class, 'show'])
    ->middleware(['auth', 'approved'])->name('orders.tracking');

// Avis du client sur une commande livrée (OrderPolicy::review : une seule fois).
Route::post('/orders/{order}/review', [ReviewController::class, 'store'])
    ->middleware(['auth', 'role:client', 'approved', 'can:review,order'])
    ->name('orders.review');

// « Signaler un problème » : uniquement l'autre partie d'une commande (OrderPolicy::report).
Route::post('/orders/{order}/reports', [ReportController::class, 'store'])
    ->middleware(['auth', 'approved', 'can:report,order'])
    ->name('reports.store');

// Espace livreur (Mobile-First) : accueil, offres de sa zone, course en cours, historique, profil.
Route::middleware(['auth', 'role:delivery', 'approved'])->prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/', Delivery\HomeController::class)->name('dashboard');
    Route::get('/offers', [DeliveryController::class, 'offers'])->name('offers');
    Route::post('/orders/{order}/accept', [DeliveryController::class, 'accept'])->name('orders.accept');
    // Position du livreur pendant sa course (toutes les ~8 s : limite large, 30 par minute).
    Route::post('/orders/{order}/location', [Delivery\LocationController::class, 'store'])
        ->middleware('throttle:30,1')->name('orders.location');
    // « Je suis arrivé au commerce » (phase de retrait) : prévient l'entreprise et le client.
    Route::post('/orders/{order}/arrived-at-store', [Delivery\LocationController::class, 'arrivedAtStore'])
        ->middleware('throttle:10,1')->name('orders.arrived-at-store');
    Route::get('/current', [DeliveryController::class, 'current'])->name('current');
    Route::get('/history', [DeliveryController::class, 'history'])->name('history');
    Route::get('/wallet', WalletController::class)->name('wallet');
    Route::get('/profile', [Delivery\ProfileController::class, 'show'])->name('profile');
    Route::patch('/availability', [Delivery\ProfileController::class, 'availability'])->name('availability');
    Route::patch('/profile/base-neighborhood', [Delivery\ProfileController::class, 'updateBaseNeighborhood'])->name('profile.base-neighborhood');
});

// Espace entreprise : un compte entreprise validé gère son propre commerce (StorePolicy::manage).
Route::middleware(['auth', 'role:business', 'approved'])->prefix('business')->name('business.')->group(function () {
    Route::get('/', [Business\StoreController::class, 'dashboard'])->name('dashboard');
    Route::get('/wallet', WalletController::class)->name('wallet');
    Route::get('/reviews', [Business\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/store/open', [Business\StoreController::class, 'toggleOpen'])->name('store.open');
    Route::get('/store', [Business\StoreController::class, 'edit'])->name('store.edit');
    // POST (et non PUT) : envoi de fichiers en multipart.
    Route::post('/store', [Business\StoreController::class, 'update'])->name('store.update');

    // Catalogue (ProductPolicy : uniquement les produits de son commerce).
    Route::controller(Business\ProductController::class)->prefix('/products')->name('products.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->middleware('can:create,App\Models\Product')->name('store');
        Route::get('/{product}/edit', 'edit')->middleware('can:update,product')->name('edit');
        // POST (et non PUT) : envoi de photo en multipart.
        Route::post('/{product}', 'update')->middleware('can:update,product')->name('update');
        Route::patch('/{product}/availability', 'availability')->middleware('can:update,product')->name('availability');
        Route::delete('/{product}', 'destroy')->middleware('can:delete,product')->name('destroy');
    });

    // Commandes reçues, en direct (actions : PUT /orders/{order}/status → OrderWorkflow).
    Route::get('/orders', [Business\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Business\OrderController::class, 'show'])->middleware('can:view,order')->name('orders.show');
    // Relance de l'annonce de livraison restée sans livreur (OrderWorkflow::relaunch).
    Route::post('/orders/{order}/relaunch', [Business\OrderController::class, 'relaunch'])->middleware('can:view,order')->name('orders.relaunch');
});

Route::middleware(['auth', 'role:admin', 'approved'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/accounts', [Admin\AccountController::class, 'index'])->name('accounts.index');
    // Validation d'un compte et de ses documents (UserPolicy / DocumentPolicy::review).
    Route::get('/accounts/{user}', [Admin\AccountController::class, 'show'])->middleware('can:review,user')->name('accounts.show');
    Route::post('/accounts/{user}/approve', [Admin\AccountValidationController::class, 'approve'])->middleware('can:review,user')->name('accounts.approve');
    Route::post('/accounts/{user}/reject', [Admin\AccountValidationController::class, 'reject'])->middleware('can:review,user')->name('accounts.reject');
    Route::post('/documents/{document}/approve', [Admin\AccountValidationController::class, 'approveDocument'])->middleware('can:review,document')->name('documents.approve');
    Route::post('/documents/{document}/reject', [Admin\AccountValidationController::class, 'rejectDocument'])->middleware('can:review,document')->name('documents.reject');

    // Annuaires par rôle (tous statuts, filtre « Comptes signalés »).
    Route::get('/clients', [Admin\AccountController::class, 'clients'])->name('clients.index');
    Route::get('/deliveries', [Admin\AccountController::class, 'deliveries'])->name('deliveries.index');
    Route::get('/businesses', [Admin\AccountController::class, 'businesses'])->name('businesses.index');

    // Modération (UserPolicy::moderate) : avertissement, blocage, déblocage, signalement interne.
    Route::get('/moderation', [Admin\ModerationController::class, 'index'])->name('moderation.index');
    // Signalements des utilisateurs (ReportPolicy : admins validés).
    Route::get('/reports', [Admin\ReportController::class, 'index'])->middleware('can:viewAny,App\Models\Report')->name('reports.index');
    Route::get('/reports/{report}', [Admin\ReportController::class, 'show'])->middleware('can:view,report')->name('reports.show');
    Route::post('/reports/{report}/handle', [Admin\ReportController::class, 'handle'])->middleware('can:view,report')->name('reports.handle');
    Route::prefix('/accounts/{user}/moderation')->name('accounts.moderation.')->middleware('can:moderate,user')->controller(Admin\ModerationController::class)->group(function () {
        Route::post('/warn', 'warn')->name('warn');
        Route::post('/block', 'block')->name('block');
        Route::post('/unblock', 'unblock')->name('unblock');
        Route::post('/flag', 'flag')->name('flag');
        Route::post('/unflag', 'unflag')->name('unflag');
    });

    // Référentiels : catégories de commerces, quartiers et zones.
    Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('neighborhoods', Admin\NeighborhoodController::class)->only(['index', 'store', 'update']);

    // Supervision des commandes (annulation : PUT orders.status.update → OrderWorkflow).
    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [Admin\OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/relaunch', [Admin\OrderController::class, 'relaunch'])->name('orders.relaunch');
    // Modération des avis (masquer le commentaire d'un avis abusif).
    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/visibility', [Admin\ReviewController::class, 'visibility'])->name('reviews.visibility');
    // Codes promo (réduction financée par Gogab).
    Route::get('/promo-codes', [Admin\PromoCodeController::class, 'index'])->name('promo-codes.index');
    Route::post('/promo-codes', [Admin\PromoCodeController::class, 'store'])->name('promo-codes.store');
    Route::put('/promo-codes/{promoCode}', [Admin\PromoCodeController::class, 'update'])->name('promo-codes.update');
    Route::patch('/promo-codes/{promoCode}/toggle', [Admin\PromoCodeController::class, 'toggle'])->name('promo-codes.toggle');
    // Finances : soldes (WalletService) et règlements des entreprises et livreurs.
    Route::get('/finances', [Admin\FinanceController::class, 'index'])->name('finances.index');
    Route::post('/finances/{user}/settle', [Admin\FinanceController::class, 'settle'])->name('finances.settle');
    Route::resource('stores', Admin\StoreController::class)->except('show');
    // Produits rattachés à une boutique : /admin/stores/{store}/products/create, /admin/products/{product}/edit…
    Route::resource('stores.products', Admin\ProductController::class)
        ->shallow()
        ->only(['create', 'store', 'edit', 'update', 'destroy']);
});

Route::middleware('auth')->group(function () {
    // Notifications in-app (tous les rôles).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    // Notifications push : abonner / désabonner l'appareil courant.
    Route::post('/push/subscription', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscription', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Livreur, entreprise : renvoyer ou remplacer un document (POST : envoi de fichier).
    Route::post('/profile/documents/{type}', [ProfileController::class, 'replaceDocument'])->name('profile.documents.replace');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// URL inconnue : 404 Gogab, en passant par les middlewares web (session, compte connecté,
// props partagées) pour proposer le bon bouton de retour selon le rôle.
Route::fallback(fn () => abort(404));
