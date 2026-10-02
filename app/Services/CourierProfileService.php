<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryProfile;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\User;
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
        $start = StoreHours::now()->startOfDay()->utc();
        $delivered = fn (): Builder => Order::query()
            ->where('delivery_id', $courier->id)
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('updated_at', [$start, $start->addDay()]);

        return [
            'deliveries' => $delivered()->count(),
            'earnings' => (float) $delivered()->sum('delivery_fee'),
        ];
    }

    private function profile(User $courier): DeliveryProfile
    {
        return $courier->deliveryProfile ?? abort(404, 'Aucun profil livreur n’est rattaché à ce compte.');
    }
}
