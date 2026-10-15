<?php

namespace App\Services;

use App\Enums\WalletEntryType;
use App\Exceptions\OrderTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

/**
 * Grand livre de Gogab : ce que la plateforme doit à chaque entreprise / livreur, et ce que les
 * livreurs lui doivent (argent encaissé auprès des clients).
 *
 * Modèle actuel (pas de passerelle de paiement) : le livreur encaisse le total de la commande
 * auprès du client, quel que soit le moyen de paiement. Quand un paiement en ligne existera,
 * seule collectedByCourier() change.
 *
 *  - entreprise : + sous-total de la commande, − commission (config gogab.commission_rate) ;
 *  - livreur    : + frais de livraison, − total encaissé ;
 *  - admin      : enregistre les règlements (settle()).
 */
class WalletService
{
    /**
     * Écrit les lignes d'une commande livrée. Idempotent (contrainte d'unicité) : à appeler dans
     * la transaction du passage à « livrée ».
     */
    public function settleDelivery(Order $order): void
    {
        $subtotal = (int) round((float) $order->subtotal);
        $commission = $this->commissionFor($subtotal);
        $owner = $order->store()->first()?->owner_id;

        if ($owner) {
            $this->record($owner, $order, WalletEntryType::Sale, $subtotal, "Vente {$order->reference}");
            $this->record($owner, $order, WalletEntryType::Commission, -$commission, "Commission sur {$order->reference}");
        }

        if ($order->delivery_id) {
            $this->record($order->delivery_id, $order, WalletEntryType::DeliveryEarning, (int) round((float) $order->delivery_fee), "Livraison {$order->reference}");
            if ($this->collectedByCourier($order)) {
                $this->record($order->delivery_id, $order, WalletEntryType::CashCollected, -(int) round((float) $order->total_price), "Encaissé sur {$order->reference}");
            }
        }
    }

    public function commissionFor(int $subtotal): int
    {
        return (int) round($subtotal * (float) config('gogab.commission_rate') / 100);
    }

    public function collectedByCourier(Order $order): bool
    {
        return true;
    }

    /** Solde en FCFA : > 0 Gogab doit à l'utilisateur, < 0 il doit à Gogab. */
    public function balance(User $user): int
    {
        return (int) WalletEntry::where('user_id', $user->id)->sum('amount');
    }

    /**
     * Enregistre un règlement : l'admin a payé l'utilisateur (solde > 0) ou en a reçu l'argent
     * (solde < 0). Le montant (positif) ne peut pas dépasser le solde dû.
     *
     * @throws OrderTransitionException montant invalide
     */
    public function settle(User $admin, User $user, int $amount, ?string $note = null): WalletEntry
    {
        return DB::transaction(function () use ($admin, $user, $amount, $note) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $balance = $this->balance($user);

            if ($balance === 0) {
                throw new OrderTransitionException('Ce compte est à jour : aucun règlement à enregistrer.');
            }
            if ($amount < 1 || $amount > abs($balance)) {
                throw new OrderTransitionException('Le montant doit être compris entre 1 et '.number_format(abs($balance), 0, ',', ' ').' FCFA.');
            }

            $direction = $balance > 0 ? -1 : 1;
            $label = $balance > 0 ? 'Versement de Gogab' : 'Remise d’espèces à Gogab';

            return WalletEntry::create([
                'user_id' => $user->id,
                'created_by' => $admin->id,
                'type' => WalletEntryType::Settlement,
                'amount' => $direction * $amount,
                'description' => $label.(filled($note) ? ' — '.trim($note) : ''),
            ]);
        });
    }

    private function record(int $userId, Order $order, WalletEntryType $type, int $amount, string $description): void
    {
        WalletEntry::firstOrCreate(
            ['order_id' => $order->id, 'user_id' => $userId, 'type' => $type->value],
            ['amount' => $amount, 'description' => $description],
        );
    }
}
