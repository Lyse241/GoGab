<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Avis reçus par le commerce de l'entreprise connectée : moyenne, répartition des notes et
 * commentaires (ceux masqués par l'admin n'apparaissent pas ; leur note reste comptée).
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $request->user()->store ?? abort(404);
        $reviews = Review::where('store_id', $store->id);

        $distribution = (clone $reviews)->selectRaw('store_rating, count(*) as total')->groupBy('store_rating')->pluck('total', 'store_rating');
        $count = (int) $distribution->sum();
        $average = $count > 0 ? round((float) (clone $reviews)->avg('store_rating'), 1) : null;

        return Inertia::render('Business/Reviews/Index', [
            'summary' => [
                'average' => $average,
                'count' => $count,
                'distribution' => collect([5, 4, 3, 2, 1])->map(fn (int $stars) => [
                    'stars' => $stars,
                    'count' => (int) ($distribution[$stars] ?? 0),
                ])->values(),
            ],
            'reviews' => $reviews->with('reviewer:id,name')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => $review->store_rating,
                    // Prénom seulement.
                    'client' => \Illuminate\Support\Str::before(trim($review->reviewer?->name ?? ''), ' ') ?: 'Client',
                    'comment' => $review->isHidden() ? null : $review->comment,
                    'comment_hidden' => $review->isHidden() && filled($review->comment),
                    'date' => $review->created_at->setTimezone(config('gogab.timezone'))->format('d/m/Y'),
                ]),
        ]);
    }
}
