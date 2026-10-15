<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\OrderTransitionException;
use App\Models\DeliveryProfile;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Suivi de la livraison en direct : le téléphone du livreur envoie sa position pendant la course
 * (report), le client la voit sur une carte avec une arrivée estimée (forOrder).
 *
 * Confidentialité : la position n'existe que pendant une course active (livreur assigné, en
 * livraison, arrivé), est effacée à la remise ou à l'annulation (clear), et n'est montrée qu'aux
 * personnes autorisées à voir la commande (OrderPolicy::view).
 *
 * Ce que chacun voit dépend de son rôle :
 *  - client, livreur, admin : toute la course (commerce, adresse, livreur) ;
 *  - entreprise : seulement l'approche du livreur vers sa boutique, jusqu'au retrait (statut
 *    « livreur assigné »). Ensuite plus rien : ni la position du livreur, ni l'adresse du client.
 *
 * Le commerce est placé à l'épingle posée par son entreprise (« Mon commerce »), sinon au centre de
 * son quartier ; l'adresse du client est toujours au centre de son quartier (pas d'adresse
 * géocodée) : ces positions sont donc plus ou moins approximatives.
 */
class TrackingService
{
    /** Statuts pendant lesquels la position du livreur est partagée. */
    public const TRACKED_STATUSES = [OrderStatus::CourierAssigned, OrderStatus::Delivering, OrderStatus::Arrived];

    /** Au-delà, la position est considérée comme périmée (téléphone éteint, hors réseau…). */
    public const FRESH_SECONDS = 90;

    /** Vitesse moyenne en ville (km/h) pour l'arrivée estimée. */
    public const AVERAGE_SPEED_KMH = 20;

    /** Distance (km) à partir de laquelle le commerce est prévenu que le livreur arrive : marge large si le commerce est placé au centre de son quartier… */
    public const STORE_NEAR_KM = 0.6;

    /** …et serrée quand l'entreprise a posé l'épingle exacte de sa boutique. */
    public const STORE_NEAR_EXACT_KM = 0.15;

    /** Limites du Gabon : une position hors de cette zone est refusée (donnée aberrante). */
    private const BOUNDS = ['lat' => [-4.0, 2.5], 'lng' => [8.5, 14.5]];

    /** Position plausible au Gabon (les coordonnées aberrantes sont refusées). */
    public static function inGabon(float $latitude, float $longitude): bool
    {
        return $latitude >= self::BOUNDS['lat'][0] && $latitude <= self::BOUNDS['lat'][1]
            && $longitude >= self::BOUNDS['lng'][0] && $longitude <= self::BOUNDS['lng'][1];
    }

    /**
     * Enregistre la position du livreur pour sa course en cours.
     *
     * @throws OrderTransitionException course non active ou position invalide
     */
    public function report(User $courier, Order $order, float $latitude, float $longitude, ?int $accuracy = null, ?int $heading = null): DeliveryProfile
    {
        if ($order->delivery_id !== $courier->id || ! in_array($order->status, self::TRACKED_STATUSES, true)) {
            throw new OrderTransitionException('Cette course n’est pas en cours : la position n’est pas partagée.');
        }

        if (! self::inGabon($latitude, $longitude)) {
            throw new OrderTransitionException('Position invalide.');
        }

        $profile = $courier->deliveryProfile ?? throw new OrderTransitionException('Aucun profil livreur n’est rattaché à ce compte.');
        $profile->forceFill([
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'accuracy' => $accuracy !== null ? max(0, $accuracy) : null,
            'heading' => $heading !== null ? (($heading % 360) + 360) % 360 : null,
            'located_at' => now(),
        ])->save();

        $this->warnStoreIfCourierIsNear($order, $profile);

        return $profile;
    }

    /**
     * « Je suis arrivé au commerce » : le livreur prévient l'entreprise (et le client) qu'il est sur
     * place. Une seule fois par course ; refusé hors de la phase de retrait.
     *
     * @throws OrderTransitionException ce n'est pas la course du livreur, ou elle n'est plus en phase de retrait
     */
    public function markArrivedAtStore(User $courier, Order $order): Order
    {
        if ($order->delivery_id !== $courier->id || $order->status !== OrderStatus::CourierAssigned) {
            throw new OrderTransitionException('Vous pouvez signaler votre arrivée au commerce tant que vous n’avez pas récupéré la commande.');
        }

        // Déjà signalé : rien à refaire (pas de seconde notification).
        $claimed = Order::whereKey($order->id)->whereNull('courier_arrived_at')->update(['courier_arrived_at' => now()]);
        if ($claimed === 0) {
            return $order->refresh();
        }

        $order->refresh()->loadMissing(['store.owner', 'client']);
        Notifier::send(
            $order->store->owner,
            'Votre livreur est arrivé',
            "Le livreur de la commande {$order->reference} est devant votre commerce : remettez-lui la commande.",
            route('business.orders.show', $order),
            'success',
        );
        Notifier::send(
            $order->client,
            'Votre livreur est au commerce',
            "Le livreur récupère votre commande {$order->reference} chez {$order->store->name}.",
            route('orders.show', $order),
        );

        return $order;
    }

    /** Efface la position (fin de course). */
    public function clear(int $courierId): void
    {
        DeliveryProfile::where('user_id', $courierId)->update([
            'latitude' => null, 'longitude' => null, 'accuracy' => null, 'heading' => null, 'located_at' => null,
        ]);
    }

    /**
     * Données de la carte pour une commande.
     *
     * @return array{
     *     active: bool, stage: string|null, message: string|null,
     *     courier: array{lat: float, lng: float, heading: int|null, accuracy: int|null, seconds_ago: int, fresh: bool}|null,
     *     store: array{name: string, lat: float, lng: float, exact: bool}|null,
     *     destination: array{label: string, lat: float, lng: float}|null,
     *     distance_km: float|null, eta_minutes: int|null
     * }
     */
    public function forOrder(Order $order, ?User $viewer = null): array
    {
        $pickupOnly = (bool) $viewer?->isBusiness();

        // Relu avec toutes les colonnes nécessaires (la page de commande n'en charge que quelques-unes).
        $order = Order::with(['store.neighborhood', 'neighborhood', 'delivery.deliveryProfile'])->findOrFail($order->id);

        // L'entreprise ne suit le livreur que jusqu'au retrait ; ensuite la course ne la regarde plus.
        $tracked = $pickupOnly ? [OrderStatus::CourierAssigned] : self::TRACKED_STATUSES;
        $active = $order->delivery_id !== null && in_array($order->status, $tracked, true);
        $store = $this->storePoint($order->store);
        $destination = $pickupOnly ? null : $this->point($order->neighborhood, 'Quartier '.($order->neighborhood?->name ?? ''));

        $payload = [
            'active' => $active,
            'stage' => null,
            'message' => null,
            'courier' => null,
            'store' => $store ? ['name' => $store['label'], 'lat' => $store['lat'], 'lng' => $store['lng'], 'exact' => $store['exact']] : null,
            'destination' => $destination,
            'distance_km' => null,
            'eta_minutes' => null,
        ];

        if (! $active) {
            return $payload;
        }

        $payload['stage'] = match ($order->status) {
            OrderStatus::CourierAssigned => $order->courier_arrived_at ? 'at_store' : 'to_store',
            OrderStatus::Delivering => 'to_client',
            default => 'arrived',
        };

        $profile = $order->delivery?->deliveryProfile;
        $located = $profile?->latitude !== null && $profile->longitude !== null && $profile->located_at !== null;
        $secondsAgo = $located ? max(0, (int) $profile->located_at->diffInSeconds(now())) : null;
        $fresh = $located && $secondsAgo <= self::FRESH_SECONDS;

        if ($located) {
            $payload['courier'] = [
                'lat' => (float) $profile->latitude,
                'lng' => (float) $profile->longitude,
                'heading' => $profile->heading,
                'accuracy' => $profile->accuracy,
                'seconds_ago' => $secondsAgo,
                'fresh' => $fresh,
            ];
        }

        // Cible du livreur : le commerce avant la récupération, ensuite l'adresse du client.
        $target = in_array($payload['stage'], ['to_store', 'at_store'], true) ? $store : $destination;
        if ($fresh && $target && ! in_array($payload['stage'], ['arrived', 'at_store'], true)) {
            $km = $this->distanceKm((float) $profile->latitude, (float) $profile->longitude, $target['lat'], $target['lng']);
            $payload['distance_km'] = round($km, 1);
            $payload['eta_minutes'] = max(1, (int) ceil($km / self::AVERAGE_SPEED_KMH * 60));
        }

        $payload['message'] = $pickupOnly
            ? $this->pickupMessage($payload['stage'], $fresh, $payload['eta_minutes'], $payload['distance_km'], (bool) ($store['exact'] ?? false))
            : $this->message($payload['stage'], $fresh, $payload['eta_minutes']);

        return $payload;
    }

    /** Distance à vol d'oiseau en kilomètres (formule de haversine). */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Message affiché au commerce pendant l'approche du livreur. */
    private function pickupMessage(string $stage, bool $fresh, ?int $eta, ?float $km, bool $exact): string
    {
        if ($stage === 'at_store') {
            return 'Votre livreur est arrivé : remettez-lui la commande.';
        }
        if (! $fresh) {
            return 'Position du livreur indisponible pour le moment.';
        }
        if ($km !== null && $km <= ($exact ? self::STORE_NEAR_EXACT_KM : self::STORE_NEAR_KM)) {
            return 'Votre livreur est tout proche : préparez la remise de la commande.';
        }

        return $eta <= 1 ? 'Votre livreur arrive dans moins d’une minute.' : "Votre livreur arrive dans environ {$eta} min.";
    }

    /**
     * Prévient l'entreprise, une seule fois par course, quand le livreur est proche de sa boutique.
     */
    private function warnStoreIfCourierIsNear(Order $order, DeliveryProfile $profile): void
    {
        if ($order->status !== OrderStatus::CourierAssigned) {
            return;
        }

        $order->loadMissing(['store.neighborhood', 'store.owner']);
        $store = $this->storePoint($order->store);
        if (! $store || ! $order->store->owner) {
            return;
        }

        $km = $this->distanceKm((float) $profile->latitude, (float) $profile->longitude, $store['lat'], $store['lng']);
        $near = $store['exact'] ? self::STORE_NEAR_EXACT_KM : self::STORE_NEAR_KM;
        if ($km <= $near && Cache::add("tracking.store-near.{$order->id}", true, now()->addHours(6))) {
            Notifier::send(
                $order->store->owner,
                'Votre livreur arrive',
                "Le livreur de la commande {$order->reference} est tout proche de votre commerce : préparez la remise.",
                route('business.orders.show', $order),
            );
        }
    }

    private function message(string $stage, bool $fresh, ?int $eta): string
    {
        if ($stage === 'arrived') {
            return 'Votre livreur est arrivé.';
        }
        if ($stage === 'at_store') {
            return 'Votre livreur est arrivé au commerce et récupère votre commande.';
        }
        if (! $fresh) {
            return 'Position du livreur indisponible pour le moment. Elle réapparaîtra dès que le livreur partagera à nouveau sa position.';
        }

        $time = $eta <= 1 ? 'moins d’une minute' : "environ {$eta} min";

        return $stage === 'to_store'
            ? "Votre livreur se rend au commerce, à {$time} du retrait."
            : "Votre livreur arrive chez vous dans {$time}.";
    }

    /**
     * Position du commerce : l'épingle exacte posée par l'entreprise, sinon le centre de son quartier.
     *
     * @return array{label: string, lat: float, lng: float, exact: bool}|null
     */
    private function storePoint(?Store $store): ?array
    {
        if (! $store) {
            return null;
        }
        if ($store->latitude !== null && $store->longitude !== null) {
            return ['label' => $store->name, 'lat' => (float) $store->latitude, 'lng' => (float) $store->longitude, 'exact' => true];
        }

        $point = $this->point($store->neighborhood, $store->name);

        return $point ? [...$point, 'exact' => false] : null;
    }

    /** @return array{label: string, lat: float, lng: float}|null */
    private function point(?Neighborhood $neighborhood, string $label): ?array
    {
        if (! $neighborhood || $neighborhood->latitude === null || $neighborhood->longitude === null) {
            return null;
        }

        return ['label' => $label, 'lat' => (float) $neighborhood->latitude, 'lng' => (float) $neighborhood->longitude];
    }
}
