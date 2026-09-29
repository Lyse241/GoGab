<?php

namespace App\Services;

use App\Enums\AccountDecisionAction;
use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\AccountDecision;
use App\Models\Store;
use App\Models\User;
use App\Support\ImageStorage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Création des comptes publics (client, livreur, entreprise).
 * Tout compte est « pending » tant qu'un administrateur ne l'a pas validé
 * (sauf clients avec gogab.auto_approve_clients).
 */
class AccountRegistration
{
    public function __construct(private readonly DocumentService $documents) {}


    /**
     * @param  array{name: string, phone: string, email: string, password: string, neighborhood_id: int, address_landmarks: string}  $data
     */
    public function registerClient(array $data): User
    {
        $autoApprove = (bool) config('gogab.auto_approve_clients');

        $user = DB::transaction(fn () => User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => Role::Client,
            'neighborhood_id' => $data['neighborhood_id'],
            'address_landmarks' => $data['address_landmarks'],
            'account_status' => $autoApprove ? AccountStatus::Approved : AccountStatus::Pending,
            'approved_at' => $autoApprove ? now() : null,
        ]));

        event(new Registered($user));
        AccountDecision::record($user, AccountDecisionAction::Submitted, $user);

        $this->notifyAdmins(
            $user,
            $autoApprove ? 'Nouveau client inscrit' : 'Nouveau compte client à valider',
            $autoApprove
                ? "{$user->name} ({$user->phone}) s’est inscrit ; son compte a été validé automatiquement."
                : "{$user->name} ({$user->phone}) vient de s’inscrire et attend votre validation.",
            $autoApprove ? 'info' : 'warning',
        );

        return $user;
    }

    /**
     * Crée, dans une transaction, le livreur (pending), son profil véhicule et ses documents.
     * Si quoi que ce soit échoue, rien n'est enregistré et les fichiers déjà copiés sont supprimés.
     *
     * @param  array<string, mixed>  $data  données validées (RegisterDeliveryRequest)
     * @param  array<string, UploadedFile>  $files  fichiers indexés par type de document
     */
    public function registerDelivery(array $data, array $files): User
    {
        $vehicle = VehicleType::from($data['vehicle_type']);
        $stored = [];

        try {
            $user = DB::transaction(function () use ($data, $files, $vehicle, &$stored) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => Role::Delivery,
                    'neighborhood_id' => $data['neighborhood_id'],
                    'address_landmarks' => $data['address_landmarks'],
                    'account_status' => AccountStatus::Pending,
                ]);

                $user->deliveryProfile()->create([
                    'vehicle_type' => $vehicle,
                    'vehicle_brand' => $data['vehicle_brand'] ?? null,
                    'plate_number' => $vehicle->requiresLicense() ? $data['plate_number'] : null,
                    'license_number' => $vehicle->requiresLicense() ? $data['license_number'] : null,
                    'base_neighborhood_id' => $data['base_neighborhood_id'],
                    'is_available' => false,
                ]);

                // Seuls les documents obligatoires pour ce véhicule sont conservés.
                $this->attachDocuments($user, DocumentType::requiredFor(Role::Delivery, $vehicle), $files, $stored);

                return $user;
            });
        } catch (Throwable $exception) {
            $this->documents->deleteFiles($stored);

            throw $exception;
        }

        event(new Registered($user));
        AccountDecision::record($user, AccountDecisionAction::Submitted, $user);

        $this->notifyAdmins(
            $user,
            'Nouveau livreur à valider',
            "{$user->name} ({$user->phone}, {$vehicle->label()}) a envoyé son dossier de livreur.",
            'warning',
        );

        return $user;
    }

    /**
     * Crée, dans une transaction, le gérant (pending), son commerce (inactif, invisible côté public
     * jusqu'à la validation), ses horaires et ses documents (obligatoires + facultatifs fournis).
     *
     * @param  array<string, mixed>  $data  données validées (RegisterBusinessRequest)
     * @param  array<string, UploadedFile|null>  $files  documents indexés par type
     */
    public function registerBusiness(array $data, ?UploadedFile $logo, array $files): User
    {
        $stored = [];
        $logoPath = null;

        try {
            $user = DB::transaction(function () use ($data, $logo, $files, &$stored, &$logoPath) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => Role::Business,
                    // Le gérant est rattaché au quartier de son commerce.
                    'neighborhood_id' => $data['neighborhood_id'],
                    'account_status' => AccountStatus::Pending,
                ]);

                $logoPath = $logo ? ImageStorage::store($logo, 'stores/logos') : null;

                $store = Store::create([
                    'owner_id' => $user->id,
                    'name' => $data['store_name'],
                    'category_id' => $data['category_id'],
                    'description' => $data['description'] ?? null,
                    'phone' => $data['store_phone'],
                    'neighborhood_id' => $data['neighborhood_id'],
                    'address_landmarks' => $data['address_landmarks'],
                    'logo' => $logoPath,
                    'is_open' => true,
                    // Activé par l'admin à la validation du compte.
                    'is_active' => false,
                ]);

                StoreHours::sync($store, $data['opening_hours']);

                $types = [
                    ...DocumentType::requiredFor(Role::Business),
                    ...array_filter(DocumentType::optionalFor(Role::Business), fn (DocumentType $type) => isset($files[$type->value])),
                ];
                $this->attachDocuments($user, $types, $files, $stored);

                return $user;
            });
        } catch (Throwable $exception) {
            $this->documents->deleteFiles($stored);
            ImageStorage::delete($logoPath);

            throw $exception;
        }

        event(new Registered($user));
        AccountDecision::record($user, AccountDecisionAction::Submitted, $user);

        $this->notifyAdmins(
            $user,
            'Nouvelle entreprise à valider',
            "{$user->store->name} ({$user->store->category->name}), géré par {$user->name} ({$user->phone}), attend votre validation.",
            'warning',
        );

        return $user;
    }

    /**
     * Enregistre les fichiers (disque privé) et crée les documents correspondants, en attente de vérification.
     *
     * @param  list<DocumentType>  $types
     * @param  array<string, UploadedFile|null>  $files
     * @param  list<string>  $stored  chemins écrits (pour nettoyer en cas d'échec)
     */
    private function attachDocuments(User $user, array $types, array $files, array &$stored): void
    {
        foreach ($types as $type) {
            $stored[] = $this->documents->store($user, $files[$type->value], $type)->file_path;
        }
    }


    /**
     * Prévient les administrateurs, avec un lien direct vers la page de validation du compte.
     */
    private function notifyAdmins(User $user, string $title, string $message, string $type): void
    {
        Notifier::admins($title, $message, route('admin.accounts.show', $user), $type);
    }
}
