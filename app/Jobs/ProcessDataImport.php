<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Country;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter
{
    private $startRow = 0;
    private $endRow = 0;

    public function setRows($startRow, $chunkSize) {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool {
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
            $tmpPath = '/tmp/import_final_' . uniqid() . '.xlsx';
            if (!file_exists($this->filePath)) throw new \Exception("Erro: Arquivo original não acessível.");
            copy($this->filePath, $tmpPath);

            $reader = new Xlsx();
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
            $headers = array_map(function($h) { return trim(mb_strtolower($h)); }, $rawHeaders);
            
            $colMap = [
                'product'  => array_search('produto', $headers),
                'country'  => array_search('país', $headers) ?: array_search('pais', $headers),
                'supplier' => array_search('fornecedor', $headers),
                'price'    => array_search('preço', $headers) ?: (array_search('preco', $headers) ?: array_search('valor', $headers)),
                'date'     => null
            ];

            // Busca flexível pela coluna de Data
            foreach ($headers as $key => $val) {
                if (str_contains($val, 'data')) {
                    $colMap['date'] = $key;
                    break;
                }
            }

            $spreadsheetHeader->disconnectWorksheets();
            unset($spreadsheetHeader, $sheet, $rawHeaders, $headers);

            // --- PRÉ-CARREGAMENTO (TURBO) ---
            $countryCache = Country::all()->pluck('id', 'name')->toArray();
            $supplierCache = Supplier::all()->pluck('id', 'name')->toArray();
            $productCache = Product::all()->mapWithKeys(function ($p) {
                return [$p->country_id . '_' . $p->name => $p->id];
            })->toArray();

            $filter = new ChunkReadFilter();
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
                    if ($rowIndex == 1) continue; // Pula cabeçalho se estiver no chunk
                    if ($rowIndex < $startRow || $rowIndex >= $startRow + $chunkSize) continue;
                    if (empty(array_filter($row))) continue;
                    
                    $processedCount++;
                    
                    $productName = trim($row[$colMap['product']] ?? '');
                    $countryName = trim($row[$colMap['country']] ?? '');
                    $supplierName = trim($row[$colMap['supplier']] ?? '');
                    $dateValue = $row[$colMap['date']] ?? null;
                    $rawPrice = $row[$colMap['price']] ?? 0;

                    if (!$productName || !$countryName) continue;

                    if (is_string($rawPrice)) {
                        $rawPrice = preg_replace('/[^0-9,.]/', '', $rawPrice);
                        if (str_contains($rawPrice, ',') && str_contains($rawPrice, '.')) {
                            $rawPrice = str_replace('.', '', $rawPrice);
                            $rawPrice = str_replace(',', '.', $rawPrice);
                        } elseif (str_contains($rawPrice, ',')) {
                            $rawPrice = str_replace(',', '.', $rawPrice);
                        }
                    }
                    $priceValue = floatval($rawPrice);

                    // 1. País (Turbo)
                    if (!isset($countryCache[$countryName])) {
                        $countryCache[$countryName] = Country::firstOrCreate(['name' => $countryName])->id;
                    }
                    $countryId = $countryCache[$countryName];

                    // 2. Produto (Turbo)
                    $productKey = $countryId . '_' . $productName;
                    if (!isset($productCache[$productKey])) {
                        $productCache[$productKey] = Product::firstOrCreate(['name' => $productName, 'country_id' => $countryId])->id;
                    }
                    $productId = $productCache[$productKey];

                    // 3. Fornecedor (Turbo)
                    $supplierId = null;
                    if ($supplierName) {
                        if (!isset($supplierCache[$supplierName])) {
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
                        'percentage' => round(($processedCount / $totalRows) * 100)
                    ], 600);
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet, $rows);
                gc_collect_cycles();
            }

            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => $totalRows, 'total' => $totalRows, 'status' => 'completed', 'percentage' => 100], 600);
            }
            @unlink($tmpPath);
            @unlink($this->filePath); // AUTO-LIMPEZA: Remove o arquivo original após sucesso

        } catch (\Throwable $e) {
            Log::error('ERRO NA IMPORTAÇÃO: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => 0, 'total' => 0, 'status' => 'failed', 'message' => $e->getMessage(), 'percentage' => 0], 600);
            }
            if (isset($tmpPath) && file_exists($tmpPath)) @unlink($tmpPath);
            if (isset($this->filePath) && file_exists($this->filePath)) @unlink($this->filePath); // AUTO-LIMPEZA: Remove o arquivo mesmo em falha
            throw $e;
        }
    }

    private function transformDate($value)
    {
        if (!$value) return null;
        try {
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
            }
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
