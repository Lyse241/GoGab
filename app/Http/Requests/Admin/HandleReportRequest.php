<?php

namespace App\Http\Requests\Admin;

use App\Enums\ModerationReason;
use App\Services\ModerationService;
use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Traitement d'un signalement par un admin (ReportPolicy::handle via le middleware de la route) :
 * - warn : message à la personne signalée (+ motif de modération facultatif)
 * - block : message + durée
 * - dismiss / resolve : note admin facultative
 */
class HandleReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['message', 'admin_note'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sanction = in_array($this->input('action'), ['warn', 'block'], true);

        return [
            'action' => ['required', Rule::in(ReportService::ACTIONS)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'moderation_reason' => ['nullable', Rule::enum(ModerationReason::class)],
            'message' => $sanction ? ['required', 'string', 'min:5', 'max:1000'] : ['nullable'],
            'duration' => $this->input('action') === 'block'
                ? ['required', Rule::in(array_keys(ModerationService::DURATIONS))]
                : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Choisissez une action.',
            'action.in' => 'Action inconnue.',
            'admin_note.max' => '1000 caractères maximum.',
            'moderation_reason.enum' => 'Motif inconnu.',
            'message.required' => 'Écrivez le message qui sera montré à la personne signalée.',
            'message.min' => 'Message trop court : expliquez ce qui est reproché.',
            'message.max' => '1000 caractères maximum.',
            'duration.required' => 'Choisissez une durée de blocage.',
            'duration.in' => 'Durée inconnue.',
        ];
    }

    /**
     * @return array{message: ?string, duration: ?string, moderation_reason: ?ModerationReason}
     */
    public function sanction(): array
    {
        return [
            'message' => $this->validated('message'),
            'duration' => $this->validated('duration'),
            'moderation_reason' => ModerationReason::tryFrom((string) $this->validated('moderation_reason')),
        ];
    }
}
