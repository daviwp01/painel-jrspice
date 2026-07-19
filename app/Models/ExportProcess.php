<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportProcess extends Model
{
    protected $fillable = [
        'date', 'contract_number', 'register_number', 
        'exporter_id', 'importer_id', 'product_id',
        'quantity_tons', 'price_per_ton_usd', 'sales_usd', 'annual_sales_usd', 
        'commission_usd', 'total_commission_usd', 'exchange_rate', 'estimated_euro',
        'estimated_receipt_date', 'seller_id', 'to_pay_usd', 'receipt_date', 
        'paid_in_date', 'paid_in_brl',
        'incident', 'video_sent', 'video_date', 'status', 'status_date', 
        'shipping_company', 'container_number',
        'dhl_date', 'dhl_number', 'etd_date', 'eta_date', 'observations'
    ];

    protected $casts = [
        'date' => 'date',
        'estimated_receipt_date' => 'date',
        'receipt_date' => 'date',
        'paid_in_date' => 'date',
        'video_date' => 'date',
        'status_date' => 'date',
        'dhl_date' => 'date',
        'etd_date' => 'date',
        'eta_date' => 'date',
        'video_sent' => 'boolean',
    ];

    public function exporter()
    {
        return $this->belongsTo(Client::class, 'exporter_id');
    }

    public function importer()
    {
        return $this->belongsTo(Client::class, 'importer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function documents()
    {
        return $this->hasMany(ExportProcessDocument::class, 'export_process_id');
    }
}
