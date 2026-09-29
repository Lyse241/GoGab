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
            self::Cash => 'Paiement à la livraison',
        };
    }

    /**
     * Consigne affichée au client lors du choix.
     */
    public function hint(): string
    {
        return match ($this) {
            self::AirtelMoney, self::MoovMoney => 'Le paiement se fait à la livraison via le numéro communiqué par le livreur.',
            self::Cash => 'En espèces, à la remise de la commande.',
        };
    }

    public function isMobileMoney(): bool
    {
        return $this !== self::Cash;
    }
}
