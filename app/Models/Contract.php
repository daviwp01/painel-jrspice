<?php

namespace App\Models;

use App\Support\ContractStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'date',
        'contract_number',
        'exporter_id',
        'importer_id',
        'country',
        'seller_id',
        'status',
        'status_date',
        'dhl_date',
        'dhl_number',
        'etd_date',
        'eta_date',
        'video_sent',
        'video_reference',
        'video_date',
        'incident',
        'estimated_receipt_date',
        'exchange_rate',
        'receipt_date',
        'paid_in_date',
        'paid_in_brl',
        'observations',
        'shipping_company',
        'container_number',
        'source_row',
        'has_incident',
    ];

    protected $casts = [
        'date' => 'date',
        'status_date' => 'date',
        'dhl_date' => 'date',
        'etd_date' => 'date',
        'eta_date' => 'date',
        'video_sent' => 'boolean',
        'video_date' => 'date',
        'estimated_receipt_date' => 'date',
        'receipt_date' => 'date',
        'paid_in_date' => 'date',
        'exchange_rate' => 'decimal:4',
        'paid_in_brl' => 'decimal:2',
        'has_incident' => 'boolean',
    ];

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'exporter_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'importer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class)->orderBy('position');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contract_users')->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class, 'contract_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ContractReview::class, 'contract_id');
    }

    public function getIsFinishedAttribute(): bool
    {
        return ContractStatus::isFinished($this->status);
    }

    public function salesTotal(): float
    {
        return (float) $this->items()->sum('sales_usd');
    }

    public function commissionTotal(): float
    {
        return (float) $this->items()->sum('commission_usd');
    }

    public function quantityTotal(): float
    {
        return (float) $this->items()->sum('quantity_tons');
    }
}
