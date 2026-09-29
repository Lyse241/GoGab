<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Progression du livreur (flux v1 simplifié, sans étape commerce) : statut actuel => statut suivant.
     */
    public const NEXT_STATUS = [
        OrderStatus::Accepted->value => OrderStatus::Delivering->value,
        OrderStatus::Delivering->value => OrderStatus::Delivered->value,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'store_id',
        'client_id',
        'delivery_id',
        'neighborhood_id',
        'address_landmarks',
        'subtotal',
        'delivery_fee',
        'total_price',
        'payment_method',
        'cash_given',
        'client_note',
        'cancel_reason',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total_price' => 'decimal:2',
            'cash_given' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
        ];
    }

    /**
     * Référence lisible attribuée dès l'insertion, ex. "GG-000123".
     */
    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if ($order->reference === null) {
                $order->reference = 'GG-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    /**
     * Ajoute une entrée à l'historique des statuts.
     */
    public function recordStatus(OrderStatus $status, ?User $changedBy = null, ?string $note = null): OrderStatusHistory
    {
        return $this->statusHistories()->create([
            'status' => $status,
            'changed_by' => $changedBy?->id,
            'note' => $note,
        ]);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_id');
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest('created_at')->oldest('id');
    }
}
