<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:check-expiring')->daily();

// UR-06: backup harian terenkripsi + health check dengan alert log.
Schedule::command('bos:backup-mysql')->dailyAt('02:30');
Schedule::command('bos:health')->everyFiveMinutes();
