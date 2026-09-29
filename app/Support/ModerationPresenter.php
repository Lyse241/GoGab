<?php

namespace App\Support;

use App\Models\ModerationAction;
use App\Services\ModerationService;

/**
 * Données d'une action de modération pour les pages admin (fiche du compte, journal de modération).
 * Contient la note interne : ne jamais l'envoyer vers une page de l'utilisateur.
 */
class ModerationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(ModerationAction $action): array
    {
        $moderation = app(ModerationService::class);

        return [
            'id' => $action->id,
            'type' => $action->type->value,
            'type_label' => $action->type->label(),
            'type_color' => $action->type->color(),
            'reason' => $action->reason?->value,
            'reason_label' => $action->reason?->label(),
            'message' => $action->message,
            'internal_note' => $action->internal_note,
            'ends_at' => $action->ends_at ? $moderation->localDate($action->ends_at) : null,
            'acknowledged_at' => $action->acknowledged_at ? $moderation->localDate($action->acknowledged_at) : null,
            // Sans administrateur : action automatique (fin d'un blocage temporaire).
            'admin' => $action->admin?->name ?? ($action->admin_id === null ? 'Automatique' : 'Administrateur supprimé'),
            'at' => $moderation->localDate($action->created_at),
            'at_iso' => $action->created_at?->toIso8601String(),
        ];
    }
}
