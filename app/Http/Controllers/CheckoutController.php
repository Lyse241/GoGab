<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Neighborhood;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Commande du panier d'UN commerce (/checkout/{store}) : le panier vient du navigateur
     * (localStorage, clé du commerce), le serveur fournit le commerce, les quartiers et les
     * modes de paiement. Un checkout ne mélange jamais deux commerces.
     */
    public function create(Store $store): Response
    {
        abort_unless($store->isVisible(), 404);

        return Inertia::render('Checkout/Index', [
            'store' => $store->only(['id', 'name', 'logo']),
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name']),
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()]),
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
