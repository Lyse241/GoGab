<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Services\StoreHours;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** @use HasFactory<\Database\Factories\StoreFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'category_id',
        'description',
        'phone',
        'neighborhood_id',
        'address_landmarks',
        'logo',
        'cover_image',
        'is_open',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Compte entreprise propriétaire (null pour les commerces seedés).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Horaires de la semaine (une ligne par jour, lundi → dimanche).
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(StoreOpeningHour::class)->orderBy('day_of_week');
    }

    // --- Horaires d'ouverture (règles dans App\Services\StoreHours, fuseau de Libreville) ---

    public function isOpenNow(?CarbonInterface $now = null): bool
    {
        return StoreHours::isOpenNow($this, $now);
    }

    public function nextOpeningAt(?CarbonInterface $now = null): ?CarbonImmutable
    {
        return StoreHours::nextOpeningAt($this, $now);
    }

    /**
     * @return array{open: bool, label: string, detail: string|null}
     */
    public function statusMessage(?CarbonInterface $now = null): array
    {
        return StoreHours::statusMessage($this, $now);
    }

    /**
     * État d'ouverture envoyé aux pages Inertia : le navigateur ne décide jamais.
     *
     * @return array{is_open_now: bool, status_label: string, status_detail: string|null}
     */
    public function openingStatus(?CarbonInterface $now = null): array
    {
        $status = $this->statusMessage($now);

        return [
            'is_open_now' => $status['open'],
            'status_label' => $status['label'],
            'status_detail' => $status['detail'],
        ];
    }

    /**
     * Commerces visibles côté public : activés par l'admin (is_active) et dont le propriétaire
     * éventuel a un compte validé. Un commerce en attente de validation, ou dont l'entreprise est
     * bloquée, n'apparaît nulle part (le débloquer suffit à le réafficher).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('owner_id')
                ->orWhereHas('owner', fn (Builder $owner) => $owner->where('account_status', AccountStatus::Approved)));
    }

    /**
     * Même règle que scopeVisible(), pour un commerce déjà chargé.
     */
    public function isVisible(): bool
    {
        return $this->is_active
            && ($this->owner_id === null || $this->owner?->account_status === AccountStatus::Approved);
    }

    /**
     * Commerces ouverts maintenant (même règle que isOpenNow(), en SQL).
     * Les heures sont stockées en HH:MM:SS : la comparaison de chaînes suffit.
     */
    public function scopeOpenNow(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now = $now ? CarbonImmutable::instance($now)->setTimezone(StoreHours::timezone()) : StoreHours::now();
        $time = $now->format('H:i:s');
        $today = $now->isoWeekday();
        $yesterday = $now->subDay()->isoWeekday();

        return $query
            ->where('is_active', true)
            ->where('is_open', true)
            ->whereHas('openingHours', fn (Builder $hours) => $hours
                ->where('is_closed', false)
                ->whereNotNull('opens_at')
                ->whereNotNull('closes_at')
                ->where(fn (Builder $hours) => $hours
                    // Créneau du jour : normal, passant minuit (déjà commencé) ou 24 h/24.
                    ->where(fn (Builder $hours) => $hours
                        ->where('day_of_week', $today)
                        ->where(fn (Builder $hours) => $hours
                            ->where(fn (Builder $hours) => $hours
                                ->whereColumn('opens_at', '<', 'closes_at')
                                ->where('opens_at', '<=', $time)
                                ->where('closes_at', '>', $time))
                            ->orWhere(fn (Builder $hours) => $hours
                                ->whereColumn('closes_at', '<', 'opens_at')
                                ->where('opens_at', '<=', $time))
                            ->orWhereColumn('opens_at', '=', 'closes_at')))
                    // Créneau de la veille qui déborde après minuit.
                    ->orWhere(fn (Builder $hours) => $hours
                        ->where('day_of_week', $yesterday)
                        ->whereColumn('closes_at', '<', 'opens_at')
                        ->where('closes_at', '>', $time))));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
