<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderTransitionException;
use App\Models\Order;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Avis du client sur une commande livrée (OrderPolicy::review : sa commande, livrée, pas déjà notée).
 */
class ReviewController extends Controller
{
    public function store(Request $request, Order $order, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate([
            'store_rating' => ['required', 'integer', 'between:1,5'],
            'courier_rating' => [$order->delivery_id ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'store_rating.required' => 'Notez le commerce de 1 à 5 étoiles.',
            'store_rating.between' => 'La note doit être comprise entre 1 et 5.',
            'courier_rating.required' => 'Notez le livreur de 1 à 5 étoiles.',
            'courier_rating.between' => 'La note doit être comprise entre 1 et 5.',
            'comment.max' => 'Le commentaire ne peut pas dépasser 500 caractères.',
        ]);

        try {
            $reviews->submit($order, $request->user(), $data);
        } catch (OrderTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Merci pour votre avis !');
    }
}
