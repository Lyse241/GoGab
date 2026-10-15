<?php

namespace App\Enums;

enum PromoType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Pourcentage du sous-total',
            self::Fixed => 'Montant fixe (FCFA)',
            self::FreeDelivery => 'Livraison offerte',
        };
    }
}
