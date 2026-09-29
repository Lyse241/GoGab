<?php

namespace App\Support;

use App\Models\Document;

/**
 * Données d'un document pour les pages Inertia (validation admin, correction du dossier).
 * Le fichier n'est jamais exposé directement : `url` pointe vers la route sécurisée documents.show.
 */
class DocumentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Document $document, bool $required = true): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'label' => $document->type->label(),
            'required' => $required,
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'status_color' => $document->status->color(),
            'rejection_reason' => $document->rejection_reason,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'is_image' => str_starts_with($document->mime_type, 'image/'),
            'is_pdf' => $document->mime_type === 'application/pdf',
            'url' => route('documents.show', $document),
            'sent_at' => $document->updated_at?->format('d/m/Y à H:i'),
            'reviewed_by' => $document->reviewer?->name,
            'reviewed_at' => $document->reviewed_at?->format('d/m/Y à H:i'),
        ];
    }
}
