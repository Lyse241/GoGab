<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Store;
use App\Services\OrderPricing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation d'une commande (POST /orders) : le panier d'UN commerce (`store_id`).
 * Tout est revérifié au moment de l'envoi : commerce visible et ouvert, produits disponibles et
 * appartenant à ce commerce, montant remis en espèces ≥ total recalculé depuis la base.
 */
class StoreOrderRequest extends FormRequest
{
    private ?Store $store = null;

    /** @var array{items: list<array<string, mixed>>, subtotal: float, delivery_fee: int, total: float}|null */
    private ?array $quote = null;

    /**
     * L'accès (client validé) est déjà contrôlé par les middlewares de la route.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cash = $this->input('cash_given');

        $this->merge([
            'client_note' => is_string($this->input('client_note')) && trim($this->input('client_note')) !== '' ? trim($this->input('client_note')) : null,
            // « 10 000 » → 10000.
            'cash_given' => is_string($cash) ? preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $cash) : $cash,
        ]);
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
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'neighborhood_id' => ['required', 'integer', 'exists:neighborhoods,id'],
            'address_landmarks' => ['required', 'string', 'min:10', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // Montant remis en espèces (monnaie du livreur) : obligatoire pour le paiement à la
            // livraison, ignoré pour le Mobile Money.
            'cash_given' => ['exclude_unless:payment_method,'.PaymentMethod::Cash->value, 'required', 'integer', 'min:1', 'max:10000000'],
            'client_note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $store = Store::with(['openingHours', 'owner:id,account_status'])->find($this->integer('store_id'));

                if (! $store->isVisible()) {
                    $validator->errors()->add('items', 'Ce commerce n’est plus disponible sur Gogab.');

                    return;
                }

                // Vérifié au moment de l'envoi, même si la page était restée ouverte.
                if (! $store->isOpenNow()) {
                    $validator->errors()->add('items', "{$store->name} : {$store->openingStatus()['status_label']}. Votre panier est conservé : vous pourrez commander à la réouverture.");

                    return;
                }

                // Tous les produits : du commerce du checkout, existants et disponibles.
                $ids = collect($this->input('items'))->pluck('product_id');
                if (app(OrderPricing::class)->availableProducts($store, $ids)->count() !== $ids->count()) {
                    $validator->errors()->add('items', 'Certains produits de votre panier ne sont plus disponibles chez ce commerce. Mettez à jour votre panier.');

                    return;
                }

                $quote = app(OrderPricing::class)->quote($store, $this->input('items'));

                if ($this->input('payment_method') === PaymentMethod::Cash->value && (float) $this->input('cash_given') < $quote['total']) {
                    $validator->errors()->add('cash_given', 'Le montant remis doit couvrir le total de la commande ('.number_format($quote['total'], 0, ',', ' ').' FCFA).');

                    return;
                }

                $this->store = $store;
                $this->quote = $quote;
            },
        ];
    }

    public function store(): Store
    {
        return $this->store;
    }

    /**
     * Prix recalculés depuis la base (lignes à prix figés, sous-total, frais, total).
     *
     * @return array{items: list<array<string, mixed>>, subtotal: float, delivery_fee: int, total: float}
     */
    public function quote(): array
    {
        return $this->quote;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Commerce introuvable : rouvrez votre panier.',
            'store_id.exists' => 'Ce commerce n’existe plus.',
            'neighborhood_id.required' => 'Choisissez votre quartier de livraison.',
            'neighborhood_id.exists' => "Ce quartier n'est pas desservi.",
            'address_landmarks.required' => 'Indiquez des repères pour que le livreur trouve votre adresse.',
            'address_landmarks.min' => 'Soyez un peu plus précis (10 caractères minimum).',
            'address_landmarks.max' => '500 caractères maximum.',
            'payment_method.required' => 'Choisissez un mode de paiement.',
            'payment_method.enum' => "Ce mode de paiement n'est pas proposé.",
            'cash_given.required' => 'Indiquez avec quel montant vous paierez, pour que le livreur prévoie la monnaie.',
            'cash_given.integer' => 'Indiquez un montant en FCFA, sans centimes.',
            'cash_given.min' => 'Indiquez un montant en FCFA.',
            'cash_given.max' => 'Ce montant est trop élevé.',
            'client_note.max' => '500 caractères maximum.',
            'items.required' => 'Votre panier est vide.',
            'items.min' => 'Votre panier est vide.',
            'items.max' => 'Votre panier contient trop d\'articles différents (50 maximum).',
            'items.*' => 'Votre panier contient des articles invalides. Mettez à jour votre panier.',
        ];
    }
}
