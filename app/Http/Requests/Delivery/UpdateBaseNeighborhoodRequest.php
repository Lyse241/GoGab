<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'coverage_km' => ['nullable', 'integer', Rule::in(config('gogab.coverage.options'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'coverage_km.in' => 'Choisissez un rayon de la liste.',
            'base_neighborhood_id.required' => 'Choisissez votre commune d’activité.',
            'base_neighborhood_id.integer' => 'Choisissez une commune de la liste.',
            'base_neighborhood_id.exists' => 'Choisissez une commune de la liste.',
        ];
    }
}
