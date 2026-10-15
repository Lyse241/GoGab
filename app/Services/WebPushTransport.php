<?php

namespace App\Services;

use App\Contracts\PushTransport;
use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Envoi réel par Web Push (clés VAPID de config/gogab.php). Délai court : l'envoi est synchrone
 * (pas de worker), il ne doit jamais ralentir une action de l'utilisateur.
 */
class WebPushTransport implements PushTransport
{
    private const TIMEOUT_SECONDS = 4;

    public function send(PushSubscription $subscription, array $payload): ?bool
    {
        try {
            // Windows / Laragon : la variable d'environnement OPENSSL_CONF doit exister au démarrage
            // du serveur (putenv n'a aucun effet sur OpenSSL), sinon la clé de chiffrement de
            // l'envoi ne peut pas être créée. Inutile sous Linux.
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('gogab.push.subject'),
                    'publicKey' => config('gogab.push.public_key'),
                    'privateKey' => config('gogab.push.private_key'),
                ],
            ], ['TTL' => 3600, 'urgency' => 'high'], new Client([
                'timeout' => self::TIMEOUT_SECONDS,
                'connect_timeout' => self::TIMEOUT_SECONDS,
            ]));

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh_key,
                    'authToken' => $subscription->auth_key,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );

            if ($report->isSuccess()) {
                return true;
            }

            // 404 / 410 : l'utilisateur a désinstallé l'application ou retiré l'autorisation.
            if ($report->isSubscriptionExpired()) {
                return false;
            }

            Log::warning('Web Push refusé : '.$report->getReason(), ['subscription' => $subscription->id]);

            return null;
        } catch (Throwable $e) {
            Log::warning('Web Push impossible : '.$e->getMessage(), ['subscription' => $subscription->id]);

            return null;
        }
    }
}
