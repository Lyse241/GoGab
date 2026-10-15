<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP sur toutes les pages :
 *  - X-Content-Type-Options : le navigateur ne « devine » pas le type d'un fichier ;
 *  - X-Frame-Options : l'application ne s'affiche pas dans le cadre d'un autre site (clickjacking) ;
 *  - Referrer-Policy : une page externe ne reçoit que le domaine d'origine ;
 *  - Permissions-Policy : géolocalisation (suivi du livreur) et caméra (photos de documents) réservées à
 *    Gogab, micro et paiement désactivés ;
 *  - Strict-Transport-Security : en HTTPS seulement, jamais en local.
 *
 * Pas de Content-Security-Policy pour l'instant : Vite (développement) et les cartes Leaflet utilisent
 * des scripts et styles intégrés ; à ajouter avec les bonnes sources avant la mise en production.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(), payment=()');

        if ($request->isSecure() && ! app()->isLocal()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
