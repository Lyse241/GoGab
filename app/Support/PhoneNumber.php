<?php

namespace App\Support;

/**
 * Numéros de téléphone gabonais, enregistrés sous une forme unique
 * pour que l'unicité ne dépende pas de la façon de les saisir.
 *
 *     "077123456", "077 12 34 56", "+241 077 12 34 56", "00241-77-12-34-56" → "077 12 34 56"
 */
class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // Indicatif du Gabon (+241 / 00241) : on revient au format national à 9 chiffres (0XX…).
        if (preg_match('/^(?:00)?241(\d{8,9})$/', $digits, $matches)) {
            $digits = str_pad($matches[1], 9, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^0\d{8}$/', $digits)) {
            return preg_replace('/^(\d{3})(\d{2})(\d{2})(\d{2})$/', '$1 $2 $3 $4', $digits);
        }

        // Format inattendu : on garde la saisie, la validation se chargera de la refuser.
        return trim($phone);
    }
}
