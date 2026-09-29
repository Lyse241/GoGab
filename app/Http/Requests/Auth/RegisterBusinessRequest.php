<?php

namespace App\Http\Requests\Auth;

use App\Enums\DocumentType;
use App\Services\DocumentService;
use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesAccountDetails;
use App\Http\Requests\Concerns\ValidatesOpeningHours;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Inscription d'une entreprise (/register/business), en 3 étapes :
 * 1. gérant, 2. commerce (dont horaires et logo), 3. documents
 * (obligatoires : DocumentType::requiredFor(Role::Business) ; facultatifs : optionalFor()).
 *
 * Les règles de chaque étape servent aussi à POST /register/business/check.
 */
class RegisterBusinessRequest extends FormRequest
{
    use ValidatesAccountDetails;
    use ValidatesOpeningHours;

    public const STEPS = [1, 2, 3];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->replace(self::normalize($this->input()));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $input = self::normalizeAccountDetails($input);
        $clean = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        return [
            ...$input,
            'store_name' => $clean($input['store_name'] ?? null),
            'description' => $clean($input['description'] ?? null),
            'store_phone' => is_string($input['store_phone'] ?? null) ? PhoneNumber::normalize($input['store_phone']) : null,
        ];
    }

    /**
     * Règles d'une étape (1 = gérant, 2 = commerce, 3 = documents).
     *
     * @return array<string, mixed>
     */
    public static function stepRules(int $step): array
    {
        return match ($step) {
            // Gérant : pas d'adresse personnelle, c'est celle du commerce qui compte.
            1 => self::accountDetailsRules(withAddress: false),
            2 => [
                'store_name' => ['required', 'string', 'min:2', 'max:120'],
                'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
                'description' => ['nullable', 'string', 'max:300'],
                'store_phone' => ['required', 'string', 'regex:/^0\d{2} \d{2} \d{2} \d{2}$/'],
                'neighborhood_id' => ['required', 'integer', Rule::exists(Neighborhood::class, 'id')],
                'address_landmarks' => ['required', 'string', 'min:10', 'max:500'],
                ...self::openingHoursRules(required: true),
                'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            3 => self::documentRules(),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function documentRules(): array
    {
        $rules = ['documents' => ['required', 'array']];

        foreach (DocumentType::requiredFor(Role::Business) as $type) {
            $rules["documents.{$type->value}"] = DocumentService::rules($type);
        }

        foreach (DocumentType::optionalFor(Role::Business) as $type) {
            $rules["documents.{$type->value}"] = DocumentService::rules($type, required: false);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(...array_map(fn (int $step) => self::stepRules($step), self::STEPS));
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
        return self::stepMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function stepMessages(): array
    {
        $messages = [
            ...self::accountDetailsMessages(),
            ...self::openingHoursMessages(),
            'name.required' => 'Indiquez le nom complet du gérant.',
            'store_name.required' => 'Indiquez le nom commercial.',
            'store_name.min' => 'Le nom commercial doit contenir au moins 2 caractères.',
            'store_name.max' => '120 caractères maximum.',
            'category_id.required' => 'Choisissez la catégorie de votre commerce.',
            'category_id.exists' => 'Cette catégorie n’existe pas.',
            'description.max' => '300 caractères maximum.',
            'store_phone.required' => 'Indiquez le téléphone du commerce.',
            'store_phone.regex' => 'Numéro invalide : saisissez un numéro gabonais à 9 chiffres (ex. 074 12 34 56).',
            'neighborhood_id.required' => 'Choisissez le quartier du commerce.',
            'neighborhood_id.exists' => 'Ce quartier n’est pas desservi.',
            'address_landmarks.required' => 'Indiquez l’adresse et des repères pour trouver le commerce.',
            'logo.image' => 'Le logo doit être une image.',
            'logo.mimes' => 'Logo : JPG, PNG ou WebP uniquement.',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
            'documents.required' => 'Ajoutez les documents demandés.',
        ];

        return [...$messages, ...DocumentType::validationMessages()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'mot de passe'];
    }
}
