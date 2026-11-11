<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\UpdateQueueHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new UpdateQueueHeartbeat)->everyMinute();

// Sem webhook no Tracktainer ainda — polling oficial com filter[updated_after].
Schedule::command('tracking:sync-arrivals')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Backup diário removido em favor do Backup Pré-Importação Ativo
// Schedule::command('app:daily-backup')->dailyAt('04:00');
