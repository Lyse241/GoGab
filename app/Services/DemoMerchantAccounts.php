<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Comptes entreprise de démonstration : donne à chaque commerce un propriétaire dont l'identifiant
 * se devine (nomducommerce@gogab.com, mot de passe « password »), pour ouvrir le tableau de bord de
 * n'importe quel commerce pendant une démo ou une soutenance.
 *
 * Idempotent : un commerce qui a déjà un propriétaire est laissé tel quel, sauf avec $includeOwned
 * (e-mail et mot de passe du propriétaire sont alors réalignés sur la règle). Les propriétaires déjà
 * en place (par exemple entreprise@gogab.ga) ne sont jamais touchés par défaut.
 */
class DemoMerchantAccounts
{
    public const DOMAIN = 'gogab.com';

    public const PASSWORD = 'password';

    /**
     * @return list<array{store: string, email: string, password: string, created: bool}>
     */
    public function ensure(bool $includeOwned = false): array
    {
        $rows = [];

        DB::transaction(function () use ($includeOwned, &$rows): void {
            $stores = Store::with('owner')->orderBy('id')->get();
            // Numéro de téléphone fictif unique (colonne unique) : 074 90 NN NN.
            $counter = (int) User::where('phone', 'like', '074 90 %')->count();

            foreach ($stores as $store) {
                $email = $this->emailFor($store);

                if ($store->owner && ! $includeOwned) {
                    $rows[] = ['store' => $store->name, 'email' => $store->owner->email, 'password' => $store->owner->email === $email ? self::PASSWORD : '(inchangé)', 'created' => false];

                    continue;
                }

                $owner = $store->owner;
                // Seuls les propriétaires validés sont réalignés : un dossier refusé ou en attente reste tel quel.
                if ($owner && (! $owner->isBusiness() || ! $owner->isApproved())) {
                    continue;
                }

                $created = ! $owner;
                if (! $owner) {
                    $owner = User::where('email', $email)->first() ?? new User(['email' => $email]);
                    $created = ! $owner->exists;
                }
                if ($owner->phone === null) {
                    $counter++;
                    $owner->phone = sprintf('074 90 %02d %02d', intdiv($counter, 100) % 100, $counter % 100);
                }

                $owner->forceFill([
                    'name' => $owner->name ?: $store->name,
                    'email' => $email,
                    'role' => Role::Business,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => $owner->email_verified_at ?? now(),
                    'account_status' => AccountStatus::Approved,
                    'approved_at' => $owner->approved_at ?? now(),
                    'rejection_reason' => null,
                    'neighborhood_id' => $owner->neighborhood_id ?? $store->neighborhood_id,
                ])->save();

                $store->forceFill(['owner_id' => $owner->id])->save();
                $rows[] = ['store' => $store->name, 'email' => $email, 'password' => self::PASSWORD, 'created' => $created];
            }
        });

        return $rows;
    }

    /** nomducommerce@gogab.com : nom sans accents, espaces ni ponctuation ; suffixe numérique en cas de doublon. */
    public function emailFor(Store $store): string
    {
        $base = Str::slug($store->name, '') ?: 'commerce'.$store->id;
        $email = "{$base}@".self::DOMAIN;
        $suffix = 1;

        // Doublon : l'e-mail est pris par un compte qui gère déjà un autre commerce.
        while (($taken = User::where('email', $email)->first())
            && $taken->id !== $store->owner_id
            && Store::where('owner_id', $taken->id)->where('id', '!=', $store->id)->exists()) {
            $suffix++;
            $email = "{$base}{$suffix}@".self::DOMAIN;
        }

        return $email;
    }
}
