<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Autorise la requête uniquement si l'utilisateur connecté a l'un des rôles donnés.
     *
     * Utilisation : ->middleware('role:admin') ou ->middleware('role:admin,delivery')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRole(...$roles)) {
            return redirect()
                ->route($user->homeRoute())
                ->with('error', "Accès refusé : cette page n'est pas accessible avec votre compte.");
        }

        return $next($request);
    }
}
