<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Neighborhood;
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
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()]),
        ]);
    }
}
