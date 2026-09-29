<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreHours;
use App\Services\DocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Comptes de test dans tous les états (mot de passe : "password").
 * Complète AdminSeeder (admin@gogab.ga) et UserSeeder (client1…3, livreur1…2, validés).
 * Nécessite NeighborhoodSeeder, CategorySeeder et StoreSeeder.
 *
 * | E-mail                      | Rôle       | Statut    |
 * | client.attente@gogab.ga     | client     | pending   |
 * | client.suspendu@gogab.ga    | client     | suspended |
 * | livreur.attente@gogab.ga    | delivery   | pending   |
 * | entreprise@gogab.ga         | business   | approved  | (propriétaire de « Chez Maman Ngoye »)
 * | entreprise.refusee@gogab.ga | business   | rejected  | (motif renseigné)
 *
 * Les documents de démonstration sont de petites images générées (disque privé), pour tester
 * l'aperçu et la validation dans l'espace admin.
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $neighborhoods = Neighborhood::pluck('id', 'name');

        $this->account('client.attente@gogab.ga', 'Client En Attente', '066 20 00 04', Role::Client, AccountStatus::Pending, [
            'neighborhood_id' => $neighborhoods['Lalala'],
            'address_landmarks' => 'Derrière la station Total, maison jaune',
        ]);

        $this->account('client.suspendu@gogab.ga', 'Client Suspendu', '066 20 00 05', Role::Client, AccountStatus::Suspended, [
            'neighborhood_id' => $neighborhoods['Glass'],
        ]);

        $courier = $this->account('livreur.attente@gogab.ga', 'Livreur En Attente', '077 10 00 03', Role::Delivery, AccountStatus::Pending, [
            'neighborhood_id' => $neighborhoods['Akanda'],
            'address_landmarks' => 'Carrefour Akanda, après la boulangerie, portail vert',
        ]);
        $courier->deliveryProfile()->updateOrCreate([], [
            'vehicle_type' => VehicleType::Moto,
            'vehicle_brand' => 'Yamaha',
            'plate_number' => 'GA-1234-LBV',
            'license_number' => 'P-0456789',
            'base_neighborhood_id' => $neighborhoods['Akanda'],
            'is_available' => false,
        ]);
        foreach ([DocumentType::IdCard, DocumentType::DrivingLicense, DocumentType::VehiclePhotoFront] as $type) {
            $courier->documents()->updateOrCreate(['type' => $type], [
                'file_path' => $this->demoFile("documents/demo/{$type->value}.jpg", $type->label()),
                'original_name' => "{$type->value}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 245_000,
                'status' => DocumentStatus::Pending,
            ]);
        }

        // Entreprise validée : propriétaire d'un commerce de démonstration existant.
        $business = $this->account('entreprise@gogab.ga', 'Maman Ngoye', '074 30 00 01', Role::Business, AccountStatus::Approved, [
            'neighborhood_id' => $neighborhoods['Nombakélé'],
        ]);
        Store::where('name', 'Chez Maman Ngoye')->update([
            'owner_id' => $business->id,
            'phone' => '074 30 00 01',
            'description' => 'Cuisine gabonaise maison : poulet nyembwe, feuilles de manioc, poisson braisé.',
            'address_landmarks' => 'Nombakélé, en face du marché, restaurant à la façade verte',
        ]);

        // Entreprise refusée : commerce créé mais inactif, dossier à corriger.
        $rejected = $this->account('entreprise.refusee@gogab.ga', 'Snack Le Rond-Point', '074 30 00 02', Role::Business, AccountStatus::Rejected, [
            'neighborhood_id' => $neighborhoods['Awendjé'],
            'rejection_reason' => "Le registre du commerce (RCCM) est illisible et le numéro d'identification fiscale ne correspond pas au nom du commerce. Merci d'envoyer une photo nette du RCCM.",
        ]);
        $store = Store::updateOrCreate(['name' => 'Snack Le Rond-Point'], [
            'owner_id' => $rejected->id,
            'category_id' => Category::where('slug', 'fast-food')->value('id'),
            'neighborhood_id' => $neighborhoods['Awendjé'],
            'phone' => '074 30 00 02',
            'is_open' => true,
            'is_active' => false,
            'cover_image' => StoreSeeder::placeholder('Snack Le Rond-Point'),
        ]);
        StoreHours::sync($store, StoreHours::everyDay('11:00', '23:00', [1]));
        $rejected->documents()->updateOrCreate(['type' => DocumentType::BusinessRegistration], [
            'file_path' => $this->demoFile('documents/demo/rccm.jpg', DocumentType::BusinessRegistration->label()),
            'original_name' => 'rccm.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 180_000,
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => 'Photo floue : le numéro RCCM est illisible.',
            'reviewed_by' => User::where('email', 'admin@gogab.ga')->value('id'),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Image d'exemple (JPEG) sur le disque privé, créée si absente. Sans GD, le chemin reste fictif.
     */
    private function demoFile(string $path, string $label): string
    {
        $disk = Storage::disk(DocumentService::DISK);

        if (! $disk->exists($path) && function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(640, 400);
            imagefill($image, 0, 0, imagecolorallocate($image, 230, 248, 241));
            imagerectangle($image, 10, 10, 629, 389, imagecolorallocate($image, 0, 134, 96));
            $text = imagecolorallocate($image, 30, 58, 138);
            imagestring($image, 5, 30, 170, 'DOCUMENT DE DEMONSTRATION', $text);
            imagestring($image, 4, 30, 200, iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: 'Document', $text);

            ob_start();
            imagejpeg($image, null, 80);
            $disk->put($path, ob_get_clean());
            imagedestroy($image);
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function account(string $email, string $name, string $phone, Role $role, AccountStatus $status, array $extra = []): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'phone' => $phone,
            'role' => $role,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'account_status' => $status,
            'approved_at' => $status === AccountStatus::Approved ? now() : null,
            'rejection_reason' => null,
            ...$extra,
        ]);
    }
}
