<?php

namespace App\Models;

use App\Enums\WalletEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement du grand livre (ajout seul). amount > 0 : Gogab doit l'utilisateur ; < 0 : il doit Gogab.
 */
class WalletEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'order_id', 'created_by', 'type', 'amount', 'description'];

    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
