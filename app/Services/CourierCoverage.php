<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\DeliveryProfile;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Qui voit quelle course ? Chaque livreur choisit un quartier de départ et un rayon de couverture
 * (km à vol d'oiseau, `delivery_profiles.coverage_km`). Il voit les courses des commerces situés dans
 * ce rayon. Une annonce restée sans livreur depuis `gogab.coverage.widen_after_minutes` est élargie à
 * tous les livreurs disponibles à moins de `gogab.coverage.widened_km` (le Grand Libreville).
 *
 * Quand une position manque (quartier sans coordonnées), on retombe sur la règle de la commune : même
 * zone que le commerce ; l'élargissement ne s'applique pas faute de distance.
 */
class CourierCoverage
{
    /** Position du commerce : l'épingle exacte de l'entreprise, sinon le centre de son quartier. */
    public function storePoint(Store $store): ?array
    {
        if ($store->latitude !== null && $store->longitude !== null) {
            return [(float) $store->latitude, (float) $store->longitude];
        }
        $store->loadMissing('neighborhood');
        $n = $store->neighborhood;

        return $n && $n->latitude !== null && $n->longitude !== null ? [(float) $n->latitude, (float) $n->longitude] : null;
    }

    /** Point de départ du livreur : le centre de son quartier de base. */
    public function basePoint(?DeliveryProfile $profile): ?array
    {
        $n = $profile?->baseNeighborhood;

        return $n && $n->latitude !== null && $n->longitude !== null ? [(float) $n->latitude, (float) $n->longitude] : null;
    }

    /** Distance (km, à vol d'oiseau) entre le départ du livreur et le commerce ; null si une position manque. */
    public function distanceKm(User $courier, Store $store): ?float
    {
        $courier->loadMissing('deliveryProfile.baseNeighborhood');
        $from = $this->basePoint($courier->deliveryProfile);
        $to = $this->storePoint($store);

        return $from && $to ? $this->haversine($from, $to) : null;
    }

    /**
     * Le livreur couvre-t-il ce commerce ? ($widened : l'annonce est élargie au Grand Libreville.)
     * Il doit être disponible (les courses déjà acceptées se poursuivent, quel que soit le rayon).
     */
    public function covers(User $courier, Store $store, bool $widened = false): bool
    {
        $courier->loadMissing('deliveryProfile.baseNeighborhood');
        $profile = $courier->deliveryProfile;
        if (! $profile || ! $profile->is_available) {
            return false;
        }

        $km = $this->distanceKm($courier, $store);
        if ($km === null) {
            $store->loadMissing('neighborhood');
            $zone = $store->neighborhood?->zone;

            return $zone !== null && $profile->baseNeighborhood?->zone === $zone;
        }

        $radius = $widened ? max((int) $profile->coverage_km, (int) config('gogab.coverage.widened_km')) : (int) $profile->coverage_km;

        return $km <= $radius;
    }

    public function coversOrder(User $courier, Order $order): bool
    {
        $order->loadMissing('store.neighborhood');

        return $order->store && $this->covers($courier, $order->store, $order->isWidened());
    }

    /**
     * Livreurs qui reçoivent l'annonce : validés, disponibles, non bloqués, qui couvrent le commerce.
     *
     * @return Collection<int, User>
     */
    public function couriersForStore(Store $store, bool $widened = false): Collection
    {
        return $this->availableCouriers()->filter(fn (User $courier) => $this->covers($courier, $store, $widened))->values();
    }

    /** @return Collection<int, User> */
    public function couriersForOrder(Order $order): Collection
    {
        $order->loadMissing('store.neighborhood');

        return $order->store ? $this->couriersForStore($order->store, $order->isWidened()) : collect();
    }

    /** Y a-t-il (au moins) un livreur disponible qui couvre ce commerce dans son rayon normal ? */
    public function hasCourierFor(Store $store): bool
    {
        return $this->couriersForStore($store)->isNotEmpty();
    }

    /**
     * Offres visibles par un livreur : annonces sans livreur des commerces qu'il couvre (rayon, ou
     * Grand Libreville si l'annonce est élargie), de la plus ancienne à la plus récente.
     *
     * @param  list<string>  $with  relations supplémentaires à charger
     * @return Collection<int, Order>
     */
    public function offersFor(User $courier, array $with = []): Collection
    {
        $courier->loadMissing('deliveryProfile.baseNeighborhood');
        $profile = $courier->deliveryProfile;
        if (! $profile || ! $profile->is_available) {
            return collect();
        }

        return Order::with(['store.neighborhood', ...$with])
            ->where('status', OrderStatus::SearchingCourier)
            ->whereNull('delivery_id')
            ->orderByRaw('coalesce(announced_at, updated_at) asc')
            ->oldest('id')
            ->get()
            ->filter(fn (Order $order) => $this->coversOrder($courier, $order))
            ->values();
    }

    /**
     * Élargit les annonces restées sans livreur trop longtemps : les livreurs qui ne les voyaient pas
     * sont prévenus, l'entreprise est informée, et l'administrateur est alerté s'il n'y a personne.
     * Appelé chaque minute (`gogab:widen-announcements`) ; une seule fois par annonce.
     *
     * @return int nombre d'annonces élargies
     */
    public function widenStaleAnnouncements(): int
    {
        $stale = Order::with(['store.neighborhood', 'store.owner', 'neighborhood'])
            ->where('status', OrderStatus::SearchingCourier)
            ->whereNull('delivery_id')
            ->whereNull('widened_at')
            ->whereRaw('coalesce(announced_at, updated_at) <= ?', [now()->subMinutes((int) config('gogab.coverage.widen_after_minutes'))])
            ->get();

        $count = 0;
        foreach ($stale as $order) {
            $claimed = Order::whereKey($order->id)->where('status', OrderStatus::SearchingCourier)->whereNull('delivery_id')->whereNull('widened_at')
                ->update(['widened_at' => now()]);
            if ($claimed !== 1 || ! $order->store) {
                continue;
            }
            $count++;

            $normal = $this->couriersForStore($order->store)->pluck('id');
            $wide = $this->couriersForStore($order->store, true);
            $newcomers = $wide->reject(fn (User $courier) => $normal->contains($courier->id));

            Notifier::send(
                $newcomers,
                'Course disponible un peu plus loin',
                "Une course attend un livreur chez {$order->store->name} : elle est ouverte à toute l'agglomération.",
                route('delivery.offers'),
                'info',
            );

            if ($order->store->owner) {
                Notifier::send(
                    $order->store->owner,
                    'Recherche de livreur élargie',
                    $wide->isEmpty()
                        ? "Aucun livreur n'est disponible pour la commande {$order->reference}. Vous pouvez relancer l'annonce ou l'annuler."
                        : "La commande {$order->reference} est maintenant proposée à tous les livreurs disponibles de l'agglomération.",
                    route('business.orders.show', $order),
                    $wide->isEmpty() ? 'warning' : 'info',
                );
            }

            if ($wide->isEmpty()) {
                Notifier::admins(
                    'Aucun livreur disponible',
                    "La commande {$order->reference} ({$order->store->name}) n'a aucun livreur disponible, même en élargissant la recherche.",
                    route('admin.orders.show', $order),
                    'warning',
                );
            }
        }

        return $count;
    }

    /** @return Collection<int, User> */
    private function availableCouriers(): Collection
    {
        return User::query()
            ->where('role', Role::Delivery)
            ->where('account_status', 'approved')
            ->whereNull('deleted_at')
            ->where(fn (Builder $query) => $query->whereNull('blocked_until')->orWhere('blocked_until', '<=', now()))
            ->whereHas('deliveryProfile', fn (Builder $query) => $query->where('is_available', true))
            ->with('deliveryProfile.baseNeighborhood')
            ->get();
    }

    /** Distance à vol d'oiseau en kilomètres (haversine). */
    private function haversine(array $a, array $b): float
    {
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
