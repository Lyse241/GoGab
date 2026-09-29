<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'en_attente';
    case Accepted = 'acceptee';
    case Refused = 'refusee';
    case Preparing = 'en_preparation';
    case SearchingCourier = 'en_recherche_livreur';
    case CourierAssigned = 'livreur_assigne';
    case Delivering = 'en_livraison';
    case Arrived = 'arrive';
    case Delivered = 'livree';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
            self::Preparing => 'En préparation',
            self::SearchingCourier => 'Recherche de livreur',
            self::CourierAssigned => 'Livreur assigné',
            self::Delivering => 'En cours de livraison',
            self::Arrived => 'Livreur arrivé',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * Couleur du badge de statut (clé interprétée par le composant React StatusBadge).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Accepted => 'sky',
            self::Refused => 'red',
            self::Preparing => 'orange',
            self::SearchingCourier => 'purple',
            self::CourierAssigned => 'indigo',
            self::Delivering => 'blue',
            self::Arrived => 'teal',
            self::Delivered => 'green',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Statuts terminaux : la commande n'évolue plus.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Refused, self::Cancelled], true);
    }
}
