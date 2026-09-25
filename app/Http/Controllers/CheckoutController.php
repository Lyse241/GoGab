<?php

namespace App\Http\Controllers;

use App\Models\Neighborhood;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Formulaire de commande : le panier vient du navigateur (localStorage),
     * le serveur fournit les quartiers et les modes de paiement.
     */
    public function create(): Response
    {
        return Inertia::render('Checkout/Index', [
            'neighborhoods' => Neighborhood::orderBy('name')->get(['id', 'name']),
            'paymentMethods' => collect(Order::PAYMENT_METHODS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }
}
