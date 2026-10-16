<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractReview extends Model
{
    protected $fillable = [
        'contract_id',
        'user_id',
        'rating',
        'comment',
        'admin_reply',
        'replied_by',
        'replied_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'replied_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function getRatingLabelAttribute(): string
    {
        return ['', 'Muito Ruim', 'Ruim', 'Regular', 'Bom', 'Excelente'][$this->rating] ?? '—';
    }
}
