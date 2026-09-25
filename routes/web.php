<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Catalogue public
Route::get('/', [StoreController::class, 'index'])->name('home');
Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');

// Panier : contenu géré côté React (localStorage), la page n'a besoin d'aucune donnée serveur.
Route::get('/cart', fn () => Inertia::render('Cart/Index'))->name('cart');

// Commande réservée aux clients connectés.
Route::middleware(['auth', 'role:client'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

// Point d'entrée après connexion / inscription : renvoie chaque rôle vers son espace.
Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->homeRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:delivery'])->group(function () {
    Route::get('/delivery/dashboard', [DeliveryController::class, 'dashboard'])->name('delivery.dashboard');
    Route::post('/delivery/orders/{order}/accept', [DeliveryController::class, 'accept'])->name('delivery.orders.accept');
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');
    Route::resource('stores', Admin\StoreController::class)->except('show');
    // Produits rattachés à une boutique : /admin/stores/{store}/products/create, /admin/products/{product}/edit…
    Route::resource('stores.products', Admin\ProductController::class)
        ->shallow()
        ->only(['create', 'store', 'edit', 'update', 'destroy']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
