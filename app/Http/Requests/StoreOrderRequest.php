<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Product;
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
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * Vérifie que tous les produits existent encore et viennent d'une seule boutique.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = collect($this->input('items'))->pluck('product_id');
                $products = Product::whereIn('id', $ids)->get(['id', 'store_id']);

                if ($products->count() !== $ids->count()) {
                    $validator->errors()->add('items', "Certains produits de votre panier ne sont plus disponibles. Mettez à jour votre panier.");
                } elseif ($products->pluck('store_id')->unique()->count() > 1) {
                    $validator->errors()->add('items', 'Une commande ne peut concerner qu\'une seule boutique.');
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
            'payment_method.in' => "Ce mode de paiement n'est pas proposé.",
            'items.required' => 'Votre panier est vide.',
            'items.min' => 'Votre panier est vide.',
            'items.max' => 'Votre panier contient trop d\'articles différents (50 maximum).',
            'items.*' => 'Votre panier contient des articles invalides. Mettez à jour votre panier.',
        ];
    }
}
