<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\OrderTransitionException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moteur des statuts de commande : SEUL endroit où le statut d'une commande change.
 *
 * - place() : création (statut initial en_attente) ;
 * - transition() : tout changement de statut, selon la table TRANSITIONS (rôles autorisés),
 *   avec vérification que l'acteur est concerné, mise à jour en transaction, historique
 *   (order_status_histories) et notifications (Notifier) ;
 * - relaunch() : nouvel envoi de l'annonce aux livreurs (le statut ne change pas).
 *
 * Toute transition interdite lève OrderTransitionException sans rien modifier.
 */
class OrderWorkflow
{
    /**
     * Statut de départ => [statut d'arrivée => rôles autorisés].
     * L'admin peut annuler à tout moment avant la livraison ; l'entreprise peut annuler une
     * annonce restée sans livreur depuis Order::ANNOUNCEMENT_RETRY_MINUTES.
     *
     * @var array<string, array<string, list<Role>>>
     */
    public const TRANSITIONS = [
        'en_attente' => [
            'acceptee' => [Role::Business],
            'refusee' => [Role::Business],
            'annulee' => [Role::Client, Role::Admin],
        ],
        'acceptee' => [
            'en_preparation' => [Role::Business],
            'annulee' => [Role::Admin],
        ],
        'en_preparation' => [
            'en_recherche_livreur' => [Role::Business],
            'annulee' => [Role::Admin],
        ],
        'en_recherche_livreur' => [
            'livreur_assigne' => [Role::Delivery],
            'annulee' => [Role::Business, Role::Admin],
        ],
        'livreur_assigne' => [
            'en_livraison' => [Role::Delivery],
            'annulee' => [Role::Admin],
        ],
        'en_livraison' => [
            'arrive' => [Role::Delivery],
            'annulee' => [Role::Admin],
        ],
        'arrive' => [
            'livree' => [Role::Delivery],
            'annulee' => [Role::Admin],
        ],
    ];

    /**
     * Transitions qui exigent un motif (enregistré dans l'historique et dans orders.cancel_reason).
     */
    private const REASON_REQUIRED = [
        'refusee' => [Role::Business],
        'annulee' => [Role::Business, Role::Admin],
    ];

    /**
     * Crée une commande au statut initial (en_attente), avec ses lignes, son historique et la
     * notification « nouvelle commande » à l'entreprise.
     *
     * @param  array<string, mixed>  $attributes  colonnes de la commande (hors statut)
     * @param  iterable<array{product_id: int, quantity: int, price: mixed}>  $items
     */
    public function place(User $client, array $attributes, iterable $items): Order
    {
        if (! $client->isClient() || ! $client->isApproved()) {
            throw new OrderTransitionException('Seul un client dont le compte est validé peut passer commande.');
        }

        $order = DB::transaction(function () use ($client, $attributes, $items) {
            $order = Order::create([
                ...$attributes,
                'client_id' => $client->id,
                'delivery_id' => null,
                'status' => OrderStatus::Pending,
            ]);
            $order->items()->createMany(collect($items)->all());
            $order->recordStatus(OrderStatus::Pending, $client);

            return $order;
        });

        $order->load(['store.owner', 'client']);
        Notifier::send(
            $order->store->owner,
            "Nouvelle commande {$order->reference}",
            "{$order->client->name} a commandé pour ".$this->money($order->total_price).'. Acceptez-la ou refusez-la.',
            route('business.orders.show', $order),
        );

        return $order;
    }

    /**
     * Fait passer la commande au statut $to au nom de $actor.
     *
     * @throws OrderTransitionException transition interdite (rien n'est modifié)
     */
    public function transition(Order $order, OrderStatus $to, User $actor, ?string $note = null): Order
    {
        $note = filled($note) ? trim($note) : null;

        $order = DB::transaction(function () use ($order, $to, $actor, $note) {
            // Relue et verrouillée : deux actions simultanées ne peuvent pas partir du même statut.
            $fresh = Order::with(['store.neighborhood'])->lockForUpdate()->find($order->id)
                ?? throw new OrderTransitionException('Cette commande n’existe plus.');
            $from = $fresh->status;

            $this->authorize($fresh, $to, $actor, $note);

            $changes = ['status' => $to, 'updated_at' => now()];
            if ($to === OrderStatus::CourierAssigned) {
                $changes['delivery_id'] = $actor->id;
            }
            if ($to === OrderStatus::SearchingCourier) {
                $changes['announced_at'] = now();
                $changes['announcement_count'] = 1;
            }
            if (in_array($to, [OrderStatus::Refused, OrderStatus::Cancelled], true) && $note) {
                $changes['cancel_reason'] = $note;
            }

            // Mise à jour conditionnelle : si le statut (ou le livreur) a changé entre-temps,
            // aucune ligne n'est touchée et la transition échoue.
            $updated = Order::whereKey($fresh->id)
                ->where('status', $from)
                ->when($to === OrderStatus::CourierAssigned, fn (Builder $query) => $query->whereNull('delivery_id'))
                ->update($changes);

            if ($updated !== 1) {
                throw new OrderTransitionException(
                    $to === OrderStatus::CourierAssigned
                        ? "La commande {$fresh->reference} a déjà été prise par un autre livreur."
                        : "La commande {$fresh->reference} a changé entre-temps. Actualisez la page.",
                    $fresh,
                    $to,
                );
            }

            $fresh->refresh();
            $fresh->recordStatus($to, $actor, $note);

            return $fresh;
        });

        $this->notify($order, $to, $actor, $note);

        return $order;
    }

    /**
     * Relance l'annonce d'une commande restée sans livreur : nouvelle notification aux livreurs
     * disponibles de la zone. L'entreprise propriétaire peut relancer une fois le délai
     * Order::ANNOUNCEMENT_RETRY_MINUTES écoulé ; l'admin à tout moment. Le statut ne change pas.
     *
     * @throws OrderTransitionException relance impossible (rien n'est modifié)
     */
    public function relaunch(Order $order, User $actor): Order
    {
        $order = DB::transaction(function () use ($order, $actor) {
            $fresh = Order::with(['store.neighborhood'])->lockForUpdate()->find($order->id)
                ?? throw new OrderTransitionException('Cette commande n’existe plus.');

            $this->authorizeRelaunch($fresh, $actor);

            $updated = Order::whereKey($fresh->id)
                ->where('status', OrderStatus::SearchingCourier)
                ->whereNull('delivery_id')
                ->update([
                    'announced_at' => now(),
                    'announcement_count' => $fresh->announcement_count + 1,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new OrderTransitionException("La commande {$fresh->reference} a changé entre-temps. Actualisez la page.", $fresh);
            }

            return $fresh->refresh();
        });

        $order->load(['store.neighborhood', 'neighborhood']);
        Notifier::send(
            $this->couriersForZone($order->store->neighborhood?->zone),
            'Course toujours disponible',
            $this->announcementMessage($order),
            route('delivery.dashboard'),
            'info',
        );

        return $order;
    }

    /**
     * L'acteur peut-il relancer l'annonce maintenant ?
     */
    public function canRelaunch(Order $order, User $actor): bool
    {
        try {
            $this->authorizeRelaunch($order, $actor);

            return true;
        } catch (OrderTransitionException) {
            return false;
        }
    }

    /**
     * Statuts vers lesquels $actor peut faire passer la commande maintenant (pour l'interface).
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(Order $order, User $actor): array
    {
        return collect(array_keys(self::TRANSITIONS[$order->status->value] ?? []))
            ->map(fn (string $status) => OrderStatus::from($status))
            ->filter(function (OrderStatus $to) use ($order, $actor) {
                try {
                    // Le motif n'est pas connu à ce stade : on ne teste que le rôle, le statut et l'acteur.
                    $this->authorize($order, $to, $actor, note: 'motif', checkReason: false);

                    return true;
                } catch (OrderTransitionException) {
                    return false;
                }
            })
            ->values()
            ->all();
    }

    /**
     * Livreurs qui reçoivent l'annonce : validés, disponibles, non bloqués, rattachés à un
     * quartier de la même zone que le commerce (pas de GPS).
     *
     * @return Collection<int, User>
     */
    public function couriersForZone(?string $zone): Collection
    {
        if (! $zone) {
            return collect();
        }

        return User::query()
            ->where('role', Role::Delivery)
            ->where('account_status', 'approved')
            ->where(fn (Builder $query) => $query->whereNull('blocked_until')->orWhere('blocked_until', '<=', now()))
            ->whereHas('deliveryProfile', fn (Builder $query) => $query
                ->where('is_available', true)
                ->whereHas('baseNeighborhood', fn (Builder $query) => $query->where('zone', $zone)))
            ->get();
    }

    /**
     * Le livreur peut-il prendre les courses de ce commerce (même zone) ?
     */
    public function courierServesStore(User $courier, Order $order): bool
    {
        $courier->loadMissing('deliveryProfile.baseNeighborhood');
        $order->loadMissing('store.neighborhood');
        $zone = $order->store->neighborhood?->zone;

        return $zone !== null
            && $courier->deliveryProfile?->is_available
            && $courier->deliveryProfile->baseNeighborhood?->zone === $zone;
    }

    /**
     * @throws OrderTransitionException
     */
    private function authorizeRelaunch(Order $order, User $actor): void
    {
        if ($order->status !== OrderStatus::SearchingCourier || $order->delivery_id !== null) {
            throw new OrderTransitionException("La commande {$order->reference} n’est plus en recherche de livreur : l’annonce ne peut pas être relancée.", $order);
        }

        if (! $actor->isApproved() || ! in_array($actor->role, [Role::Business, Role::Admin], true)) {
            throw new OrderTransitionException('Vous ne pouvez pas relancer cette annonce.', $order);
        }

        if ($actor->isBusiness()) {
            $order->loadMissing('store');
            if ($order->store?->owner_id !== $actor->id) {
                throw new OrderTransitionException("La commande {$order->reference} ne concerne pas votre commerce.", $order);
            }
            if (! $order->announcementIsStale()) {
                throw new OrderTransitionException($this->retryTooEarlyMessage($order), $order);
            }
        }
    }

    private function retryTooEarlyMessage(Order $order): string
    {
        return 'Laissez '.Order::ANNOUNCEMENT_RETRY_MINUTES.' minutes aux livreurs pour répondre : vous pourrez relancer ou annuler à partir de '
            .$order->announcementRetryAt()?->setTimezone(config('gogab.timezone'))->format('H\hi').'.';
    }

    private function announcementMessage(Order $order): string
    {
        return "{$order->store->name} ({$order->store->neighborhood?->name}) → {$order->neighborhood?->name} · "
            .$this->money($order->total_price).'. Premier livreur à accepter, premier servi.';
    }

    /**
     * @throws OrderTransitionException
     */
    private function authorize(Order $order, OrderStatus $to, User $actor, ?string $note, bool $checkReason = true): void
    {
        $from = $order->status;

        // Course déjà prise : message explicite pour le livreur arrivé second.
        if ($to === OrderStatus::CourierAssigned && $order->delivery_id !== null && $order->delivery_id !== $actor->id) {
            throw new OrderTransitionException("La commande {$order->reference} a déjà été prise par un autre livreur.", $order, $to);
        }

        if ($from->isFinal()) {
            throw new OrderTransitionException("La commande {$order->reference} est déjà « {$from->label()} » : elle ne peut plus changer.", $order, $to);
        }

        $roles = self::TRANSITIONS[$from->value][$to->value] ?? null;
        if ($roles === null) {
            throw new OrderTransitionException("Impossible de passer la commande {$order->reference} de « {$from->label()} » à « {$to->label()} ».", $order, $to);
        }

        if (! in_array($actor->role, $roles, true)) {
            throw new OrderTransitionException("Votre rôle ({$actor->role->label()}) ne permet pas de passer cette commande à « {$to->label()} ».", $order, $to);
        }

        if (! $actor->isApproved()) {
            throw new OrderTransitionException('Votre compte doit être validé pour agir sur une commande.', $order, $to);
        }

        $concerned = match ($actor->role) {
            Role::Admin => true,
            Role::Client => $order->client_id === $actor->id,
            Role::Business => $order->store?->owner_id === $actor->id,
            // Prise de la course : livreur de la zone ; ensuite, uniquement le livreur assigné.
            Role::Delivery => $to === OrderStatus::CourierAssigned
                ? $order->delivery_id === null && $this->courierServesStore($actor, $order)
                : $order->delivery_id === $actor->id,
        };

        if (! $concerned) {
            throw new OrderTransitionException(match ($actor->role) {
                Role::Business => "La commande {$order->reference} ne concerne pas votre commerce.",
                Role::Client => "La commande {$order->reference} n’est pas la vôtre.",
                Role::Delivery => $to === OrderStatus::CourierAssigned
                    ? ($order->delivery_id !== null
                        ? "La commande {$order->reference} a déjà été prise par un autre livreur."
                        : "La commande {$order->reference} n’est pas dans votre zone (ou vous êtes indisponible).")
                    : "La commande {$order->reference} n’est pas assignée à votre compte.",
                default => 'Vous n’êtes pas concerné par cette commande.',
            }, $order, $to);
        }

        // L'entreprise n'annule qu'une annonce restée sans livreur depuis le délai prévu.
        if ($actor->role === Role::Business && $to === OrderStatus::Cancelled && ! $order->announcementIsStale()) {
            throw new OrderTransitionException($this->retryTooEarlyMessage($order), $order, $to);
        }

        if ($checkReason && in_array($actor->role, self::REASON_REQUIRED[$to->value] ?? [], true) && blank($note)) {
            throw new OrderTransitionException("Indiquez le motif pour passer la commande à « {$to->label()} ».", $order, $to);
        }
    }

    /**
     * Notifications de chaque étape (titres et messages en français).
     * Chaque envoi : [destinataires, titre, message, lien, type].
     */
    private function notify(Order $order, OrderStatus $to, User $actor, ?string $note): void
    {
        $order->load(['store.owner', 'store.neighborhood', 'client', 'delivery', 'neighborhood']);
        $ref = $order->reference;
        $store = $order->store->name;
        $client = $order->client;
        $business = $order->store->owner;
        $courier = $order->delivery;
        $clientUrl = route('orders.show', $order);
        $businessUrl = route('business.orders.show', $order);
        $courierUrl = route('delivery.dashboard');
        $reason = $note ? " Motif : {$note}" : '';

        $messages = match ($to) {
            OrderStatus::Accepted => [
                [$client, 'Commande acceptée', "{$store} a accepté votre commande {$ref}.", $clientUrl, 'success'],
            ],
            OrderStatus::Refused => [
                [$client, 'Commande refusée', "{$store} ne peut pas honorer votre commande {$ref}.{$reason}", $clientUrl, 'warning'],
            ],
            OrderStatus::Preparing => [
                [$client, 'Commande en préparation', "{$store} prépare votre commande {$ref}.", $clientUrl, 'info'],
            ],
            OrderStatus::SearchingCourier => [
                [
                    $this->couriersForZone($order->store->neighborhood?->zone),
                    'Nouvelle course disponible',
                    $this->announcementMessage($order),
                    $courierUrl,
                    'info',
                ],
                [$client, 'Recherche d’un livreur', "Votre commande {$ref} est prête : nous cherchons un livreur près de {$store}.", $clientUrl, 'info'],
            ],
            OrderStatus::CourierAssigned => [
                [$client, 'Livreur trouvé', "{$courier?->name} va récupérer votre commande {$ref} chez {$store}.", $clientUrl, 'success'],
                [$business, 'Livreur assigné', "{$courier?->name} vient chercher la commande {$ref}.", $businessUrl, 'info'],
            ],
            OrderStatus::Delivering => [
                [$client, 'Commande récupérée', "{$courier?->name} a récupéré votre commande {$ref} et arrive.", $clientUrl, 'info'],
                [$business, 'Commande récupérée', "{$courier?->name} a récupéré la commande {$ref}.", $businessUrl, 'info'],
            ],
            OrderStatus::Arrived => [
                [$client, 'Votre livreur est arrivé', "{$courier?->name} est arrivé avec votre commande {$ref}.", $clientUrl, 'success'],
            ],
            OrderStatus::Delivered => [
                [$client, 'Commande livrée', "Votre commande {$ref} a été livrée. Merci d’avoir choisi Gogab !", $clientUrl, 'success'],
                [$business, 'Commande livrée', "La commande {$ref} a été livrée au client.", $businessUrl, 'success'],
            ],
            // Parties concernées, sauf l'auteur de l'annulation.
            OrderStatus::Cancelled => collect([[$client, $clientUrl], [$business, $businessUrl], [$courier, $courierUrl]])
                ->filter(fn (array $party) => $party[0] !== null && ! $party[0]->is($actor))
                ->map(fn (array $party) => [$party[0], 'Commande annulée', "La commande {$ref} ({$store}) a été annulée.{$reason}", $party[1], 'warning'])
                ->all(),
            default => [],
        };

        foreach ($messages as [$recipients, $title, $message, $url, $type]) {
            Notifier::send($recipients, $title, $message, $url, $type);
        }
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' FCFA';
    }
}
