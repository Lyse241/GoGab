<?php

namespace App\Services;

use App\Contracts\PushTransport;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Notifications push (Web Push) : appelé par Notifier::send, donc chaque notification in-app est
 * aussi poussée sur les appareils abonnés de son destinataire, application fermée comprise.
 * Sans clés VAPID configurées, ou sans abonné, il ne fait rien. Un échec d'envoi n'empêche jamais
 * l'action qui a déclenché la notification.
 */
class PushService
{
    public function __construct(private readonly PushTransport $transport) {}

    public static function isConfigured(): bool
    {
        return filled(config('gogab.push.public_key')) && filled(config('gogab.push.private_key'));
    }

    /**
     * @param  Collection<int, User>  $users
     * @return int nombre d'appareils atteints
     */
    public function send(Collection $users, string $title, string $message, ?string $url = null): int
    {
        if (! self::isConfigured() || $users->isEmpty()) {
            return 0;
        }

        $payload = [
            'title' => $title,
            'body' => $message,
            'url' => $url ?: '/',
        ];

        $delivered = 0;
        PushSubscription::whereIn('user_id', $users->pluck('id'))->get()->each(function (PushSubscription $subscription) use ($payload, &$delivered) {
            $result = $this->transport->send($subscription, $payload);

            if ($result === true) {
                $delivered++;
            } elseif ($result === false) {
                $subscription->delete();
            }
        });

        return $delivered;
    }

    /**
     * Enregistre (ou transfère) l'abonnement d'un appareil. Un même navigateur ne peut appartenir
     * qu'à un compte à la fois : sur un téléphone partagé, le dernier à s'abonner le reçoit.
     */
    public function subscribe(User $user, string $endpoint, string $p256dh, string $auth, ?string $encoding = null, ?string $userAgent = null): PushSubscription
    {
        return PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'p256dh_key' => $p256dh,
                'auth_key' => $auth,
                'content_encoding' => $encoding ?: 'aes128gcm',
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ],
        );
    }

    /** Retire l'abonnement d'un appareil, seulement s'il appartient à cet utilisateur. */
    public function unsubscribe(User $user, string $endpoint): bool
    {
        return PushSubscription::where('user_id', $user->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->delete() > 0;
    }
}
