<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modération des avis : l'admin peut masquer le commentaire d'un avis abusif (la note reste
 * comptée dans la moyenne) ou le réafficher.
 */
class ReviewController extends Controller
{
    public const FILTERS = ['comments', 'all', 'hidden'];

    public function index(Request $request): Response
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'comments';

        return Inertia::render('Admin/Reviews/Index', [
            'filter' => $filter,
            'reviews' => Review::with(['reviewer:id,name', 'store:id,name', 'courier:id,name'])
                ->when($filter === 'comments', fn ($query) => $query->whereNotNull('comment'))
                ->when($filter === 'hidden', fn ($query) => $query->whereNotNull('hidden_at'))
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Review $review) => [
                    'id' => $review->id,
                    'store' => $review->store?->name,
                    'client' => $review->reviewer?->name,
                    'courier' => $review->courier?->name,
                    'store_rating' => $review->store_rating,
                    'courier_rating' => $review->courier_rating,
                    'comment' => $review->comment,
                    'hidden' => $review->isHidden(),
                    'date' => $review->created_at->setTimezone(config('gogab.timezone'))->format('d/m/Y H:i'),
                ]),
        ]);
    }

    public function visibility(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        $reviews->setHidden($review, $request->user(), (bool) $data['hidden']);

        return back()->with('success', $data['hidden'] ? 'Commentaire masqué.' : 'Commentaire réaffiché.');
    }
}
