<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Utilisation d'un code promo sur une commande (une ligne par commande).
 */
class PromoRedemption extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['promo_code_id', 'user_id', 'order_id', 'discount'];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
