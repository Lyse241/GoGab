<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Refus d'un document ou d'un compte : le motif est obligatoire (il est montré à l'utilisateur).
 * L'autorisation est vérifiée par la Policy (middleware "can" de la route, puis le service).
 */
class RejectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => is_string($this->reason) ? trim($this->reason) : $this->reason]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Indiquez le motif du refus : il sera montré à l’utilisateur.',
            'reason.min' => 'Motif trop court : expliquez ce qui doit être corrigé.',
            'reason.max' => '1000 caractères maximum.',
        ];
    }
}
