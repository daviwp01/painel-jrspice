<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class DailyBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera um backup diário Excel da base Jrspice e mantém os 3 mais recentes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando backup diário...');
        
        $file = BackupService::generate('diario');
        
        if ($file) {
            $this->info("Backup gerado com sucesso: {$file}");
            Log::info("Backup diário gerado: {$file}");
        } else {
            $this->error('Falha ao gerar o backup diário.');
            Log::error('Falha ao gerar o backup diário via console.');
        }
    }
}
