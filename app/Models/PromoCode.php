<?php

namespace App\Models;

use App\Enums\PromoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Code promo : réduction financée par Gogab (le commerce et le livreur sont payés comme sans code).
 */
class PromoCode extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_subtotal', 'max_uses', 'max_uses_per_user',
        'first_order_only', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromoType::class,
            'value' => 'integer',
            'min_subtotal' => 'integer',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** « 10 % », « 2 000 FCFA », « Livraison offerte ». */
    public function label(): string
    {
        return match ($this->type) {
            PromoType::Percent => "{$this->value} % de réduction",
            PromoType::Fixed => number_format($this->value, 0, ',', ' ').' FCFA de réduction',
            PromoType::FreeDelivery => 'Livraison offerte',
        };
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }
}
