<?php

namespace App\Enums;

/**
 * Modes de paiement proposés. Aucun paiement réel n'est intégré : seul le choix est enregistré.
 */
enum PaymentMethod: string
{
    case AirtelMoney = 'airtel_money';
    case MoovMoney = 'moov_money';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::AirtelMoney => 'Airtel Money',
            self::MoovMoney => 'Moov Money',
            self::Cash => 'Espèces à la livraison',
        };
    }
}
