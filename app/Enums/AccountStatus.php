<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de validation',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
            self::Suspended => 'Suspendu',
        };
    }

    /**
     * Couleur du badge de statut (clé interprétée par le composant React UI/StatusBadge).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Suspended => 'gray',
        };
    }
}
