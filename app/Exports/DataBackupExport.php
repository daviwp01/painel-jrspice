<?php

namespace App\Exports;

use App\Models\ProductPrice;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataBackupExport implements FromQuery, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Database\Eloquent\Builder
    */
    public function query()
    {
        // Usa query builder para processar em chunks (economiza memória)
        return ProductPrice::query()
            ->with(['product.country', 'supplier'])
            ->orderBy('date', 'desc');
    }

    public function headings(): array
    {
        return [
            'PRODUTO',
            'SAFRA',
            'PAÍS',
            'FORNECEDOR',
            'DATA REGISTRO',
            'ANO / MES',
            'SEMANA',
            'PREÇO'
        ];
    }

    /**
    * @var ProductPrice $price
    */
    public function map($price): array
    {
        $date = \Carbon\Carbon::parse($price->date);
        
        $harvest = $price->product->harvest_month;
        if ($harvest) {
            // Normaliza a saída para o formato "YYYY / MM" no backup
            $clean = preg_replace('/\s+/', '', $harvest);
            if (preg_match('/^(\d{1,2})[\/-](\d{4})$/', $clean, $matches)) {
                $harvest = $matches[2] . ' / ' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            } elseif (preg_match('/^(\d{4})[\/-](\d{1,2})$/', $clean, $matches)) {
                $harvest = $matches[1] . ' / ' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            }
        }
        
        return [
            $price->product->name,
            $harvest,
            $price->product->country->name,
            $price->supplier ? $price->supplier->name : '',
            $date->format('d/m/Y'),
            $date->format('Y / m'),
            $date->weekOfYear,
            $price->price
        ];
    }
}
