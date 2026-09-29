<?php

namespace App\Http\Requests\Admin;

use App\Models\Neighborhood;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout / modification d'un quartier et de sa zone (la zone définit « les livreurs autour »).
 */
class NeighborhoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim($this->name))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique(Neighborhood::class, 'name')->ignore($this->route('neighborhood')?->id)],
            'zone' => ['required', Rule::in(Neighborhood::ZONES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom du quartier.',
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'name.max' => '80 caractères maximum.',
            'name.unique' => 'Ce quartier existe déjà.',
            'zone.required' => 'Choisissez la zone du quartier.',
            'zone.in' => 'Zone inconnue.',
        ];
    }
}
