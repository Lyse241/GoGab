<?php

namespace App\Enums;

/**
 * Traitement d'un signalement : ouvert, en cours d'examen (ouvert par un admin), traité, classé
 * sans suite. « À traiter » = ouvert ou en cours d'examen.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::InReview => 'En cours d’examen',
            self::Resolved => 'Traité',
            self::Dismissed => 'Classé sans suite',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'yellow',
            self::InReview => 'sky',
            self::Resolved => 'green',
            self::Dismissed => 'gray',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, self::pending(), true);
    }

    /**
     * Statuts « à traiter » (compteur du menu, doublons).
     *
     * @return list<self>
     */
    public static function pending(): array
    {
        return [self::Open, self::InReview];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
