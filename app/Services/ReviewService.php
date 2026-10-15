<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\OrderTransitionException;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Avis des clients : une fois la commande livrée, le client note le commerce et le livreur
 * (une seule fois, sans modification). Le commerce et le livreur sont notifiés.
 */
class ReviewService
{
    public function canReview(Order $order, User $user): bool
    {
        return $user->isClient()
            && $user->isApproved()
            && $order->client_id === $user->id
            && $order->status === OrderStatus::Delivered
            && ! $order->review()->exists();
    }

    /**
     * Modération : masque (ou réaffiche) le commentaire d'un avis ; la note reste comptée.
     * Seul un admin validé peut le faire.
     */
    public function setHidden(Review $review, User $admin, bool $hidden): Review
    {
        abort_unless($admin->isAdmin() && $admin->isApproved(), 403);

        $review->forceFill([
            'hidden_at' => $hidden ? now() : null,
            'hidden_by' => $hidden ? $admin->id : null,
        ])->save();

        return $review;
    }

    /**
     * Moyenne et nombre des notes reçues par un livreur (null tant qu'il n'a aucune note).
     *
     * @return array{average: float|null, count: int}
     */
    public function courierSummary(User $courier): array
    {
        $query = Review::where('courier_id', $courier->id)->whereNotNull('courier_rating');
        $average = $query->avg('courier_rating');

        return ['average' => $average !== null ? round((float) $average, 1) : null, 'count' => $query->count()];
    }

    /**
     * @param  array{store_rating: int, courier_rating?: int|null, comment?: string|null}  $data
     *
     * @throws OrderTransitionException avis impossible (commande non livrée, déjà noté…)
     */
    public function submit(Order $order, User $client, array $data): Review
    {
        if ($order->client_id !== $client->id || $order->status !== OrderStatus::Delivered) {
            throw new OrderTransitionException('Vous pouvez noter une commande une fois qu’elle est livrée.');
        }

        try {
            $review = Review::create([
                'order_id' => $order->id,
                'reviewer_id' => $client->id,
                'store_id' => $order->store_id,
                'courier_id' => $order->delivery_id,
                'store_rating' => (int) $data['store_rating'],
                'courier_rating' => $order->delivery_id ? ($data['courier_rating'] ?? null) : null,
                'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new OrderTransitionException('Vous avez déjà donné votre avis sur cette commande.');
        }

        $order->loadMissing(['store.owner', 'delivery']);
        $stars = fn (?int $note) => $note ? "{$note}/5" : '—';

        Notifier::send(
            $order->store->owner,
            "Nouvel avis sur {$order->reference}",
            "Note du commerce : {$stars($review->store_rating)}".($review->comment ? ' — « '.$review->comment.' »' : ''),
            route('business.orders.show', $order),
        );
        if ($order->delivery && $review->courier_rating) {
            Notifier::send($order->delivery, "Nouvel avis sur {$order->reference}", "Votre note : {$stars($review->courier_rating)}.", route('delivery.history'));
        }

        return $review;
    }
}
