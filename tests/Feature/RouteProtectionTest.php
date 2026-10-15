<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Filet de sécurité : chaque route de l'application, appelée par un visiteur non connecté, est soit
 * explicitement publique, soit refusée (redirection vers la connexion, 401 ou 403). Une nouvelle
 * route oubliée sans middleware d'authentification fait échouer ce test.
 */
class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** Routes que tout visiteur peut appeler : adresse => méthodes publiques. */
    private const PUBLIC_ROUTES = [
        '/' => ['GET'],
        'up' => ['GET'],
        'search' => ['GET'],
        'stores/{store}' => ['GET'],
        'stores/{store}/status' => ['GET'],
        'credits-photos' => ['GET'],
        'design-system' => ['GET'],
        'cart' => ['GET'],
        'sanctum/csrf-cookie' => ['GET'],
        'login' => ['GET', 'POST'],
        'register' => ['GET'],
        'register/client' => ['GET', 'POST'],
        'register/delivery' => ['GET', 'POST'],
        'register/delivery/check' => ['POST'],
        'register/business' => ['GET', 'POST'],
        'register/business/check' => ['POST'],
        'forgot-password' => ['GET', 'POST'],
        'reset-password/{token}' => ['GET'],
        'reset-password' => ['POST'],
        // Redirige le visiteur vers la connexion en mémorisant son panier.
        'cart/{store}/login' => ['GET'],
    ];

    public function test_every_route_is_either_public_on_purpose_or_refused_to_visitors(): void
    {
        $checked = 0;
        $problems = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            // Fichiers publics de Laravel (stockage), routes internes et page 404 générique.
            if (str_starts_with($uri, '_') || str_contains($uri, 'storage/{path') || str_contains($uri, 'fallbackPlaceholder')) {
                continue;
            }
            // Redirections permanentes d'anciennes adresses (/admin/dashboard → /admin) : la cible est protégée.
            if (ltrim($route->getActionName(), '\\') === \Illuminate\Routing\RedirectController::class) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                if (in_array($method, self::PUBLIC_ROUTES[$uri] ?? [], true)) {
                    continue;
                }

                $path = '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $uri), '/');
                $response = $this->json($method, $path);
                $checked++;

                // Refusé : 401 (non connecté) ou 403. Une redirection n'est pas possible en JSON.
                if (! in_array($response->getStatusCode(), [401, 403], true)) {
                    $problems[] = "{$method} {$uri} → {$response->getStatusCode()}";
                }
            }
        }

        $this->assertGreaterThan(80, $checked, 'le test doit couvrir toutes les routes protégées');
        $this->assertSame([], $problems, "Routes accessibles à un visiteur sans être déclarées publiques :\n".implode("\n", $problems));
    }

    public function test_pages_carry_the_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('microphone=()', $response->headers->get('Permissions-Policy'));
        // Pas de HSTS en HTTP simple (local).
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_sensitive_public_forms_are_rate_limited(): void
    {
        // Mot de passe oublié : 6 par minute, puis 429 (sinon une boîte mail peut être inondée).
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => 'quelquun@example.com']);
        }
        $this->post('/forgot-password', ['email' => 'quelquun@example.com'])->assertStatus(429);
    }
}
