<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PWA : manifeste, icônes, service worker et page hors ligne. Ce sont des fichiers statiques de
 * public/ ; le test vérifie qu'ils sont cohérents entre eux (une icône déclarée existe).
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    private function manifest(): array
    {
        return json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_manifest_is_installable(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('Gogab', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $manifest['theme_color']);

        // Chrome exige une icône 192 et une 512 ; une maskable évite le cadre blanc sur Android.
        $sizes = collect($manifest['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($manifest['icons'])->pluck('purpose')->all());
    }

    public function test_every_declared_icon_exists_with_its_declared_size(): void
    {
        foreach ($this->manifest()['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path, "Icône manquante : {$icon['src']}");

            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}", "Taille incorrecte : {$icon['src']}");
        }
    }

    public function test_service_worker_caches_nothing_private_and_has_an_offline_page(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        // La page hors ligne est précachée, et seuls les fichiers compilés sont mis en cache.
        $this->assertStringContainsString('/offline.html', $worker);
        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertSame(1, substr_count($worker, 'cache.put('), 'une seule écriture en cache : les fichiers de /build/assets');
        $this->assertStringContainsString('/build/assets/', $worker);
    }

    public function test_pages_link_the_manifest(): void
    {
        $this->get('/')->assertOk()->assertSee('rel="manifest" href="/manifest.webmanifest"', false);
    }
}
