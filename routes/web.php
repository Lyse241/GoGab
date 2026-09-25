<?php

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

// Commande réservée aux clients connectés (page provisoire jusqu'à l'étape 4).
Route::get('/checkout', fn () => Inertia::render('Checkout/Index'))
    ->middleware(['auth', 'role:client'])
    ->name('checkout');

// Point d'entrée après connexion / inscription : renvoie chaque rôle vers son espace.
Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->homeRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:delivery'])->prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Delivery/Dashboard'))->name('dashboard');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
