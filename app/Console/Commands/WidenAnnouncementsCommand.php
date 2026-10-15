<?php

namespace App\Console\Commands;

use App\Services\CourierCoverage;
use Illuminate\Console\Command;

class WidenAnnouncementsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'gogab:widen-announcements';

    /**
     * @var string
     */
    protected $description = 'Élargit à tout le Grand Libreville les annonces restées sans livreur (planifié chaque minute)';

    public function handle(CourierCoverage $coverage): int
    {
        $count = $coverage->widenStaleAnnouncements();
        $this->info($count.' annonce(s) élargie(s).');

        return self::SUCCESS;
    }
}
