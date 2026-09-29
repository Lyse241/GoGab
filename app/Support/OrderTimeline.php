<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;

/**
 * Timeline de suivi côté client, construite depuis order_status_histories.
 *
 * Étapes du parcours normal (libellés client), chacune : done (franchie, avec son heure),
 * current (en cours) ou upcoming (à venir). Une commande refusée ou annulée s'arrête à la
 * dernière étape franchie, suivie de l'étape de fin (refus / annulation, avec le motif).
 */
class OrderTimeline
{
    /** @var array<string, string> */
    public const STEPS = [
        'en_attente' => 'Commande envoyée',
        'acceptee' => 'Acceptée',
        'en_preparation' => 'En préparation',
        'en_recherche_livreur' => 'Recherche d’un livreur',
        'livreur_assigne' => 'Livreur assigné',
        'en_livraison' => 'En route',
        'arrive' => 'Livreur arrivé',
        'livree' => 'Livrée',
    ];

    /**
     * @return list<array{status: string, label: string, state: string, at: string|null, at_iso: string|null, note: string|null}>
     */
    public static function for(Order $order): array
    {
        $order->loadMissing('statusHistories');
        // Heure de la dernière entrée de chaque statut.
        $reached = $order->statusHistories->keyBy(fn (OrderStatusHistory $entry) => $entry->status->value);
        $current = $order->status;
        $interrupted = in_array($current, [OrderStatus::Refused, OrderStatus::Cancelled], true);

        $steps = [];
        foreach (self::STEPS as $status => $label) {
            $entry = $reached->get($status);

            if ($interrupted && ! $entry) {
                break; // la commande n'ira pas plus loin
            }

            $steps[] = self::step($status, $label, match (true) {
                $status === $current->value => 'current',
                $entry !== null => 'done',
                default => 'upcoming',
            }, $entry);
        }

        if ($interrupted) {
            // Les étapes franchies restent « faites » ; l'étape de fin est l'étape courante.
            $steps = array_map(fn (array $step) => [...$step, 'state' => 'done'], $steps);
            $steps[] = self::step(
                $current->value,
                $current === OrderStatus::Refused ? 'Refusée par le commerce' : 'Annulée',
                'current',
                $reached->get($current->value),
                $order->cancel_reason,
            );
        }

        return $steps;
    }

    /**
     * @return array{status: string, label: string, state: string, at: string|null, at_iso: string|null, note: string|null}
     */
    private static function step(string $status, string $label, string $state, ?OrderStatusHistory $entry, ?string $note = null): array
    {
        $at = $entry?->created_at?->setTimezone(config('gogab.timezone'));

        return [
            'status' => $status,
            'label' => $label,
            'state' => $state,
            'at' => $at?->format('H\hi'),
            'at_iso' => $at?->toIso8601String(),
            'note' => $note,
        ];
    }
}
