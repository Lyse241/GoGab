<?php

namespace App\Models;

use App\Enums\AccountDecisionAction;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de l'historique d'un dossier : qui, quoi, quand (et pourquoi pour un refus).
 */
class AccountDecision extends Model
{
    /**
     * Historique en ajout seul : pas de colonne updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'document_id',
        'document_type',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AccountDecisionAction::class,
            'document_type' => DocumentType::class,
        ];
    }

    /**
     * Ajoute un événement à l'historique du compte.
     */
    public static function record(User $account, AccountDecisionAction $action, ?User $actor, ?Document $document = null, ?string $note = null): self
    {
        return self::create([
            'user_id' => $account->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'document_id' => $document?->id,
            'document_type' => $document?->type,
            'note' => $note,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
