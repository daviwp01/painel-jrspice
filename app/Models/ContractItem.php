<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractItem extends Model
{
    protected $fillable = [
        'contract_id',
        'position',
        'register_number',
        'product_id',
        'quantity_tons',
        'price_per_ton_usd',
        'sales_usd',
        'commission_rate',
        'commission_usd',
        'exchange_rate',
        'estimated_euro',
        'to_pay_rate',
        'to_pay_usd',
        'etd_date',
        'eta_date',
        'dhl_number',
        'video_date',
        'video_reference',
        'observations',
        'source_row',
    ];

    protected $casts = [
        'quantity_tons' => 'decimal:3',
        'price_per_ton_usd' => 'decimal:2',
        'sales_usd' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_usd' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'estimated_euro' => 'decimal:3',
        'to_pay_rate' => 'decimal:4',
        'to_pay_usd' => 'decimal:2',
        'etd_date' => 'date',
        'eta_date' => 'date',
        'video_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ContractProduct::class, 'product_id');
    }
}
