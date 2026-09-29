<?php

namespace App\Http\Requests\Concerns;

use App\Models\Neighborhood;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Informations personnelles communes à toutes les inscriptions (client, livreur, entreprise) :
 * nom, téléphone (normalisé, unique), e-mail, mot de passe, quartier, repères d'adresse.
 */
trait ValidatesAccountDetails
{
    /**
     * Nettoie la saisie avant validation : l'unicité du téléphone ne dépend pas de sa forme.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeAccountDetails(array $input): array
    {
        $clean = fn (string $key, callable $transform) => is_string($input[$key] ?? null) ? $transform($input[$key]) : ($input[$key] ?? null);

        return [
            ...$input,
            'name' => $clean('name', fn ($value) => trim($value)),
            'email' => $clean('email', fn ($value) => mb_strtolower(trim($value))),
            'phone' => $clean('phone', fn ($value) => PhoneNumber::normalize($value)),
            'address_landmarks' => $clean('address_landmarks', fn ($value) => trim($value)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function accountDetailsRules(bool $withAddress = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^0\d{2} \d{2} \d{2} \d{2}$/', Rule::unique(User::class, 'phone')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if ($withAddress) {
            $rules['neighborhood_id'] = ['required', 'integer', Rule::exists(Neighborhood::class, 'id')];
            $rules['address_landmarks'] = ['required', 'string', 'min:10', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function accountDetailsMessages(): array
    {
        return [
            'name.required' => 'Indiquez votre nom complet.',
            'name.min' => 'Votre nom doit contenir au moins 2 caractères.',
            'phone.required' => 'Indiquez votre numéro de téléphone.',
            'phone.regex' => 'Numéro invalide : saisissez un numéro gabonais à 9 chiffres (ex. 077 12 34 56).',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé par un autre compte.',
            'email.required' => 'Indiquez votre adresse e-mail.',
            'email.email' => 'Cette adresse e-mail n’est pas valide.',
            'email.unique' => 'Un compte existe déjà avec cette adresse e-mail. Connectez-vous plutôt.',
            'password.required' => 'Choisissez un mot de passe.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'neighborhood_id.required' => 'Choisissez votre quartier.',
            'neighborhood_id.exists' => 'Ce quartier n’est pas desservi.',
            'address_landmarks.required' => 'Indiquez votre adresse et des repères.',
            'address_landmarks.min' => 'Soyez un peu plus précis (10 caractères minimum).',
            'address_landmarks.max' => '500 caractères maximum.',
        ];
    }
}
