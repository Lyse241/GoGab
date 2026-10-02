<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryProfile;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Espace livreur : disponibilité, quartier de base (donc zone des annonces), course en cours
 * et compteurs du jour (journée de Libreville).
 */
class CourierProfileService
{
    /**
     * Statuts d'une course en cours pour le livreur assigné.
     *
     * @var list<OrderStatus>
     */
    public const ACTIVE_STATUSES = [OrderStatus::CourierAssigned, OrderStatus::Delivering, OrderStatus::Arrived];

    /**
     * « Je suis disponible / indisponible ». Indisponible : aucune annonce ni notification
     * d'annonce (OrderWorkflow::couriersForZone) ; une course déjà acceptée continue.
     */
    public function setAvailability(User $courier, bool $available): DeliveryProfile
    {
        $profile = $this->profile($courier);
        $profile->update(['is_available' => $available]);

        return $profile;
    }

    /**
     * Change le quartier de base : la zone des annonces suit immédiatement.
     */
    public function setBaseNeighborhood(User $courier, Neighborhood $neighborhood): DeliveryProfile
    {
        $profile = $this->profile($courier);
        $profile->update(['base_neighborhood_id' => $neighborhood->id]);

        return $profile->load('baseNeighborhood');
    }

    /**
     * Courses en cours du livreur (la plus ancienne d'abord).
     *
     * @return Collection<int, Order>
     */
    public function activeOrders(User $courier): Collection
    {
        return $courier->deliveries()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->oldest()
            ->oldest('id')
            ->get();
    }

    /**
     * Compteurs du jour (journée de Libreville) : courses livrées et gains (frais de livraison).
     *
     * @return array{deliveries: int, earnings: float}
     */
    public function todayStats(User $courier): array
    {
        $start = StoreHours::now()->startOfDay();
        $delivered = $this->deliveredBetween($courier, $start, $start->addDay());

        return [
            'deliveries' => $delivered->count(),
            'earnings' => (float) $delivered->sum('delivery_fee'),
        ];
    }

    /**
     * Gains (frais de livraison des courses livrées) du jour, de la semaine (lundi → dimanche)
     * et du mois, à l'heure de Libreville.
     *
     * @return array{today: float, week: float, month: float}
     */
    public function earnings(User $courier): array
    {
        $now = StoreHours::now();
        $sum = fn (CarbonInterface $from, CarbonInterface $to): float => (float) $this->deliveredBetween($courier, $from, $to)->sum('delivery_fee');

        return [
            'today' => $sum($now->startOfDay(), $now->startOfDay()->addDay()),
            'week' => $sum($now->startOfWeek(CarbonInterface::MONDAY), $now->startOfWeek(CarbonInterface::MONDAY)->addWeek()),
            'month' => $sum($now->startOfMonth(), $now->startOfMonth()->addMonth()),
        ];
    }

    /**
     * Courses livrées par le livreur entre deux instants (heure de livraison = updated_at :
     * une commande livrée ne change plus).
     */
    private function deliveredBetween(User $courier, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Order::query()
            ->where('delivery_id', $courier->id)
            ->where('status', OrderStatus::Delivered)
            ->where('updated_at', '>=', $from->utc())
            ->where('updated_at', '<', $to->utc());
    }

    private function profile(User $courier): DeliveryProfile
    {
        return $courier->deliveryProfile ?? abort(404, 'Aucun profil livreur n’est rattaché à ce compte.');
    }
}
