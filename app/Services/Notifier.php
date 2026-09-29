<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Point d'entrée unique des notifications in-app.
 *
 *     Notifier::send($client, 'Commande acceptée', 'Chez Maman Ngoye prépare votre commande.', route('orders.show', $order), 'success');
 *     Notifier::send(User::where('role', 'admin')->get(), 'Nouveau livreur', 'Un compte attend votre validation.');
 */
class Notifier
{
    /**
     * @param  User|iterable<User|null>|null  $users  un utilisateur ou une collection (les null sont ignorés)
     * @param  string  $type  info | success | warning
     * @return int nombre de destinataires notifiés
     */
    public static function send(
        User|iterable|null $users,
        string $title,
        string $message,
        ?string $url = null,
        string $type = 'info',
    ): int {
        if (! in_array($type, AppNotification::TYPES, true)) {
            throw new InvalidArgumentException("Type de notification inconnu : {$type} (attendu : ".implode(', ', AppNotification::TYPES).').');
        }

        $recipients = self::recipients($users);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AppNotification($title, $message, self::relativeUrl($url), $type));
        }

        return $recipients->count();
    }

    /**
     * Notifie tous les administrateurs validés.
     */
    public static function admins(string $title, string $message, ?string $url = null, string $type = 'info'): int
    {
        $admins = User::where('role', Role::Admin)
            ->where('account_status', AccountStatus::Approved)
            ->get();

        return self::send($admins, $title, $message, $url, $type);
    }

    /**
     * Les liens internes sont enregistrés en chemin relatif ("/orders/12") : ils restent valides
     * quel que soit l'hôte (APP_URL en ligne de commande, 127.0.0.1 en local, domaine en production).
     */
    private static function relativeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! isset($parts['host']) || $parts['host'] === $appHost) {
            $path = '/'.ltrim($parts['path'] ?? '', '/');

            return $path
                .(isset($parts['query']) ? '?'.$parts['query'] : '')
                .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }

        return $url;
    }

    /**
     * @return Collection<int, User>
     */
    private static function recipients(User|iterable|null $users): Collection
    {
        if ($users instanceof User) {
            return collect([$users]);
        }

        return collect($users ?? [])
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values();
    }
}
