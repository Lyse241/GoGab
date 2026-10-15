<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PromoType;
use App\Exceptions\PromoException;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Codes promo : vérification d'un code pour un client et un panier, calcul de la réduction,
 * enregistrement de l'utilisation. La réduction est financée par Gogab : elle baisse le total
 * payé par le client, pas ce que reçoivent le commerce (sous-total − commission) et le livreur
 * (frais de livraison) — voir WalletService.
 */
class PromoService
{
    /** Normalise la saisie : « bienvenue 10 » → « BIENVENUE10 ». */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $code));
    }

    /**
     * @return array{promo: PromoCode, discount: int}
     *
     * @throws PromoException code inutilisable (message destiné au client)
     */
    public function evaluate(?string $code, User $client, float $subtotal, int $deliveryFee, ?int $exceptOrderId = null): array
    {
        $normalized = self::normalize($code);
        $promo = $normalized !== '' ? PromoCode::where('code', $normalized)->first() : null;

        if (! $promo || ! $promo->is_active) {
            throw new PromoException('Ce code promo n’existe pas ou n’est plus actif.');
        }
        if ($promo->starts_at && $promo->starts_at->isFuture()) {
            throw new PromoException('Ce code promo n’est pas encore valable.');
        }
        if ($promo->ends_at && $promo->ends_at->isPast()) {
            throw new PromoException('Ce code promo a expiré.');
        }
        if ($subtotal < $promo->min_subtotal) {
            throw new PromoException('Ce code est valable à partir de '.number_format($promo->min_subtotal, 0, ',', ' ').' FCFA d’achats (hors livraison).');
        }
        if ($promo->max_uses !== null && $this->activeRedemptions($promo)->count() >= $promo->max_uses) {
            throw new PromoException('Ce code promo a atteint sa limite d’utilisations.');
        }
        if ($this->activeRedemptions($promo)->where('user_id', $client->id)->count() >= $promo->max_uses_per_user) {
            throw new PromoException('Vous avez déjà utilisé ce code promo.');
        }
        if ($promo->first_order_only && Order::where('client_id', $client->id)
            ->when($exceptOrderId, fn (Builder $query) => $query->whereKeyNot($exceptOrderId))
            ->whereNotIn('status', [OrderStatus::Refused, OrderStatus::Cancelled])
            ->exists()) {
            throw new PromoException('Ce code est réservé à une première commande.');
        }

        $discount = match ($promo->type) {
            PromoType::Percent => (int) floor($subtotal * min($promo->value, 100) / 100),
            PromoType::Fixed => min($promo->value, (int) floor($subtotal)),
            PromoType::FreeDelivery => $deliveryFee,
        };

        return ['promo' => $promo, 'discount' => max(0, min($discount, (int) floor($subtotal) + $deliveryFee))];
    }

    /**
     * Enregistre l'utilisation du code de la commande. À appeler dans la transaction de création :
     * le code est verrouillé puis revérifié, pour que deux commandes simultanées ne dépassent pas
     * la limite d'utilisations.
     *
     * @throws PromoException code devenu inutilisable
     */
    public function redeem(Order $order, User $client): PromoRedemption
    {
        PromoCode::where('code', self::normalize($order->promo_code))->lockForUpdate()->first();

        // La commande vient d'être créée : on l'exclut de la règle « première commande ».
        $result = $this->evaluate($order->promo_code, $client, (float) $order->subtotal, (int) $order->delivery_fee, $order->id);

        return PromoRedemption::create([
            'promo_code_id' => $result['promo']->id,
            'user_id' => $client->id,
            'order_id' => $order->id,
            'discount' => (int) round((float) $order->discount),
        ]);
    }

    /** Utilisations qui comptent : celles des commandes ni refusées ni annulées. */
    private function activeRedemptions(PromoCode $promo): Builder
    {
        return PromoRedemption::where('promo_code_id', $promo->id)
            ->whereHas('order', fn (Builder $query) => $query->whereNotIn('status', [OrderStatus::Refused, OrderStatus::Cancelled]));
    }
}
