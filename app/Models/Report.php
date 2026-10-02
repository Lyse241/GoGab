<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Signalement d'un utilisateur (reportedUser) par l'autre partie d'une commande (reporter).
 * Créé et traité uniquement via App\Services\ReportService.
 */
class Report extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'order_id',
        'reason',
        'description',
        'status',
        'handled_by',
        'handled_at',
        'admin_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function isUrgent(): bool
    {
        return $this->reason->isUrgent() && $this->status->isPending();
    }

    /**
     * Signalements à traiter (ouverts ou en cours d'examen).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatus::pending());
    }

    /**
     * Fraudes à traiter en tête, puis les plus récents.
     */
    public function scopeUrgentFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when reason = ? and status in (?, ?) then 0 else 1 end', [
                ReportReason::Fraude->value,
                ReportStatus::Open->value,
                ReportStatus::InReview->value,
            ])
            ->latest()
            ->latest('id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
