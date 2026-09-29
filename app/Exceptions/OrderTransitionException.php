<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Transition de commande refusée par OrderWorkflow (mauvais rôle, mauvais statut, acteur non
 * concerné, commande déjà prise…). La commande n'est jamais modifiée.
 *
 * Rendu automatique : retour en arrière avec un message d'erreur (toast), ou 422 en JSON.
 */
class OrderTransitionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Order $order = null,
        public readonly ?OrderStatus $to = null,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
