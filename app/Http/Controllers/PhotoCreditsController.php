<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Crédits des photos de démonstration (public/images/demo/credits.json). Les photos sous licence
 * CC BY / CC BY-SA exigent de citer l'auteur, la licence et la source : elles sont détaillées ici.
 */
class PhotoCreditsController extends Controller
{
    public function __invoke(): Response
    {
        $path = public_path('images/demo/credits.json');
        $credits = collect(is_file($path) ? json_decode(file_get_contents($path), true) : []);

        $attributed = $credits->filter(fn (array $credit) => str_contains($credit['licence'], 'CC BY'));

        return Inertia::render('Public/PhotoCredits', [
            'attributed' => $attributed->map(fn (array $credit) => [
                'title' => $credit['title'] ? preg_replace('/\.(jpe?g|png)$/i', '', $credit['title']) : null,
                'author' => $credit['author'],
                'licence' => $credit['licence'],
                'source' => $credit['source'],
                'page' => $credit['page'],
            ])->unique(fn (array $credit) => $credit['page'])->sortBy('author')->values(),
            'others' => $credits->diffKeys($attributed)->groupBy('source')->map->count()->sortDesc(),
        ]);
    }
}
