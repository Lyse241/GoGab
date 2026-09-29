<?php

namespace App\Http\Requests\Concerns;

use App\Services\StoreHours;
use Illuminate\Validation\Validator;

/**
 * Validation des horaires envoyés par le composant React OpeningHoursEditor :
 * opening_hours = 7 lignes { day_of_week, is_closed, opens_at "HH:MM", closes_at "HH:MM" }.
 *
 * À utiliser dans toute Form Request qui enregistre les horaires d'un commerce
 * (admin, et plus tard inscription entreprise / « Mon commerce »).
 */
trait ValidatesOpeningHours
{
    /**
     * @return array<string, mixed>
     */
    public static function openingHoursRules(bool $required = false): array
    {
        return [
            'opening_hours' => [$required ? 'required' : 'sometimes', 'array', 'size:7'],
            'opening_hours.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'opening_hours.*.is_closed' => ['required', 'boolean'],
            'opening_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function openingHoursMessages(): array
    {
        return [
            'opening_hours.required' => 'Indiquez les horaires d’ouverture.',
            'opening_hours.size' => 'Les horaires doivent couvrir les 7 jours de la semaine.',
            'opening_hours.*.opens_at.date_format' => 'Heure invalide (format HH:MM).',
            'opening_hours.*.closes_at.date_format' => 'Heure invalide (format HH:MM).',
        ];
    }

    /**
     * Un jour ouvert doit avoir ses deux heures (à appeler depuis after()).
     */
    protected function validateOpeningHoursConsistency(Validator $validator): void
    {
        self::checkOpeningHoursConsistency($validator, $this->input('opening_hours', []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    public static function checkOpeningHoursConsistency(Validator $validator, mixed $days): void
    {
        if ($validator->errors()->isNotEmpty() || ! is_array($days)) {
            return;
        }

        foreach ($days as $index => $day) {
            if (filter_var($day['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $name = ucfirst(StoreHours::DAYS[(int) ($day['day_of_week'] ?? 0)] ?? 'Ce jour');

            if (blank($day['opens_at'] ?? null)) {
                $validator->errors()->add("opening_hours.{$index}.opens_at", "{$name} : indiquez l’heure d’ouverture, ou cochez « Fermé ce jour ».");
            }

            if (blank($day['closes_at'] ?? null)) {
                $validator->errors()->add("opening_hours.{$index}.closes_at", "{$name} : indiquez l’heure de fermeture, ou cochez « Fermé ce jour ».");
            }
        }
    }
}
