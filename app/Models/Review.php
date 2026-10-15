<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis d'un client sur une commande livrée (notes de 1 à 5 pour le commerce et le livreur).
 */
class Review extends Model
{
    protected $fillable = ['order_id', 'reviewer_id', 'store_id', 'courier_id', 'store_rating', 'courier_rating', 'comment'];

    protected function casts(): array
    {
        return [
            'store_rating' => 'integer',
            'courier_rating' => 'integer',
            'hidden_at' => 'datetime',
        ];
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
