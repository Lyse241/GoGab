<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / modification d'une catégorie de commerce (espace admin).
 */
class CategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'min:2', 'max:60', Rule::unique(Category::class, 'name')->ignore($category?->id)],
            'icon' => ['required', Rule::in(config('gogab.category_icons'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom de la catégorie.',
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'name.max' => '60 caractères maximum.',
            'name.unique' => 'Une catégorie porte déjà ce nom.',
            'icon.required' => 'Choisissez une icône.',
            'icon.in' => 'Cette icône n’est pas proposée.',
            'sort_order.integer' => 'L’ordre d’affichage doit être un nombre.',
            'sort_order.min' => 'L’ordre d’affichage ne peut pas être négatif.',
        ];
    }
}
