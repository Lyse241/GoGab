<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Suppression d'un compte par son titulaire, par anonymisation : la ligne `users` reste pour que
 * les commandes, leur historique et les signalements des autres parties restent intacts
 * (aucune suppression en cascade). Les données personnelles et les documents sont effacés,
 * la connexion devient impossible, un commerce est retiré du catalogue.
 */
class AccountDeletionService
{
    public const DELETED_NAME = 'Compte supprimé';

    public function __construct(
        private readonly ModerationService $moderation,
        private readonly DocumentService $documents,
    ) {}

    /**
     * @throws ValidationException commande en cours, ou dernier administrateur
     */
    public function delete(User $user): void
    {
        $active = $this->moderation->activeOrders($user);
        if ($active->isNotEmpty()) {
            throw ValidationException::withMessages([
                'password' => $active->count() > 1
                    ? "Vous avez {$active->count()} commandes en cours : attendez qu’elles soient terminées avant de supprimer votre compte."
                    : "La commande {$active->first()->reference} est en cours : attendez qu’elle soit terminée avant de supprimer votre compte.",
            ]);
        }

        if ($user->isAdmin() && ! User::where('role', Role::Admin)->where('account_status', AccountStatus::Approved)->whereNull('deleted_at')->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'password' => 'Vous êtes le dernier administrateur : nommez-en un autre avant de supprimer votre compte.',
            ]);
        }

        $paths = $user->documents()->pluck('file_path')->all();

        DB::transaction(function () use ($user) {
            $user->documents()->delete();
            $user->notifications()->delete();

            // Livreur : plus jamais proposé ; données du véhicule effacées.
            $user->deliveryProfile?->update(['is_available' => false, 'plate_number' => null, 'license_number' => null]);

            // Entreprise : le commerce quitte le catalogue (ses commandes passées restent).
            $user->store?->update(['is_active' => false, 'is_open' => false]);

            $user->forceFill([
                'name' => self::DELETED_NAME,
                'email' => "supprime-{$user->id}-".Str::lower(Str::random(8)).'@gogab.invalid',
                'phone' => null,
                'address_landmarks' => null,
                'neighborhood_id' => null,
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'deleted_at' => now(),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        // Fichiers privés supprimés une fois l'anonymisation enregistrée.
        $this->documents->deleteFiles($paths);
    }
}
