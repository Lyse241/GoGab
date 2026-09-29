<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    /**
     * Bloque les actions sensibles (commander, livrer, gérer un commerce, administrer)
     * tant que le compte n'a pas été validé par un administrateur.
     *
     * Utilisation : ->middleware(['auth', 'role:client', 'approved'])
     * Le compte peut toujours se connecter, consulter le catalogue et son profil.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isApproved()) {
            return $next($request);
        }

        $message = match ($user->account_status) {
            AccountStatus::Rejected => 'Votre inscription a été refusée : corrigez votre dossier pour accéder à cette page.',
            AccountStatus::Suspended => 'Votre compte est suspendu : cette action n’est pas disponible.',
            default => 'Votre compte est en cours de validation : cette action sera disponible dès qu’un administrateur l’aura validé.',
        };

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route($user->accountStatusRoute())->with('warning', $message);
    }
}
