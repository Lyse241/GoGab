<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

/**
 * Génère les clés VAPID des notifications push. À faire une seule fois par environnement :
 * changer les clés invalide tous les abonnements existants.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'gogab:vapid {--write : Écrire les clés dans le fichier .env} {--force : Remplacer des clés déjà configurées}';

    protected $description = 'Génère les clés VAPID (notifications push)';

    public function handle(): int
    {
        if (filled(config('gogab.push.public_key')) && ! $this->option('force')) {
            $this->error('Des clés VAPID sont déjà configurées. Les remplacer désabonnerait tous les appareils : relancez avec --force si c’est voulu.');

            return self::FAILURE;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $e) {
            $this->error('Impossible de créer les clés : '.$e->getMessage());
            $this->line('Sous Windows (Laragon), OpenSSL ne trouve pas sa configuration. Avant de lancer cette commande, définissez la variable d’environnement :');
            $this->line('  PowerShell : $env:OPENSSL_CONF = "C:\laragon\bin\php\php-8.x\extras\ssl\openssl.cnf"');

            return self::FAILURE;
        }

        $lines = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
        ];

        if (! $this->option('write')) {
            $this->info('Ajoutez ces lignes à votre fichier .env (ou relancez avec --write) :');
            foreach ($lines as $name => $value) {
                $this->line("{$name}={$value}");
            }

            return self::SUCCESS;
        }

        $path = base_path('.env');
        $env = is_file($path) ? file_get_contents($path) : '';
        foreach ($lines as $name => $value) {
            $env = preg_match("/^{$name}=.*$/m", $env)
                ? preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env)."\n{$name}={$value}\n";
        }
        file_put_contents($path, $env);

        $this->info('Clés VAPID écrites dans .env. Redémarrez le serveur (ou php artisan config:clear).');

        return self::SUCCESS;
    }
}
