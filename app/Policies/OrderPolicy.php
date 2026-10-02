<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Services\ReportService;

/**
 * Qui voit quelle commande. Les changements de statut sont contrôlés par OrderWorkflow.
 *
 * - client : ses commandes ;
 * - entreprise : celles de son commerce ;
 * - livreur : celles qu'il a acceptées, et les annonces (recherche de livreur) de sa zone ;
 * - admin : toutes.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if (! $user->isApproved()) {
            // Un client en attente peut revoir ses commandes passées, rien d'autre.
            return $user->isClient() && $order->client_id === $user->id;
        }

        return match ($user->role) {
            Role::Admin => true,
            Role::Client => $order->client_id === $user->id,
            Role::Business => $order->store?->owner_id === $user->id,
            Role::Delivery => $order->delivery_id === $user->id
                || ($order->status === OrderStatus::SearchingCourier
                    && $order->delivery_id === null
                    && app(OrderWorkflow::class)->courierServesStore($user, $order)),
        };
    }

    /**
     * « Signaler un problème » : seulement une partie de la commande (client, entreprise du
     * commerce, livreur assigné), compte validé.
     */
    public function report(User $user, Order $order): bool
    {
        return $user->isApproved() && app(ReportService::class)->parties($order, $user)->isNotEmpty();
    }
}
