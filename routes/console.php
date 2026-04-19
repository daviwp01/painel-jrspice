<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

use App\Jobs\UpdateQueueHeartbeat;

Schedule::job(new UpdateQueueHeartbeat)->everyMinute();

// Backup diário removido em favor do Backup Pré-Importação Ativo
// Schedule::command('app:daily-backup')->dailyAt('04:00');
