<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de vérification',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
        };
    }

    /**
     * Couleur du badge (clés de UI/Badge).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
