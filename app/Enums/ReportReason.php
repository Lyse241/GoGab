<?php

namespace App\Enums;

/**
 * Motif d'un signalement d'utilisateur (l'autre partie d'une commande).
 */
enum ReportReason: string
{
    case Retard = 'retard';
    case ComportementIrrespectueux = 'comportement_irrespectueux';
    case CommandeNonConforme = 'commande_non_conforme';
    case Fraude = 'fraude';
    case Absence = 'absence';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Retard => 'Retard',
            self::ComportementIrrespectueux => 'Comportement irrespectueux',
            self::CommandeNonConforme => 'Commande non conforme',
            self::Fraude => 'Fraude',
            self::Absence => 'Absence',
            self::Autre => 'Autre',
        };
    }

    /**
     * Signalement urgent : badge « Urgent », en tête de la liste admin.
     */
    public function isUrgent(): bool
    {
        return $this === self::Fraude;
    }

    /**
     * Motif de modération proposé par défaut quand l'admin sanctionne depuis le signalement.
     */
    public function moderationReason(): ModerationReason
    {
        return match ($this) {
            self::Retard => ModerationReason::RetardsRepetes,
            self::ComportementIrrespectueux => ModerationReason::ComportementAbusif,
            self::CommandeNonConforme, self::Absence => ModerationReason::Plainte,
            self::Fraude => ModerationReason::Fraude,
            self::Autre => ModerationReason::Autre,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $reason) => ['value' => $reason->value, 'label' => $reason->label()], self::cases());
    }
}
