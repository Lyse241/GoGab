<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    /**
     * L'accès (client connecté) est déjà contrôlé par les middlewares de la route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le panier n'envoie que des identifiants et des quantités :
     * les prix sont toujours relus en base par le serveur.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'neighborhood_id' => ['required', 'integer', 'exists:neighborhoods,id'],
            'address_landmarks' => ['required', 'string', 'min:10', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // Montant remis en espèces, pour que le livreur prévoie la monnaie.
            'cash_given' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'prohibited_unless:payment_method,'.PaymentMethod::Cash->value],
            'client_note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * Vérifie que tous les produits existent encore, sont disponibles et viennent d'une seule boutique.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = collect($this->input('items'))->pluck('product_id');
                $products = Product::whereIn('id', $ids)->where('is_available', true)->get(['id', 'store_id']);

                if ($products->count() !== $ids->count()) {
                    $validator->errors()->add('items', "Certains produits de votre panier ne sont plus disponibles. Mettez à jour votre panier.");
                } elseif ($products->pluck('store_id')->unique()->count() > 1) {
                    $validator->errors()->add('items', 'Une commande ne peut concerner qu\'une seule boutique.');
                } else {
                    // Vérifié au moment de l'envoi, même si la page était restée ouverte.
                    $store = Store::with('openingHours')->find($products->first()->store_id);

                    if (! $store->isOpenNow()) {
                        $detail = $store->statusMessage()['detail'];
                        $validator->errors()->add('items', "{$store->name} est fermé pour le moment".($detail ? " ({$detail})" : '').'. Votre panier est conservé : vous pourrez commander à la réouverture.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'neighborhood_id.required' => 'Choisissez votre quartier de livraison.',
            'neighborhood_id.exists' => "Ce quartier n'est pas desservi.",
            'address_landmarks.required' => 'Indiquez des repères pour que le livreur trouve votre adresse.',
            'address_landmarks.min' => 'Soyez un peu plus précis (10 caractères minimum).',
            'address_landmarks.max' => '500 caractères maximum.',
            'payment_method.required' => 'Choisissez un mode de paiement.',
            'payment_method.enum' => "Ce mode de paiement n'est pas proposé.",
            'cash_given.numeric' => 'Indiquez un montant en FCFA.',
            'cash_given.min' => 'Le montant ne peut pas être négatif.',
            'cash_given.max' => 'Ce montant est trop élevé.',
            'cash_given.prohibited_unless' => 'Le montant en espèces ne concerne que le paiement en espèces.',
            'client_note.max' => '500 caractères maximum.',
            'items.required' => 'Votre panier est vide.',
            'items.min' => 'Votre panier est vide.',
            'items.max' => 'Votre panier contient trop d\'articles différents (50 maximum).',
            'items.*' => 'Votre panier contient des articles invalides. Mettez à jour votre panier.',
        ];
    }
}
