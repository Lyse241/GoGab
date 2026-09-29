<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ValidatesAccountDetails;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription d'un client (/register/client).
 */
class RegisterClientRequest extends FormRequest
{
    use ValidatesAccountDetails;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Téléphone normalisé avant validation : l'unicité ne dépend pas des espaces ou de l'indicatif.
     */
    protected function prepareForValidation(): void
    {
        $this->replace(self::normalizeAccountDetails($this->input()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::accountDetailsRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...self::accountDetailsMessages(),
            'address_landmarks.required' => 'Indiquez votre adresse et des repères pour le livreur.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'mot de passe'];
    }
}
