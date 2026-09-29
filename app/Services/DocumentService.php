<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pièces justificatives (inscriptions livreur et entreprise, corrections, profil).
 *
 * Fichiers sur le disque PRIVÉ « local » : storage/app/private/documents/{user_id}/, nom aléatoire,
 * jamais dans public/. Lecture uniquement via DocumentController@show (DocumentPolicy).
 */
class DocumentService
{
    public const DISK = 'local';

    /**
     * Enregistre le fichier et crée le document. Un nouvel envoi du même type remplace l'ancien :
     * même ligne, statut repassé à pending, ancien fichier supprimé une fois la transaction validée.
     */
    public function store(User $user, UploadedFile $file, DocumentType $type): Document
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs("documents/{$user->id}", "{$type->value}-".Str::random(24).".{$extension}", self::DISK);

        $previousPath = $user->documents()->where('type', $type)->value('file_path');

        $document = $user->documents()->updateOrCreate(['type' => $type], [
            'file_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'status' => DocumentStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        if ($previousPath && $previousPath !== $path) {
            // Immédiat hors transaction ; sinon seulement si la transaction aboutit.
            DB::afterCommit(fn () => $this->deleteFiles($previousPath));
        }

        return $document;
    }

    /**
     * Règles de validation d'un fichier : JPG / PNG (et PDF sauf pour les photos), 5 Mo maximum,
     * et vrai type du contenu vérifié (un fichier texte renommé en .jpg est refusé).
     *
     * @return list<mixed>
     */
    public static function rules(DocumentType $type, bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', $type->extensions()),
            'max:'.DocumentType::MAX_KILOBYTES,
            function (string $attribute, mixed $file, \Closure $fail) use ($type) {
                if ($file instanceof UploadedFile && ! in_array(self::realMimeType($file), $type->mimeTypes(), true)) {
                    $fail("{$type->label()} : le contenu du fichier ne correspond pas à un format accepté.");
                }
            },
        ];
    }

    /**
     * Type réel lu dans le contenu du fichier (et non déduit de son nom).
     */
    public static function realMimeType(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        return $path ? ((new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: null) : null;
    }

    /**
     * Supprime des fichiers du disque privé (ex. nettoyage après une inscription échouée).
     *
     * @param  string|list<string>|null  $paths
     */
    public function deleteFiles(string|array|null $paths): void
    {
        $paths = array_filter((array) $paths);

        if ($paths) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
