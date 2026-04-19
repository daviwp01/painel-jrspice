<?php

namespace App\Imports;

use App\Models\Country;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductPrice;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
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

        // 1. "Rede de Arraste" para capturar colunas por similaridade
        $harvestMonth = null;
        $countryName = null;
        $productName = null;
        $supplierName = null;
        $dateValue = null;
        $priceValue = 0;

        foreach ($data as $key => $val) {
            $key = mb_strtolower(trim($key));
            
            // Lógica de Safra (Fuzzy match)
            if (str_contains($key, 'safra') || str_contains($key, 'harvest') || $key === 'mes') {
                $harvestMonth = $val;
            }
            
            // Lógica de Produto
            if (str_contains($key, 'produto') || str_contains($key, 'product')) {
                $productName = $val;
            }

            // Lógica de País
            if (str_contains($key, 'pais') || str_contains($key, 'país') || str_contains($key, 'country') || str_contains($key, 'origem')) {
                $countryName = $val;
            }

            // Lógica de Fornecedor
            if (str_contains($key, 'fornecedor') || str_contains($key, 'supplier')) {
                $supplierName = $val;
            }

            // Lógica de Data
            if (str_contains($key, 'data') || str_contains($key, 'date')) {
                $dateValue = $val;
            }

            // Lógica de Preço
            if (str_contains($key, 'preco') || str_contains($key, 'preço') || str_contains($key, 'price') || str_contains($key, 'valor')) {
                $priceValue = $val;
            }
        }
        
        // Fallbacks adicionais se a rede de arraste falhar em chaves exatas
        $countryName  = $countryName ?? $data['pais'] ?? $data['country'] ?? null;
        $productName  = $productName ?? $data['produto'] ?? $data['product'] ?? null;
        $harvestMonth = $harvestMonth ?? $data['safra'] ?? $data['harvest'] ?? null;
        
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
        }
        $priceValue = (float) $priceValue;

        if (!$countryName || !$productName) return;

        // 1. Pais
        $country = Country::firstOrCreate(['name' => trim($countryName)]);

        // 2. Produto (Update para persistir a SAFRA se houver dado)
        $product = Product::firstOrCreate([
            'name' => trim($productName),
            'country_id' => $country->id
        ]);
        
        if ($harvestMonth) {
            $product->harvest_month = trim($harvestMonth);
            $product->save();
        }

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
                    'price' => $priceValue,
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
    public function headingRow(): int
    {
        return 1;
    }
}
