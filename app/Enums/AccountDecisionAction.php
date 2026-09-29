<?php

namespace App\Enums;

/**
 * Événements de l'historique d'un dossier d'inscription (table account_decisions).
 */
enum AccountDecisionAction: string
{
    case Submitted = 'submitted';
    case DocumentApproved = 'document_approved';
    case DocumentRejected = 'document_rejected';
    case AccountApproved = 'account_approved';
    case AccountRejected = 'account_rejected';
    case Resubmitted = 'resubmitted';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Dossier envoyé',
            self::DocumentApproved => 'Document approuvé',
            self::DocumentRejected => 'Document refusé',
            self::AccountApproved => 'Compte validé',
            self::AccountRejected => 'Inscription refusée',
            self::Resubmitted => 'Dossier corrigé et renvoyé',
        };
    }

    /**
     * Couleur de la pastille dans la frise (clés de UI/Badge).
     */
    public function color(): string
    {
        return match ($this) {
            self::Submitted, self::Resubmitted => 'sky',
            self::DocumentApproved, self::AccountApproved => 'green',
            self::DocumentRejected, self::AccountRejected => 'red',
        };
    }
}
