<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAccountDetails;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * « Mon profil » (tous les rôles) : nom, téléphone, e-mail, quartier, repères d'adresse.
 * Mêmes règles qu'à l'inscription (ValidatesAccountDetails), l'unicité ignorant le compte lui-même.
 * Quartier et repères sont obligatoires pour un client (adresse de livraison par défaut).
 */
class ProfileUpdateRequest extends FormRequest
{
    use ValidatesAccountDetails;

    protected function prepareForValidation(): void
    {
        $this->replace(self::normalizeAccountDetails($this->all()));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $rules = self::accountDetailsRules(withAddress: true);
        $addressRequired = $user->isClient() ? 'required' : 'nullable';

        return [
            'name' => $rules['name'],
            'phone' => ['required', 'string', 'regex:/^0\d{2} \d{2} \d{2} \d{2}$/', Rule::unique(User::class, 'phone')->ignore($user->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'neighborhood_id' => [$addressRequired, 'integer', Rule::exists(Neighborhood::class, 'id')],
            'address_landmarks' => [$addressRequired, 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...self::accountDetailsMessages(),
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'email.lowercase' => 'Écrivez l’adresse e-mail en minuscules.',
        ];
    }
}
