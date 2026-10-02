<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * « Signaler un problème » depuis une commande. L'accès est vérifié par OrderPolicy::report
 * (middleware can:report,order) ; la personne signalée et les doublons par ReportService.
 */
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reported_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reported_user_id.required' => 'Choisissez la personne concernée.',
            'reported_user_id.integer' => 'Choisissez la personne concernée.',
            'reported_user_id.exists' => 'Choisissez la personne concernée.',
            'reason.required' => 'Choisissez un motif.',
            'reason.enum' => 'Motif inconnu.',
            'description.required' => 'Décrivez ce qui s’est passé.',
            'description.min' => 'Décrivez ce qui s’est passé en quelques mots (10 caractères minimum).',
            'description.max' => '2000 caractères maximum.',
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from($this->validated('reason'));
    }
}
