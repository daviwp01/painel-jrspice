<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Support\ProductHeaderMap;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ChunkReadFilter implements IReadFilter
{
    private $startRow = 0;

    private $endRow = 0;

    public function setRows($startRow, $chunkSize)
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        // Sempre ler a primeira linha (cabeçalhos) para mapeamento
        if ($row == 1 || ($row >= $this->startRow && $row < $this->endRow)) {
            return true;
        }

        return false;
    }
}

class ProcessDataImport implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    protected $jobId;

    public $timeout = 3600;

    public function __construct($filePath, $jobId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        try {
            $tmpPath = '/tmp/import_final_'.uniqid().'.xlsx';

            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($this->filePath)) {
                throw new \Exception('Erro: Arquivo não encontrado no Storage.');
            }

            $fileContent = \Illuminate\Support\Facades\Storage::disk('local')->get($this->filePath);
            file_put_contents($tmpPath, $fileContent);

            $reader = new Xlsx;
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $info = $reader->listWorksheetInfo($tmpPath);
            $totalRows = $info[0]['totalRows'];
            $chunkSize = 300;

            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => 0, 'total' => $totalRows, 'status' => 'processing', 'percentage' => 1], 600);
            }

            // --- MAPEAMENTO DE COLUNAS (Fidelidade Dinâmica) ---
            $spreadsheetHeader = $reader->load($tmpPath);
            $sheet = $spreadsheetHeader->getActiveSheet();
            $rawHeaders = $sheet->toArray(null, true, true, true)[1] ?? [];
            $headers = array_map(function ($h) {
                return trim(mb_strtolower($h));
            }, $rawHeaders);

            $colMap = ProductHeaderMap::fromLoweredHeaders($headers);

            Log::info('Mapeamento de colunas detectado:', $colMap);

            $spreadsheetHeader->disconnectWorksheets();
            unset($spreadsheetHeader, $sheet, $rawHeaders, $headers);

            // --- PRÉ-CARREGAMENTO (TURBO) ---
            $countryCache = Country::all()->pluck('id', 'name')->toArray();
            $supplierCache = Supplier::all()->pluck('id', 'name')->toArray();
            $productCache = Product::all()->mapWithKeys(function ($p) {
                return [$p->country_id.'_'.$p->name => $p->id];
            })->toArray();

            $filter = new ChunkReadFilter;
            $reader->setReadFilter($filter);
            $processedCount = 0;

            // Começa da linha 2 (Dados reais)
            for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
                if ($this->batch() && $this->batch()->cancelled()) {
                    Log::warning("Importação Cancelada pelo Usuário: {$this->jobId}");
                    @unlink($tmpPath);
                    @unlink($this->filePath); // AUTO-LIMPEZA: Remove mesmo se cancelado

                    return;
                }

                $filter->setRows($startRow, $chunkSize);
                $spreadsheet = $reader->load($tmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray(null, true, true, true);

                foreach ($rows as $rowIndex => $row) {
                    if ($rowIndex == 1) {
                        continue;
                    } // Pula cabeçalho se estiver no chunk
                    if ($rowIndex < $startRow || $rowIndex >= $startRow + $chunkSize) {
                        continue;
                    }
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $processedCount++;

                    $productName = trim($row[$colMap['product']] ?? '');
                    $harvestMonth = trim($row[$colMap['safra']] ?? '');
                    $countryName = trim($row[$colMap['country']] ?? '');
                    $supplierName = trim($row[$colMap['supplier']] ?? '');
                    $dateValue = $row[$colMap['date']] ?? null;
                    $rawPrice = $row[$colMap['price']] ?? 0;

                    if (! $productName || ! $countryName) {
                        continue;
                    }

                    // Normaliza a safra antes de salvar
                    $harvestMonth = $this->normalizeHarvest($harvestMonth);

                    $rawPrice = $row[$colMap['price'] ?? ''] ?? null;
                    // Remove todos os tipos de espaços invisíveis (incluindo non-breaking space do Excel)
                    $cleanPrice = preg_replace('/[\p{Z}\s]/u', '', (string) $rawPrice);
                    $priceValue = ($cleanPrice !== '') ? floatval(str_replace(',', '.', str_replace('.', '', $cleanPrice))) : 0;

                    // Validação Absoluta: Ignora se for nulo, vazio (mesmo com espaços invisíveis), zero ou negativo
                    if ($rawPrice === null || $cleanPrice === '' || $priceValue <= 0) {
                        Log::warning("IMPORT_BLOCKER_V4: Linha {$rowIndex} descartada. Motivo: Preço ausente ou zero. Produto: '{$productName}'");

                        continue;
                    }

                    // 1. País (Turbo)
                    if (! isset($countryCache[$countryName])) {
                        $countryCache[$countryName] = Country::firstOrCreate(['name' => $countryName])->id;
                    }
                    $countryId = $countryCache[$countryName];

                    // 2. Produto (Atualiza a Safra APENAS se houver valor na planilha)
                    $productData = [];
                    if (! empty($harvestMonth)) {
                        $productData['harvest_month'] = $harvestMonth;
                    }

                    $nameEn = $this->optionalCell($row, $colMap['product_en'] ?? null);
                    $nameEs = $this->optionalCell($row, $colMap['product_es'] ?? null);
                    if ($nameEn !== null) {
                        $productData['name_en'] = $nameEn;
                    }
                    if ($nameEs !== null) {
                        $productData['name_es'] = $nameEs;
                    }

                    $product = Product::updateOrCreate(
                        ['name' => $productName, 'country_id' => $countryId],
                        $productData
                    );
                    $productId = $product->id;
                    $productKey = $countryId.'_'.$productName;
                    $productCache[$productKey] = $productId;

                    // 3. Fornecedor (Turbo)
                    $supplierId = null;
                    if ($supplierName) {
                        if (! isset($supplierCache[$supplierName])) {
                            $supplierCache[$supplierName] = Supplier::firstOrCreate(['name' => $supplierName])->id;
                        }
                        $supplierId = $supplierCache[$supplierName];
                    }

                    // 4. Gravação de Segurança (updateOrCreate)
                    $date = $this->transformDate($dateValue);
                    if ($date) {
                        ProductPrice::updateOrCreate(
                            [
                                'product_id' => $productId,
                                'supplier_id' => $supplierId,
                                'date' => $date->format('Y-m-d'),
                            ],
                            [
                                'price' => $priceValue,
                            ]
                        );
                    }
                }

                if ($this->jobId) {
                    Cache::put("import_progress_{$this->jobId}", [
                        'current' => $processedCount,
                        'total' => $totalRows,
                        'status' => 'processing',
                        'percentage' => round(($processedCount / $totalRows) * 100),
                    ], 600);
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet, $rows);
                gc_collect_cycles();
            }

            Cache::forget('last_prices_update');
            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => $totalRows, 'total' => $totalRows, 'status' => 'completed', 'percentage' => 100], 600);
            }
            @unlink($tmpPath);
            \Illuminate\Support\Facades\Storage::disk('local')->delete($this->filePath); // Limpa original

        } catch (\Throwable $e) {
            Log::error('ERRO NA IMPORTAÇÃO: '.$e->getMessage().' Trace: '.$e->getTraceAsString());
            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => 0, 'total' => 0, 'status' => 'failed', 'message' => $e->getMessage(), 'percentage' => 0], 600);
            }
            if (isset($tmpPath) && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            if (isset($this->filePath)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($this->filePath);
            } // Limpa em caso de erro
            throw $e;
        }
    }

    /**
     * @param  int|string|null  $column  key as produced by toArray(returnCellRef: true) — int index or column letter
     */
    private function optionalCell(array $row, int|string|null $column): ?string
    {
        if ($column === null) {
            return null;
        }

        $value = $row[$column] ?? null;
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function normalizeHarvest($value)
    {
        if (! $value) {
            return null;
        }
        $value = trim($value);

        $monthsMap = [
            '01' => 'JANEIRO', '02' => 'FEVEREIRO', '03' => 'MARÇO', '04' => 'ABRIL',
            '05' => 'MAIO', '06' => 'JUNHO', '07' => 'JULHO', '08' => 'AGOSTO',
            '09' => 'SETEMBRO', '10' => 'OUTUBRO', '11' => 'NOVEMBRO', '12' => 'DEZEMBRO',
            'janeiro' => 'JANEIRO', 'fevereiro' => 'FEVEREIRO', 'marco' => 'MARÇO', 'março' => 'MARÇO',
            'abril' => 'ABRIL', 'maio' => 'MAIO', 'junho' => 'JUNHO', 'julho' => 'JULHO',
            'agosto' => 'AGOSTO', 'setembro' => 'SETEMBRO', 'outubro' => 'OUTUBRO', 'novembro' => 'NOVEMBRO', 'dezembro' => 'DEZEMBRO',
        ];

        $lowerValue = mb_strtolower($value);

        // Se já for o nome do mês, retorna ele em maiúsculo
        if (isset($monthsMap[$lowerValue])) {
            return $monthsMap[$lowerValue];
        }

        $clean = preg_replace('/\s+/', '', $value);

        // Caso YYYY/MM ou YYYY-MM
        if (preg_match('/^(\d{4})[\/-](\d{1,2})$/', $clean, $matches)) {
            $m = str_pad($matches[2], 2, '0', STR_PAD_LEFT);

            return $monthsMap[$m] ?? $value;
        }

        // Caso MM/YYYY ou MM-YYYY
        if (preg_match('/^(\d{1,2})[\/-](\d{4})$/', $clean, $matches)) {
            $m = str_pad($matches[1], 2, '0', STR_PAD_LEFT);

            return $monthsMap[$m] ?? $value;
        }

        return mb_strtoupper($value);
    }

    private function transformDate($value)
    {
        if (! $value) {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
            }

            $cleanValue = trim($value);

            // Tenta formato brasileiro dd/mm/YYYY primeiro para evitar troca de mês/dia pelo Carbon
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $cleanValue)) {
                return Carbon::createFromFormat('d/m/Y', $cleanValue)->startOfDay();
            }

            return Carbon::parse($cleanValue)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }
}
