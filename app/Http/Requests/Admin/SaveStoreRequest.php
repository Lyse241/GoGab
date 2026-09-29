<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesOpeningHours;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création / modification d'une boutique par l'admin (infos, photo, horaires, fermeture temporaire).
 */
class SaveStoreRequest extends FormRequest
{
    use ValidatesOpeningHours;

    /**
     * L'accès (admin) est déjà contrôlé par le middleware de la route.
     */
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
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Interrupteur de fermeture temporaire (false = fermé même pendant les horaires).
            'is_open' => ['sometimes', 'boolean'],
            ...$this->openingHoursRules(),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateOpeningHoursConsistency($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Choisissez une catégorie.',
            'category_id.exists' => "Cette catégorie n'existe pas.",
            'cover_image.max' => "L'image ne doit pas dépasser 2 Mo.",
            ...$this->openingHoursMessages(),
        ];
    }
}
