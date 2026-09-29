<?php

namespace App\Http\Requests\Admin;

use App\Enums\ModerationReason;
use App\Services\ModerationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Action de modération sur un compte ; les champs dépendent de l'action (nom de la route) :
 * - warn : motif + message (visible par l'utilisateur)
 * - block : motif + message + durée
 * - unblock : motif (+ message facultatif)
 * - flag : motif + note interne ; unflag : note interne facultative
 * L'autorisation est vérifiée par UserPolicy::moderate (middleware "can" puis service).
 */
class ModerateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['message', 'internal_note'] as $field) {
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
        $reason = ['required', Rule::enum(ModerationReason::class)];
        $message = ['required', 'string', 'min:5', 'max:1000'];

        return match ($this->action()) {
            'warn' => ['reason' => $reason, 'message' => $message],
            'block' => [
                'reason' => $reason,
                'message' => $message,
                'duration' => ['required', Rule::in(array_keys(ModerationService::DURATIONS))],
            ],
            'unblock' => ['reason' => $reason, 'message' => ['nullable', 'string', 'max:1000']],
            'flag' => ['reason' => $reason, 'internal_note' => $message],
            'unflag' => ['internal_note' => ['nullable', 'string', 'max:1000']],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Choisissez un motif.',
            'reason.enum' => 'Motif inconnu.',
            'message.required' => 'Écrivez le message qui sera montré à l’utilisateur.',
            'message.min' => 'Message trop court : expliquez ce qui est reproché.',
            'message.max' => '1000 caractères maximum.',
            'duration.required' => 'Choisissez une durée de blocage.',
            'duration.in' => 'Durée inconnue.',
            'internal_note.required' => 'Ajoutez une note interne pour les autres administrateurs.',
            'internal_note.min' => 'Note trop courte.',
            'internal_note.max' => '1000 caractères maximum.',
        ];
    }

    public function reason(): ?ModerationReason
    {
        return ModerationReason::tryFrom((string) $this->validated('reason'));
    }

    /**
     * warn | block | unblock | flag | unflag (dernier segment du nom de la route).
     */
    private function action(): string
    {
        return (string) str($this->route()?->getName())->afterLast('.');
    }
}
