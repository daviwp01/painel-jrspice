<?php

namespace App\Jobs;

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
        if ($row == 1 || ($row >= $this->startRow && $row < $this->endRow)) {
            return true;
        }
        return false;
    }
}

class ProcessDataImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            $chunkSize = 300; // REDUZIDO: Para máxima estabilidade no Docker 128MB

            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => 0, 'total' => $totalRows, 'status' => 'processing', 'percentage' => 1], 600);
            }

            // --- PRÉ-CARREGAMENTO (TURBO) ---
            $countryCache = Country::all()->pluck('id', 'name')->toArray();
            $supplierCache = Supplier::all()->pluck('id', 'name')->toArray();
            $productCache = Product::all()->mapWithKeys(function ($p) {
                return [$p->country_id . '_' . $p->name => $p->id];
            })->toArray();

            $filter = new ChunkReadFilter();
            $reader->setReadFilter($filter);
            $processedCount = 0;

            for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
                $filter->setRows($startRow, $chunkSize);
                $spreadsheet = $reader->load($tmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Pular as 3 primeiras linhas (Filtros, vazia, cabeçalhos)
                // Se startRow for 2 (primeira iteração do loop), pula se row < 4
                foreach ($rows as $rowIndex => $row) {
                    $currentRowNumber = $startRow + $rowIndex;
                    if ($currentRowNumber < 4) continue;
                    if (empty(array_filter($row))) continue;
                    
                    \Illuminate\Support\Facades\Log::info('DEBUG: Importando linha:', ['row' => $currentRowNumber, 'prod' => $row[0] ?? '', 'price' => $row[6] ?? '']);
                    $processedCount++;
                    
                    $productName = trim($row[0] ?? '');
                    $countryName = trim($row[1] ?? '');
                    $supplierName = trim($row[2] ?? '');
                    $dateValue = $row[3] ?? null;
                    $rawPrice = $row[6] ?? 0;

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

                // Atualiza progresso após cada fatia processada
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

        } catch (\Throwable $e) {
            Log::error('ERRO INABALÁVEL: ' . $e->getMessage());
            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", ['current' => 0, 'total' => 0, 'status' => 'failed', 'message' => $e->getMessage(), 'percentage' => 0], 600);
            }
            if (isset($tmpPath) && file_exists($tmpPath)) @unlink($tmpPath);
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
