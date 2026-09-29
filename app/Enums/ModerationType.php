<?php

namespace App\Enums;

enum ModerationType: string
{
    case Warning = 'warning';
    case Block = 'block';
    case Unblock = 'unblock';
    case Flag = 'flag';
    case Unflag = 'unflag';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Avertissement',
            self::Block => 'Blocage',
            self::Unblock => 'Déblocage',
            self::Flag => 'Signalement interne',
            self::Unflag => 'Signalement retiré',
        };
    }

    /**
     * Couleur du badge (clés de UI/Badge).
     */
    public function color(): string
    {
        return match ($this) {
            self::Warning => 'warning',
            self::Block => 'danger',
            self::Unblock => 'success',
            self::Flag => 'purple',
            self::Unflag => 'neutral',
        };
    }
}
