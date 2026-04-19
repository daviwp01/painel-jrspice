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
        
        return [
            $price->product->name,
            $price->product->harvest_month,
            $price->product->country->name,
            $price->supplier ? $price->supplier->name : '',
            $date->format('d/m/Y'),
            $date->format('Y / m'),
            $date->weekOfYear,
            $price->price
        ];
    }
}
