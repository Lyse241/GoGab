<?php

namespace App\Http\Requests\Auth;

use App\Enums\DocumentType;
use App\Services\DocumentService;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Http\Requests\Concerns\ValidatesAccountDetails;
use App\Models\DeliveryProfile;
use App\Models\Neighborhood;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription d'un livreur (/register/delivery), en 3 étapes :
 * 1. informations personnelles, 2. véhicule, 3. documents (liste : DocumentType::requiredFor()).
 *
 * Les règles de chaque étape sont aussi utilisées par la vérification intermédiaire
 * (POST /register/delivery/check), pour signaler une erreur avant l'étape suivante.
 */
class RegisterDeliveryRequest extends FormRequest
{
    use ValidatesAccountDetails;

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
        $vehicle = VehicleType::tryFrom((string) ($input['vehicle_type'] ?? ''));
        $clean = fn ($value) => is_string($value) && trim($value) !== '' ? preg_replace('/\s+/', ' ', trim($value)) : null;

        // Plaque en majuscules ; plaque et permis ignorés pour un vélo.
        $plate = $clean($input['plate_number'] ?? null);

        return [
            ...$input,
            'vehicle_brand' => $clean($input['vehicle_brand'] ?? null),
            'plate_number' => $vehicle?->requiresLicense() && $plate ? mb_strtoupper($plate) : null,
            'license_number' => $vehicle?->requiresLicense() ? $clean($input['license_number'] ?? null) : null,
        ];
    }

    /**
     * Règles d'une étape (1 = personnel, 2 = véhicule, 3 = documents).
     *
     * @param  array<string, mixed>  $input  saisie déjà normalisée
     * @return array<string, mixed>
     */
    public static function stepRules(int $step, array $input): array
    {
        $vehicle = VehicleType::tryFrom((string) ($input['vehicle_type'] ?? ''));
        $needsLicense = $vehicle?->requiresLicense() ?? true;

        return match ($step) {
            1 => self::accountDetailsRules(),
            2 => [
                'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
                'vehicle_brand' => [$needsLicense ? 'required' : 'nullable', 'string', 'max:100'],
                'plate_number' => $needsLicense
                    ? ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 -]{2,18}[A-Z0-9]$/', Rule::unique(DeliveryProfile::class, 'plate_number')]
                    : ['nullable'],
                'license_number' => $needsLicense ? ['required', 'string', 'max:50'] : ['nullable'],
                'base_neighborhood_id' => ['required', 'integer', Rule::exists(Neighborhood::class, 'id')],
            ],
            3 => self::documentRules($vehicle),
            default => [],
        };
    }

    /**
     * Un fichier par document obligatoire ; les autres fichiers éventuels sont ignorés.
     *
     * @return array<string, mixed>
     */
    public static function documentRules(?VehicleType $vehicle): array
    {
        $rules = ['documents' => ['required', 'array']];

        foreach (DocumentType::requiredFor(Role::Delivery, $vehicle) as $type) {
            $rules["documents.{$type->value}"] = DocumentService::rules($type);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $input = $this->all();

        return array_merge(...array_map(fn (int $step) => self::stepRules($step, $input), self::STEPS));
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
            'vehicle_type.required' => 'Choisissez votre type de véhicule.',
            'vehicle_type.enum' => 'Type de véhicule inconnu.',
            'vehicle_brand.required' => 'Indiquez la marque et le modèle du véhicule.',
            'vehicle_brand.max' => '100 caractères maximum.',
            'plate_number.required' => 'Indiquez le numéro de plaque d’immatriculation.',
            'plate_number.regex' => 'Plaque invalide : lettres, chiffres, espaces ou tirets (ex. GA-1234-LBV).',
            'plate_number.unique' => 'Cette plaque est déjà enregistrée pour un autre livreur.',
            'license_number.required' => 'Indiquez le numéro de votre permis de conduire.',
            'base_neighborhood_id.required' => 'Choisissez le quartier où vous comptez travailler.',
            'base_neighborhood_id.exists' => 'Ce quartier n’est pas desservi.',
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
