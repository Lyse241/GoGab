<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Notifier;
use Illuminate\Console\Command;

class NotifyTestCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'gogab:notify-test
        {email : E-mail du destinataire}
        {--type=info : info, success ou warning}';

    /**
     * @var string
     */
    protected $description = 'Envoie une notification in-app de démonstration à un utilisateur';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Aucun utilisateur avec l'e-mail « {$this->argument('email')} ».");

            return self::FAILURE;
        }

        $type = $this->option('type');

        if (! in_array($type, AppNotification::TYPES, true)) {
            $this->error('Type inconnu : utilisez '.implode(', ', AppNotification::TYPES).'.');

            return self::FAILURE;
        }

        Notifier::send(
            $user,
            'Notification de test',
            'Ceci est une notification de démonstration envoyée le '.now()->format('d/m/Y à H:i').'.',
            route('notifications.index'),
            $type,
        );

        $this->info("Notification « {$type} » envoyée à {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
