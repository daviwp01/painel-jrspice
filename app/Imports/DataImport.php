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
            if (str_contains($key, 'safra') || str_contains($key, 'harvest')) {
                $harvestMonth = $val;
            } elseif (str_contains($key, 'mes') && $harvestMonth === null && !str_contains($key, 'ano')) {
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
        $rawPrice = $priceValue;
        // Remove todos os tipos de espaços invisíveis (incluindo non-breaking space do Excel)
        $cleanPrice = preg_replace('/[\p{Z}\s]/u', '', (string)$rawPrice);
        $priceValue = ($cleanPrice !== '') ? floatval(str_replace(',', '.', str_replace('.', '', $cleanPrice))) : 0;
        
        // Validação Absoluta: Ignora se for nulo, vazio (mesmo com espaços invisíveis), zero ou negativo
        if ($rawPrice === null || $cleanPrice === '' || $priceValue <= 0) {
            \Illuminate\Support\Facades\Log::warning("Linha ignorada no DataImport síncrono: Produto '{$productName}' está sem preço ou com valor zero.");
            return;
        }

        // 1. Pais
        $country = Country::firstOrCreate(['name' => trim($countryName)]);

        // 2. Produto (Update para persistir a SAFRA se houver dado)
        $product = Product::firstOrCreate([
            'name' => trim($productName),
            'country_id' => $country->id
        ]);
        
        if ($harvestMonth) {
            $product->harvest_month = $this->normalizeHarvest($harvestMonth);
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

    private function normalizeHarvest($value) {
        if (!$value) return null;
        $value = trim($value);
        
        $monthsMap = [
            '01' => 'JANEIRO', '02' => 'FEVEREIRO', '03' => 'MARÇO', '04' => 'ABRIL',
            '05' => 'MAIO', '06' => 'JUNHO', '07' => 'JULHO', '08' => 'AGOSTO',
            '09' => 'SETEMBRO', '10' => 'OUTUBRO', '11' => 'NOVEMBRO', '12' => 'DEZEMBRO',
            'janeiro' => 'JANEIRO', 'fevereiro' => 'FEVEREIRO', 'marco' => 'MARÇO', 'março' => 'MARÇO',
            'abril' => 'ABRIL', 'maio' => 'MAIO', 'junho' => 'JUNHO', 'julho' => 'JULHO',
            'agosto' => 'AGOSTO', 'setembro' => 'SETEMBRO', 'outubro' => 'OUTUBRO', 'novembro' => 'NOVEMBRO', 'dezembro' => 'DEZEMBRO'
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
