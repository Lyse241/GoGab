<?php

namespace App\Enums;

enum WalletEntryType: string
{
    case Sale = 'sale';
    case Commission = 'commission';
    case DeliveryEarning = 'delivery_earning';
    case CashCollected = 'cash_collected';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Vente',
            self::Commission => 'Commission Gogab',
            self::DeliveryEarning => 'Gain de livraison',
            self::CashCollected => 'Montant encaissé auprès du client',
            self::Settlement => 'Règlement',
        };
    }
}
