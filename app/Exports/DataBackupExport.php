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
            'Produto',
            'País',
            'Fornecedor',
            'Data Registro',
            'Preço'
        ];
    }

    /**
    * @var ProductPrice $price
    */
    public function map($price): array
    {
        return [
            $price->product->name,
            $price->product->country->name,
            $price->supplier ? $price->supplier->name : '',
            $price->date,
            $price->price
        ];
    }
}
