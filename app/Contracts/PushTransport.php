<?php

namespace App\Contracts;

use App\Models\PushSubscription;

/**
 * Envoi d'une notification push à un abonnement. Interface séparée pour que les tests n'appellent
 * jamais les serveurs de Google / Mozilla / Apple.
 */
interface PushTransport
{
    /**
     * @param  array{title: string, body: string, url: string|null, tag?: string}  $payload
     * @return bool|null true : envoyée ; false : abonnement expiré ou révoqué (à supprimer) ;
     *                   null : échec passager (réseau, service indisponible), on garde l'abonnement
     */
    public function send(PushSubscription $subscription, array $payload): ?bool;
}
