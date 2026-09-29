<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Services\OrderPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /** Montants proposés en un clic pour le paiement à la livraison (FCFA). */
    public const QUICK_CASH_AMOUNTS = [5000, 10000, 20000];

    /**
     * Tunnel de commande du panier d'UN commerce (/checkout/{store}). Le panier vient du navigateur
     * (localStorage, clé du commerce) ; le serveur fournit le commerce, l'adresse du profil, les
     * modes de paiement et les frais de livraison. Un client en attente voit la page, sans pouvoir
     * valider (POST /orders est réservé aux comptes validés).
     */
    public function create(Request $request, Store $store): Response
    {
        abort_unless($store->isVisible(), 404);

        $user = $request->user();
        $store->load('openingHours');

        return Inertia::render('Checkout/Index', [
            'store' => $store->only(['id', 'name', 'logo']) + $store->openingStatus(),
            // Adresse du profil, modifiable pour cette commande.
            'address' => [
                'neighborhood_id' => $user->neighborhood_id,
                'address_landmarks' => $user->address_landmarks,
            ],
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name', 'zone']),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'hint' => $method->hint(),
                'mobile_money' => $method->isMobileMoney(),
            ]),
            'deliveryFee' => OrderPricing::deliveryFee(),
            'quickCashAmounts' => self::QUICK_CASH_AMOUNTS,
            'canOrder' => $user->isApproved(),
        ]);
    }

    /**
     * Ancienne adresse (panier unique) : les paniers sont maintenant par commerce.
     */
    public function legacy(): RedirectResponse
    {
        return redirect()->route('cart');
    }

    /**
     * « Commander » pour un visiteur : connexion, puis retour sur le panier de ce commerce.
     */
    public function login(Store $store): RedirectResponse
    {
        session()->put('url.intended', route('cart', ['store' => $store->id]));

        return redirect()->route('login');
    }
}
