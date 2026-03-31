<?php

namespace App\Imports;

use App\Models\Country;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductPrice;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DataImport implements OnEachRow, WithHeadingRow
{
    protected $jobId;
    protected $current = 0;

    public function __construct($jobId = null)
    {
        $this->jobId = $jobId;
    }

    public function onRow(Row $row)
    {
        $this->current++;
        $data = $row->toArray();
        \Illuminate\Support\Facades\Log::info('Dados da linha importada:', $data);

        $countryName = $data['pais'] ?? $data['country'] ?? null;
        $productName = $data['produto'] ?? $data['product'] ?? null;
        $supplierName = $data['fornecedor'] ?? $data['supplier'] ?? null;
        $dateValue = $data['data_registro'] ?? $data['date'] ?? null;
        $priceValue = $data['preco'] ?? $data['price'] ?? $data['preço'] ?? $data['valor'] ?? 0;
        
        // Advanced cleanup for currency strings in various formats
        if (is_string($priceValue)) {
            // Remove everything except numbers, dots and commas
            $priceValue = preg_replace('/[^0-9,.]/', '', $priceValue);
            
            // If it's something like "1.400,00" -> remove dot, swap comma to dot
            if (str_contains($priceValue, ',') && str_contains($priceValue, '.')) {
                $priceValue = str_replace('.', '', $priceValue);
                $priceValue = str_replace(',', '.', $priceValue);
            } 
            // If it's "1400,00" -> swap comma to dot
            elseif (str_contains($priceValue, ',')) {
                $priceValue = str_replace(',', '.', $priceValue);
            }
            // If it's "1.400" (US format vs BR format?) -> This is tricky.
            // But usually raw numeric cells from Excel come as numbers, strings are formatted.
        }
        $priceValue = (float) $priceValue;

        if (!$countryName || !$productName) return;

        // 1. Pais
        $country = Country::firstOrCreate(['name' => trim($countryName)]);

        // 2. Produto
        $product = Product::firstOrCreate([
            'name' => trim($productName),
            'country_id' => $country->id
        ]);

        // 3. Fornecedor
        $supplier = null;
        if ($supplierName) {
            $supplier = Supplier::firstOrCreate(['name' => trim($supplierName)]);
        }

        // 4. Preço e Data
        $date = $this->transformDate($dateValue);
        
        if ($date) {
            ProductPrice::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'supplier_id' => $supplier ? $supplier->id : null,
                    'date' => $date->format('Y-m-d'),
                ],
                [
                    'min_price' => $priceValue,
                    'max_price' => $priceValue,
                    'average_price' => $priceValue,
                ]
            );
        }

        // Atualiza progresso a cada 100 linhas no cache
        if ($this->jobId && ($this->current % 100 == 0)) {
            Cache::put("import_progress_{$this->jobId}", [
                'current' => $this->current,
                'total' => 0,
                'status' => 'processing',
                'percentage' => 0
            ], 600);
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
