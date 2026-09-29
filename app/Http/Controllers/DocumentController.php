<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces justificatives du disque privé : visibles seulement par leur propriétaire
 * ou par un administrateur (DocumentPolicy::view).
 */
class DocumentController extends Controller
{
    /**
     * Types affichés dans le navigateur ; tout autre fichier est proposé en téléchargement.
     */
    private const INLINE_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    /**
     * Renvoie le fichier en flux.
     */
    public function show(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk(DocumentService::DISK);
        abort_unless($disk->exists($document->file_path), 404);

        $inline = in_array($document->mime_type, self::INLINE_TYPES, true);

        return $disk->response(
            $document->file_path,
            $document->original_name,
            [
                'Content-Type' => $inline ? $document->mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }
}
