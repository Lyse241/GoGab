<?php

namespace App\Models;

use App\Enums\ModerationReason;
use App\Enums\ModerationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Action de modération sur un compte (historique en ajout seul).
 * `message` est visible par l'utilisateur ; `internal_note` par les admins seulement.
 */
class ModerationAction extends Model
{
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'admin_id',
        'type',
        'reason',
        'message',
        'internal_note',
        'ends_at',
        'acknowledged_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ModerationType::class,
            'reason' => ModerationReason::class,
            'ends_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
