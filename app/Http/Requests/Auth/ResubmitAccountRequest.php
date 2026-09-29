<?php

namespace App\Http\Requests\Auth;

use App\Enums\DocumentType;
use App\Services\DocumentService;
use App\Enums\VehicleType;
use App\Http\Requests\Concerns\ValidatesOpeningHours;
use App\Models\Category;
use App\Models\DeliveryProfile;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\AccountValidationService;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Correction d'un dossier refusé : informations selon le rôle + documents renvoyés.
 * Les documents obligatoires refusés ou absents doivent être renvoyés ; les autres peuvent l'être.
 * L'autorisation (compte refusé, propriétaire) est vérifiée par UserPolicy::resubmit.
 */
class ResubmitAccountRequest extends FormRequest
{
    use ValidatesOpeningHours;

    public function authorize(): bool
    {
        return $this->user()->can('resubmit', $this->user());
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) && trim($value) !== '' ? preg_replace('/\s+/', ' ', trim($value)) : null;

        $this->merge([
            'name' => $clean($this->input('name')),
            'phone' => is_string($this->input('phone')) ? PhoneNumber::normalize($this->input('phone')) : null,
            'address_landmarks' => $clean($this->input('address_landmarks')),
            'vehicle_brand' => $clean($this->input('vehicle_brand')),
            'plate_number' => ($plate = $clean($this->input('plate_number'))) ? mb_strtoupper($plate) : null,
            'license_number' => $clean($this->input('license_number')),
            'store_name' => $clean($this->input('store_name')),
            'description' => $clean($this->input('description')),
            'store_phone' => is_string($this->input('store_phone')) ? PhoneNumber::normalize($this->input('store_phone')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $validation = app(AccountValidationService::class);

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^0\d{2} \d{2} \d{2} \d{2}$/', Rule::unique(User::class, 'phone')->ignore($user->id)],
            'neighborhood_id' => ['required', 'integer', Rule::exists(Neighborhood::class, 'id')],
            'address_landmarks' => ['required', 'string', 'min:10', 'max:500'],
            'documents' => ['nullable', 'array'],
        ];

        if ($user->isDelivery() && $user->deliveryProfile) {
            $needsLicense = $user->deliveryProfile->vehicle_type !== VehicleType::Bicycle;
            $rules += [
                'vehicle_brand' => [$needsLicense ? 'required' : 'nullable', 'string', 'max:100'],
                'plate_number' => $needsLicense
                    ? ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 -]{2,18}[A-Z0-9]$/', Rule::unique(DeliveryProfile::class, 'plate_number')->ignore($user->deliveryProfile->id)]
                    : ['nullable'],
                'license_number' => $needsLicense ? ['required', 'string', 'max:50'] : ['nullable'],
                'base_neighborhood_id' => ['required', 'integer', Rule::exists(Neighborhood::class, 'id')],
            ];
        }

        if ($user->isBusiness() && $user->store) {
            $rules += [
                'store_name' => ['required', 'string', 'min:2', 'max:120'],
                'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
                'description' => ['nullable', 'string', 'max:300'],
                'store_phone' => ['required', 'string', 'regex:/^0\d{2} \d{2} \d{2} \d{2}$/'],
                ...self::openingHoursRules(required: true),
            ];
        }

        // Documents : obligatoires à renvoyer (refusés ou absents), les autres au choix.
        $mustResend = $validation->documentsToResend($user);
        foreach ($validation->resendableDocuments($user) as $type) {
            $rules["documents.{$type->value}"] = DocumentService::rules($type, required: in_array($type, $mustResend, true));
        }

        return $rules;
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->user()->isBusiness()
            ? $this->validateOpeningHoursConsistency($validator)
            : null];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...RegisterBusinessRequest::stepMessages(),
            ...RegisterDeliveryRequest::stepMessages(),
            'name.required' => 'Indiquez votre nom complet.',
            'neighborhood_id.required' => 'Choisissez le quartier.',
            'address_landmarks.required' => 'Indiquez l’adresse et des repères.',
        ];
    }
}
