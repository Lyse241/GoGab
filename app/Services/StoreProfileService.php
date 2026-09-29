<?php

namespace App\Services;

use App\Models\Store;
use App\Support\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Profil d'un commerce géré par son entreprise (« Mon commerce ») et interrupteur d'ouverture.
 */
class StoreProfileService
{
    /**
     * Met à jour le profil, les horaires et les images. Les nouvelles images sont écrites avant la
     * transaction (et effacées si elle échoue) ; les anciennes ne sont supprimées qu'après validation.
     *
     * @param  array<string, mixed>  $data  données validées (UpdateStoreRequest)
     */
    public function update(Store $store, array $data, ?UploadedFile $logo = null, ?UploadedFile $cover = null): Store
    {
        $images = [
            'logo' => [$logo, (bool) ($data['remove_logo'] ?? false), 'stores/logos'],
            'cover_image' => [$cover, (bool) ($data['remove_cover_image'] ?? false), 'stores'],
        ];

        $attributes = collect($data)->only(['name', 'category_id', 'description', 'phone', 'neighborhood_id', 'address_landmarks'])->all();
        $newFiles = [];
        $oldFiles = [];

        foreach ($images as $column => [$file, $remove, $folder]) {
            if ($file) {
                $attributes[$column] = $newFiles[] = ImageStorage::store($file, $folder);
                $oldFiles[] = $store->{$column};
            } elseif ($remove) {
                $attributes[$column] = null;
                $oldFiles[] = $store->{$column};
            }
        }

        try {
            DB::transaction(function () use ($store, $attributes, $data) {
                $store->update($attributes);
                StoreHours::sync($store, $data['opening_hours']);
            });
        } catch (\Throwable $e) {
            array_walk($newFiles, fn (string $path) => ImageStorage::delete($path));

            throw $e;
        }

        array_walk($oldFiles, fn (?string $path) => ImageStorage::delete($path));

        return $store->refresh();
    }

    /**
     * Interrupteur « Commerce ouvert / fermé » : fermeture temporaire, en plus des horaires.
     * Le commerce n'est réellement ouvert que si l'interrupteur est sur ouvert ET que l'heure
     * est dans ses horaires (StoreHours::isOpenNow).
     */
    public function setOpen(Store $store, bool $open): Store
    {
        $store->update(['is_open' => $open]);

        return $store;
    }
}
