<?php

namespace App\Http\Requests\Business;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout / modification d'un produit du catalogue par l'entreprise.
 * Prix en FCFA entiers ; section libre (ex. « Plats », « Boissons »).
 */
class ProductRequest extends FormRequest
{
    public const IMAGE_MAX_KB = 2048;

    public const MAX_PRICE = 10_000_000;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) && trim($value) !== '' ? preg_replace('/\s+/u', ' ', trim($value)) : null;
        $price = $this->input('price');

        $this->merge([
            'name' => $clean($this->input('name')),
            'description' => is_string($this->input('description')) && trim($this->input('description')) !== '' ? trim($this->input('description')) : null,
            'menu_section' => ($section = $clean($this->input('menu_section'))) !== null ? mb_strtoupper(mb_substr($section, 0, 1)).mb_substr($section, 1) : null,
            // « 4 500 » ou « 4500 FCFA » → 4500.
            'price' => is_string($price) ? preg_replace('/[\s\x{00A0}\x{202F}]|fcfa/iu', '', $price) : $price,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PRICE],
            'menu_section' => ['nullable', 'string', 'max:60'],
            'is_available' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::IMAGE_MAX_KB],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom du produit.',
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'name.max' => '120 caractères maximum.',
            'description.max' => '500 caractères maximum.',
            'price.required' => 'Indiquez le prix.',
            'price.integer' => 'Le prix doit être un nombre entier de FCFA (sans centimes).',
            'price.min' => 'Le prix doit être d’au moins 1 FCFA.',
            'price.max' => 'Le prix ne peut pas dépasser 10 000 000 FCFA.',
            'menu_section.max' => '60 caractères maximum.',
            'is_available.required' => 'Indiquez si le produit est disponible.',
            'image.image' => 'La photo doit être une image.',
            'image.mimes' => 'Photo : JPG, PNG ou WebP uniquement.',
            'image.max' => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }
}
