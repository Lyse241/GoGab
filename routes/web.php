<?php

use App\Http\Controllers\AccountCorrectionController;
use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\AccountWarningsController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Business;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Catalogue public
Route::get('/', [StoreController::class, 'index'])->name('home');
Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');
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

// Commande réservée aux clients dont le compte est validé
// (un client en attente peut parcourir le catalogue, pas commander).
Route::middleware(['auth', 'role:client', 'approved'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'legacy']);
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
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

Route::middleware(['auth', 'role:delivery', 'approved'])->group(function () {
    Route::get('/delivery', [DeliveryController::class, 'dashboard'])->name('delivery.dashboard');
    Route::post('/delivery/orders/{order}/accept', [DeliveryController::class, 'accept'])->name('delivery.orders.accept');
});

// Espace entreprise : un compte entreprise validé gère son propre commerce (StorePolicy::manage).
Route::middleware(['auth', 'role:business', 'approved'])->prefix('business')->name('business.')->group(function () {
    Route::get('/', [Business\StoreController::class, 'dashboard'])->name('dashboard');
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

    // Sections pas encore construites : état vide « Bientôt disponible ».
    Route::get('/orders', fn () => Inertia::render('ComingSoon', [
        'title' => 'Commandes',
        'description' => 'La section « Commandes » arrive dans une prochaine version de l’espace administrateur.',
        'back' => 'admin.dashboard',
    ]))->name('orders.index');
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
