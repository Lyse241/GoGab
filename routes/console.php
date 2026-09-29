<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tâches planifiées : lancer `php artisan schedule:run` chaque minute (cron) ou `php artisan schedule:work` en local.
Schedule::command('gogab:unblock-expired')->everyMinute()->withoutOverlapping();
