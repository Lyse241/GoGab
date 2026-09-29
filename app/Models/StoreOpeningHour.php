<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horaires d'un commerce pour un jour de la semaine (1 = lundi … 7 = dimanche).
 * Les heures sont toujours stockées au format HH:MM:SS (comparables en SQL comme en PHP).
 */
class StoreOpeningHour extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function opensAt(): Attribute
    {
        return Attribute::set(fn (?string $value) => self::normalizeTime($value));
    }

    protected function closesAt(): Attribute
    {
        return Attribute::set(fn (?string $value) => self::normalizeTime($value));
    }

    /**
     * "8:00", "08:00" ou "08:00:00" → "08:00:00" ; vide → null.
     */
    public static function normalizeTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        [$hours, $minutes] = array_pad(explode(':', trim($value)), 2, '0');

        return sprintf('%02d:%02d:00', (int) $hours, (int) $minutes);
    }

    /**
     * Jour travaillé avec des heures renseignées.
     */
    public function hasHours(): bool
    {
        return ! $this->is_closed && $this->opens_at !== null && $this->closes_at !== null;
    }

    /**
     * Le créneau finit le lendemain (ex. 18:00 → 02:00).
     */
    public function crossesMidnight(): bool
    {
        return $this->hasHours() && $this->closes_at < $this->opens_at;
    }

    public function isAllDay(): bool
    {
        return $this->hasHours() && $this->closes_at === $this->opens_at;
    }
}
