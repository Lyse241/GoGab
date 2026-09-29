<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfBlocked
{
    /**
     * Pages encore accessibles à un compte bloqué : la page qui explique le blocage, et la déconnexion.
     */
    private const ALLOWED_ROUTES = ['account.suspended', 'logout'];

    /**
     * Un compte bloqué (statut suspended) est renvoyé vers la page « compte suspendu » à chaque requête :
     * il peut se connecter, mais seulement pour voir le motif et la date de fin du blocage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isBlocked() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Votre compte est bloqué.'], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('account.suspended');
    }
}
