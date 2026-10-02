<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Délai sans livreur après lequel l'entreprise peut relancer l'annonce ou annuler la commande.
     */
    public const ANNOUNCEMENT_RETRY_MINUTES = 5;

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
        'announced_at',
        'announcement_count',
        'cash_collected_at',
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
            'announced_at' => 'datetime',
            'announcement_count' => 'integer',
            'cash_collected_at' => 'datetime',
        ];
    }

    /**
     * Monnaie à rendre par le livreur (montant remis − total) ; null hors paiement à la livraison
     * ou si aucun montant n'a été indiqué.
     */
    protected function changeDue(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->payment_method === PaymentMethod::Cash && $this->cash_given !== null
            ? round((float) $this->cash_given - (float) $this->total_price, 2)
            : null);
    }

    /**
     * Heure à partir de laquelle l'entreprise peut relancer (ou annuler) une annonce restée
     * sans livreur ; null si la commande n'est pas en recherche de livreur.
     */
    public function announcementRetryAt(): ?CarbonInterface
    {
        if ($this->status !== OrderStatus::SearchingCourier || $this->delivery_id !== null) {
            return null;
        }

        return ($this->announced_at ?? $this->updated_at)?->copy()->addMinutes(self::ANNOUNCEMENT_RETRY_MINUTES);
    }

    /**
     * Annonce restée sans réponse depuis au moins ANNOUNCEMENT_RETRY_MINUTES.
     */
    public function announcementIsStale(): bool
    {
        $retryAt = $this->announcementRetryAt();

        return $retryAt !== null && now()->greaterThanOrEqualTo($retryAt);
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
     * Ajoute une entrée à l'historique des statuts. Réservé à App\Services\OrderWorkflow :
     * le statut d'une commande ne change jamais ailleurs.
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
