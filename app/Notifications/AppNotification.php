<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification in-app générique (canal database), affichée dans la cloche et sur /notifications.
 *
 * Envoi synchrone (pas de ShouldQueue) : aucune file d'attente à faire tourner.
 * Passer par App\Services\Notifier plutôt que d'instancier cette classe directement.
 */
class AppNotification extends Notification
{
    use Queueable;

    public const TYPES = ['info', 'success', 'warning'];

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly string $type = 'info',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Contenu enregistré dans notifications.data.
     *
     * @return array{title: string, message: string, url: string|null, type: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'type' => $this->type,
        ];
    }
}
