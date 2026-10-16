<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\Contracts\Spreadsheet\ContractSpreadsheetImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportContractsFromSpreadsheet extends Command
{
    protected $signature = 'contracts:import
        {path? : Caminho do arquivo .xlsx}
        {--dry-run : Executa tudo e desfaz ao final, apenas para validação}
        {--clear : Remove os contratos importados anteriormente antes de importar}';

    protected $description = 'Importa os contratos da aba "Painel de info Vendas" da planilha para o sistema';

    public function handle(ContractSpreadsheetImporter $importer): int
    {
        $path = $this->argument('path') ?? base_path('contratos/Painel de dados ATUAL.xlsx');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        if ($this->option('clear') && ! $this->option('dry-run')) {
            $removed = Contract::query()->whereNotNull('source_row')->delete();
            $this->warn("{$removed} contratos importados anteriormente removidos.");
        }

        $this->info('Importando contratos de: '.$path);
        $this->newLine();

        try {
            $report = $importer->import($path, (bool) $this->option('dry-run'));
        } catch (\Throwable $exception) {
            $this->error('Falha na importação: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($report);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('DRY-RUN: nenhuma alteração foi persistida.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report): void
    {
        $this->table(
            ['Métrica', 'Qtde'],
            [
                ['Contratos criados', $report['contracts_created']],
                ['Contratos atualizados', $report['contracts_updated']],
                ['Itens criados', $report['items_created']],
                ['Itens atualizados', $report['items_updated']],
                ['Itens removidos', $report['items_removed']],
                ['Produtos criados', $report['products_created']],
                ['Clientes (exportadores/importadores) criados', $report['clients_created']],
            ]
        );

        $this->line('Total de contratos na base: '.DB::table('contracts')->count());
        $this->line('Total de itens na base: '.DB::table('contract_items')->count());
        $this->newLine();

        $residue = $report['residue_rows'] ?? [];
        if ($residue !== []) {
            $this->newLine();
            $this->warn(sprintf(
                'Linhas de resíduo de fórmula ignoradas: %d (linhas %d–%d). Não contêm dados de negócio.',
                count($residue),
                min($residue),
                max($residue)
            ));
        }

        $this->renderList('Linhas descartadas (sem dados úteis)', array_map(
            static fn (array $row) => "Linha {$row['row']}: {$row['reason']}",
            $report['discarded_rows']
        ));

        $this->renderList('Avisos de importação', array_values($report['warnings']));
        $this->renderList('Vendedores sem usuário correspondente (vinculo ficou vazio)', array_values(array_unique($report['unmatched_sellers'])));

        foreach ($report['merged'] as $label => $entries) {
            $this->renderList("Grafias unificadas em {$label}", $entries);
        }

        foreach ($report['possible_variants'] ?? [] as $label => $entries) {
            $this->renderList("Possíveis variantes em {$label} (revisar manualmente)", $entries);
        }

        foreach ($report['flagged'] as $label => $entries) {
            $this->renderList("Valores revisados em {$label}", $entries);
        }

        $this->newLine();
        $this->info('Relatório completo salvo em storage/logs/contracts-import-report.json');
        file_put_contents(
            storage_path('logs/contracts-import-report.json'),
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param  array<int, string>  $entries
     */
    private function renderList(string $title, array $entries): void
    {
        $entries = array_values(array_filter($entries));

        if ($entries === []) {
            return;
        }

        $this->newLine();
        $this->warn("{$title} (".count($entries).')');

        foreach (array_slice($entries, 0, 40) as $entry) {
            $this->line('  - '.$entry);
        }

        if (count($entries) > 40) {
            $this->line('  ... e mais '.(count($entries) - 40).' itens.');
        }
    }
}
