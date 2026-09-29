<?php

namespace App\Enums;

enum VehicleType: string
{
    case Moto = 'moto';
    case Bicycle = 'bicycle';
    case Car = 'car';

    public function label(): string
    {
        return match ($this) {
            self::Moto => 'Moto',
            self::Bicycle => 'Vélo',
            self::Car => 'Voiture',
        };
    }

    /**
     * Véhicule immatriculé qui demande un permis (pas le vélo).
     */
    public function requiresLicense(): bool
    {
        return $this !== self::Bicycle;
    }
}
