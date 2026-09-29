<?php

namespace App\Console\Commands;

use App\Services\ModerationService;
use Illuminate\Console\Command;

class UnblockExpiredCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'gogab:unblock-expired';

    /**
     * @var string
     */
    protected $description = 'Débloque les comptes dont le blocage temporaire est terminé (avec notification)';

    public function handle(ModerationService $moderation): int
    {
        $count = $moderation->unblockExpired();

        $this->info($count > 0 ? "{$count} compte(s) débloqué(s)." : 'Aucun blocage expiré.');

        return self::SUCCESS;
    }
}
