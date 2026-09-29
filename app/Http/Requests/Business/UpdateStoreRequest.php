<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\Concerns\ValidatesOpeningHours;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * « Mon commerce » : profil public du commerce, horaires, logo et couverture.
 * Les documents ne se modifient pas ici.
 */
class UpdateStoreRequest extends FormRequest
{
    use ValidatesOpeningHours;

    /** Tailles maximales en Ko (les photos sont réduites dans le navigateur avant l'envoi). */
    public const LOGO_MAX_KB = 2048;

    public const COVER_MAX_KB = 4096;

    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->user()->store);
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $this->merge([
            'name' => $clean($this->input('name')),
            'description' => $clean($this->input('description')),
            'address_landmarks' => $clean($this->input('address_landmarks')),
            'phone' => is_string($this->input('phone')) ? PhoneNumber::normalize($this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'description' => ['nullable', 'string', 'max:300'],
            'phone' => ['required', 'string', 'regex:/^0\d{2} \d{2} \d{2} \d{2}$/'],
            'neighborhood_id' => ['required', 'integer', Rule::exists(Neighborhood::class, 'id')],
            'address_landmarks' => ['required', 'string', 'min:10', 'max:500'],
            ...self::openingHoursRules(required: true),
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::LOGO_MAX_KB],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::COVER_MAX_KB, 'dimensions:min_width=400,min_height=200'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_cover_image' => ['sometimes', 'boolean'],
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
            ...self::openingHoursMessages(),
            'name.required' => 'Indiquez le nom commercial.',
            'name.min' => 'Le nom commercial doit contenir au moins 2 caractères.',
            'name.max' => '120 caractères maximum.',
            'category_id.required' => 'Choisissez la catégorie de votre commerce.',
            'category_id.exists' => 'Cette catégorie n’existe pas.',
            'description.max' => '300 caractères maximum.',
            'phone.required' => 'Indiquez le téléphone du commerce.',
            'phone.regex' => 'Numéro invalide : saisissez un numéro gabonais à 9 chiffres (ex. 074 12 34 56).',
            'neighborhood_id.required' => 'Choisissez le quartier du commerce.',
            'neighborhood_id.exists' => 'Ce quartier n’est pas desservi.',
            'address_landmarks.required' => 'Indiquez l’adresse et des repères pour trouver le commerce.',
            'address_landmarks.min' => 'Donnez un peu plus de repères (10 caractères minimum).',
            'address_landmarks.max' => '500 caractères maximum.',
            'logo.image' => 'Le logo doit être une image.',
            'logo.mimes' => 'Logo : JPG, PNG ou WebP uniquement.',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
            'cover_image.image' => 'La couverture doit être une image.',
            'cover_image.mimes' => 'Couverture : JPG, PNG ou WebP uniquement.',
            'cover_image.max' => 'La couverture ne doit pas dépasser 4 Mo.',
            'cover_image.dimensions' => 'La couverture doit mesurer au moins 400 × 200 pixels.',
        ];
    }
}
