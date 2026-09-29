<?php

namespace App\Enums;

enum Role: string
{
    case Client = 'client';
    case Delivery = 'delivery';
    case Business = 'business';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Delivery => 'Livreur',
            self::Business => 'Entreprise',
            self::Admin => 'Administrateur',
        };
    }
}
