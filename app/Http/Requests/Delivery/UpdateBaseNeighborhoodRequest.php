<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Quartier de base du livreur (sa zone d'activité). Le contrôle du rôle est fait par les
 * middlewares de la route (role:delivery, approved).
 */
class UpdateBaseNeighborhoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_neighborhood_id' => ['required', 'integer', 'exists:neighborhoods,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_neighborhood_id.required' => 'Choisissez votre quartier de base.',
            'base_neighborhood_id.integer' => 'Choisissez un quartier de la liste.',
            'base_neighborhood_id.exists' => 'Choisissez un quartier de la liste.',
        ];
    }
}
