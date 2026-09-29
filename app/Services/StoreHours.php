<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreOpeningHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Horaires d'ouverture des commerces, toujours évalués à l'heure de Libreville
 * (config gogab.timezone) : le navigateur ne décide jamais si un commerce est ouvert.
 *
 * Règles :
 * - ouvert = commerce actif + interrupteur is_open + heure courante dans le créneau du jour ;
 * - un créneau dont la fermeture est avant l'ouverture passe minuit (18:00 → 02:00) :
 *   le mardi à 01:00, c'est le créneau du lundi qui s'applique ;
 * - ouverture = fermeture : ouvert 24 h/24 ce jour-là.
 */
class StoreHours
{
    public const DAYS = [
        1 => 'lundi',
        2 => 'mardi',
        3 => 'mercredi',
        4 => 'jeudi',
        5 => 'vendredi',
        6 => 'samedi',
        7 => 'dimanche',
    ];

    /**
     * Heure actuelle à Libreville (Carbon::setTestNow est pris en compte).
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function timezone(): string
    {
        return config('gogab.timezone', 'Africa/Libreville');
    }

    /**
     * Au moins un jour de la semaine a des heures d'ouverture.
     */
    public static function hasSchedule(Store $store): bool
    {
        return $store->openingHours->contains(fn (StoreOpeningHour $day) => $day->hasHours());
    }

    /**
     * Créneau en cours selon les horaires (sans tenir compte de l'interrupteur manuel).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null [ouverture, fermeture]
     */
    public static function currentSlot(Store $store, ?CarbonInterface $now = null): ?array
    {
        $now = self::localize($now);
        $days = self::days($store);

        // Créneau du jour.
        $today = $days->get($now->isoWeekday());
        if ($today?->hasHours()) {
            [$opensAt, $closesAt] = self::slotOn($today, $now->startOfDay());

            if ($now >= $opensAt && $now < $closesAt) {
                return [$opensAt, $closesAt];
            }
        }

        // Créneau de la veille qui déborde après minuit.
        $yesterday = $days->get($now->subDay()->isoWeekday());
        if ($yesterday?->crossesMidnight()) {
            [$opensAt, $closesAt] = self::slotOn($yesterday, $now->subDay()->startOfDay());

            if ($now < $closesAt) {
                return [$opensAt, $closesAt];
            }
        }

        return null;
    }

    public static function isOpenNow(Store $store, ?CarbonInterface $now = null): bool
    {
        return $store->isVisible()
            && $store->is_open
            && self::currentSlot($store, $now) !== null;
    }

    /**
     * Prochaine ouverture selon les horaires (aujourd'hui plus tard, demain ou un jour suivant,
     * jours de repos sautés). Null si aucun horaire n'est défini.
     */
    public static function nextOpeningAt(Store $store, ?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = self::localize($now);
        $days = self::days($store);

        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $now->startOfDay()->addDays($offset);
            $day = $days->get($date->isoWeekday());

            if ($day?->hasHours()) {
                [$opensAt] = self::slotOn($day, $date);

                if ($opensAt > $now) {
                    return $opensAt;
                }
            }
        }

        return null;
    }

    /**
     * État affichable : open (commande possible), label complet, detail (pour le badge).
     *
     * @return array{open: bool, label: string, detail: string|null}
     */
    public static function statusMessage(Store $store, ?CarbonInterface $now = null): array
    {
        $now = self::localize($now);

        if (! $store->isVisible()) {
            return self::status(false, 'Fermé · commerce indisponible', 'commerce indisponible');
        }

        if (! $store->is_open) {
            return self::status(false, 'Temporairement fermé', 'fermeture temporaire');
        }

        if (! self::hasSchedule($store)) {
            return self::status(false, 'Horaires non renseignés', 'horaires non renseignés');
        }

        if ($slot = self::currentSlot($store, $now)) {
            [$opensAt, $closesAt] = $slot;
            if ($closesAt->diffInHours($opensAt, true) >= 24) {
                return self::status(true, 'Ouvert 24 h/24', 'ouvert 24 h/24');
            }

            $detail = 'ferme à '.self::time($closesAt);

            return self::status(true, 'Ouvert · '.$detail, $detail);
        }

        $next = self::nextOpeningAt($store, $now);

        if (! $next) {
            return self::status(false, 'Fermé', null);
        }

        $detail = 'ouvre '.self::when($next, $now).'à '.self::time($next);

        return self::status(false, 'Fermé · '.$detail, $detail);
    }

    /**
     * Horaires des 7 jours pour un formulaire (HH:MM), lundi → dimanche.
     *
     * @return list<array{day_of_week: int, is_closed: bool, opens_at: string, closes_at: string}>
     */
    public static function schedule(Store $store): array
    {
        $days = self::days($store);

        return array_map(function (int $dayOfWeek) use ($days) {
            $day = $days->get($dayOfWeek);

            return [
                'day_of_week' => $dayOfWeek,
                'is_closed' => $day?->is_closed ?? false,
                'opens_at' => $day?->opens_at ? substr($day->opens_at, 0, 5) : '',
                'closes_at' => $day?->closes_at ? substr($day->closes_at, 0, 5) : '',
            ];
        }, array_keys(self::DAYS));
    }

    /**
     * Enregistre les 7 jours (une ligne par jour, mise à jour si elle existe).
     *
     * @param  iterable<array{day_of_week: int, is_closed?: bool, opens_at?: string|null, closes_at?: string|null}>  $days
     */
    public static function sync(Store $store, iterable $days): void
    {
        foreach ($days as $day) {
            $closed = (bool) ($day['is_closed'] ?? false);

            $store->openingHours()->updateOrCreate(
                ['day_of_week' => (int) $day['day_of_week']],
                [
                    'is_closed' => $closed,
                    // Un jour de repos garde ses heures éventuelles pour pouvoir le rouvrir facilement.
                    'opens_at' => $day['opens_at'] ?? null,
                    'closes_at' => $day['closes_at'] ?? null,
                ],
            );
        }

        $store->unsetRelation('openingHours');
    }

    /**
     * Mêmes horaires tous les jours, avec d'éventuels jours de repos (1 = lundi … 7 = dimanche).
     *
     * @param  list<int>  $closedDays
     * @return list<array{day_of_week: int, is_closed: bool, opens_at: string, closes_at: string}>
     */
    public static function everyDay(string $opensAt, string $closesAt, array $closedDays = []): array
    {
        return array_map(fn (int $day) => [
            'day_of_week' => $day,
            'is_closed' => in_array($day, $closedDays, true),
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ], array_keys(self::DAYS));
    }

    /**
     * @return Collection<int, StoreOpeningHour>
     */
    private static function days(Store $store): Collection
    {
        return $store->openingHours->keyBy('day_of_week');
    }

    /**
     * Ouverture et fermeture réelles d'un créneau commençant le jour $date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function slotOn(StoreOpeningHour $day, CarbonImmutable $date): array
    {
        $opensAt = $date->setTimeFromTimeString($day->opens_at);
        $closesAt = $date->setTimeFromTimeString($day->closes_at);

        if ($closesAt <= $opensAt) {
            $closesAt = $closesAt->addDay(); // passe minuit, ou 24 h/24 si égales
        }

        return [$opensAt, $closesAt];
    }

    private static function localize(?CarbonInterface $now): CarbonImmutable
    {
        return $now ? CarbonImmutable::instance($now)->setTimezone(self::timezone()) : self::now();
    }

    /**
     * "" (aujourd'hui), "demain ", "lundi ", "lundi prochain ".
     */
    private static function when(CarbonImmutable $next, CarbonImmutable $now): string
    {
        $days = (int) $now->startOfDay()->diffInDays($next->startOfDay(), true);

        return match (true) {
            $days === 0 => '',
            $days === 1 => 'demain ',
            $days < 7 => self::DAYS[$next->isoWeekday()].' ',
            default => self::DAYS[$next->isoWeekday()].' prochain ',
        };
    }

    private static function time(CarbonInterface $time): string
    {
        return $time->format('H\hi');
    }

    /**
     * @return array{open: bool, label: string, detail: string|null}
     */
    private static function status(bool $open, string $label, ?string $detail): array
    {
        return ['open' => $open, 'label' => $label, 'detail' => $detail];
    }
}
