<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stockage des images de boutiques et produits sur le disque "public"
 * (servies via /storage après `php artisan storage:link`).
 */
class ImageStorage
{
    public static function store(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'public');
    }

    /**
     * Supprime une image locale. Les URLs externes (placeholders Picsum) sont ignorées.
     */
    public static function delete(?string $path): void
    {
        if ($path && ! preg_match('#^https?://#', $path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Remplace l'image si un nouveau fichier est envoyé, sinon garde l'actuelle.
     */
    public static function replace(?UploadedFile $file, ?string $current, string $folder): ?string
    {
        if (! $file) {
            return $current;
        }

        self::delete($current);

        return self::store($file, $folder);
    }
}
