<?php

use App\Support\ErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Compte bloqué : renvoyé vers « compte suspendu » à chaque requête.
            \App\Http\Middleware\RedirectIfBlocked::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // role:admin,business → rôle requis ; approved → compte validé par l'admin.
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'approved' => \App\Http\Middleware\EnsureApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pages d'erreur Gogab (Inertia) à la place des pages Laravel par défaut.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()) {
                return $response;
            }

            // Session expirée pendant un formulaire Inertia : retour sur la page avec un message
            // (la saisie est conservée). Hors Inertia : page 419 Gogab.
            if ($status === 419 && $request->header('X-Inertia')) {
                return back()->with('error', 'La page a expiré. Réessayez.');
            }

            // En développement, on garde la page de debug détaillée pour les erreurs serveur.
            $friendly = config('app.debug') ? [403, 404, 419] : ErrorPage::STATUSES;

            return in_array($status, $friendly, true)
                ? ErrorPage::render($request, $status)
                : $response;
        });
    })->create();
