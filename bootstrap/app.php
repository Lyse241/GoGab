<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pages d'erreur Gogab (Inertia) à la place des pages Laravel par défaut.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Session expirée (formulaire resté ouvert trop longtemps) : on revient en arrière avec un message.
            if ($status === 419) {
                return back()->with('error', 'La page a expiré. Veuillez réessayer.');
            }

            // En développement, on garde la page de debug détaillée pour les erreurs serveur.
            $friendly = [403, 404];
            if (! config('app.debug')) {
                $friendly = [...$friendly, 500, 503];
            }

            if (in_array($status, $friendly, true) && ! $request->expectsJson()) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
