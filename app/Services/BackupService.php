<?php

namespace App\Services;

use App\Exports\DataBackupExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupService
{
    /**
     * Gera um novo backup Excel da base atual
     * @param string $source 'manual' ou 'diario'
     */
    public static function generate($source = 'manual')
    {
        // Aumenta cache e memória para exportações grandes
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutos de fôlego
        try {
            // FAXINA: Remove arquivos antigos de importação para não "sujar" o sistema
            self::cleanupImports();

            $timestamp = Carbon::now()->format('d-m-Y_H-i');
            $fileName = "backups/backup_jrspice_{$source}_{$timestamp}.xlsx";
            
            // Garante que o diretório existe
            if (!Storage::disk('local')->exists('backups')) {
                Storage::disk('local')->makeDirectory('backups');
            }

            // Gera o export
            Excel::store(new DataBackupExport, $fileName, 'local');

            // Rotaciona backups (mantém apenas os 3 mais recentes)
            self::rotate();

            return $fileName;
        } catch (\Throwable $e) {
            Log::error("Erro ao gerar backup: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mantém apenas os 3 arquivos mais recentes no storage
     */
    public static function rotate()
    {
        $files = Storage::disk('local')->files('backups');
        
        // Se temos mais de 3 arquivos, remove os mais antigos
        if (count($files) > 3) {
            // Ordena por data de modificação (mais antigo primeiro)
            usort($files, function($a, $b) {
                return Storage::disk('local')->lastModified($a) <=> Storage::disk('local')->lastModified($b);
            });

            // Quantos arquivos precisamos remover?
            $toRemove = count($files) - 3;
            for ($i = 0; $i < $toRemove; $i++) {
                Storage::disk('local')->delete($files[$i]);
            }
        }
    }

    /**
     * Lista os backups para a UI
     */
    public static function list($limit = 3)
    {
        $files = Storage::disk('local')->files('backups');
        
        // Ordena por data (mais recente primeiro)
        usort($files, function($a, $b) {
            return Storage::disk('local')->lastModified($b) <=> Storage::disk('local')->lastModified($a);
        });

        return array_map(function($file) {
            return [
                'name' => basename($file),
                'path' => $file,
                'date' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file))->format('d/m/Y H:i'),
                'size' => round(Storage::disk('local')->size($file) / 1024, 2) . ' KB'
            ];
        }, array_slice($files, 0, $limit));
    }

    /**
     * Limpa a pasta de imports para não acumular arquivos
     */
    public static function cleanupImports()
    {
        $files = Storage::disk('local')->files('imports');
        foreach ($files as $file) {
            Storage::disk('local')->delete($file);
        }
    }
}
