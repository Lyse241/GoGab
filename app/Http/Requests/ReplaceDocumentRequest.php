<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Services\DocumentService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Remplacement d'un document depuis « Mon profil » (livreur, entreprise) : un fichier, aux
 * formats et à la taille du type de document. Les règles métier sont dans
 * AccountValidationService::replaceDocument().
 */
class ReplaceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDelivery() || $this->user()->isBusiness();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => DocumentService::rules($this->type())];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $prefix = "documents.{$this->type()->value}.";

        return collect(DocumentType::validationMessages())
            ->filter(fn (string $message, string $key) => str_starts_with($key, $prefix))
            ->mapWithKeys(fn (string $message, string $key) => ['file.'.substr($key, strlen($prefix)) => $message])
            ->all();
    }

    public function type(): DocumentType
    {
        return DocumentType::tryFrom((string) $this->route('type')) ?? abort(404);
    }
}
