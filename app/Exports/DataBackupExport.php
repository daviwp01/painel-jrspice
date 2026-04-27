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
        
        $monthsMap = [
            1 => 'JANEIRO', 2 => 'FEVEREIRO', 3 => 'MARÇO', 4 => 'ABRIL',
            5 => 'MAIO', 6 => 'JUNHO', 7 => 'JULHO', 8 => 'AGOSTO',
            9 => 'SETEMBRO', 10 => 'OUTUBRO', 11 => 'NOVEMBRO', 12 => 'DEZEMBRO'
        ];

        $harvest = $price->product->harvest_month;
        
        // Se a safra estiver em formato YYYY / MM, converte o mês para nome
        if ($harvest) {
            $clean = preg_replace('/\s+/', '', $harvest);
            if (preg_match('/^(\d{1,2})[\/-](\d{4})$/', $clean, $matches)) {
                $harvest = $monthsMap[(int)$matches[1]] ?? $harvest;
            } elseif (preg_match('/^(\d{4})[\/-](\d{1,2})$/', $clean, $matches)) {
                $harvest = $monthsMap[(int)$matches[2]] ?? $harvest;
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
