<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'filter_type',
        'filter_value',
        'page_context',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'searched_at' => 'datetime',
        ];
    }

    // ─── Filter type labels (display-friendly) ───────────────────
    public const TYPE_LABELS = [
        'country'    => 'País',
        'product'    => 'Produto',
        'supplier'   => 'Fornecedor',
        'date_range' => 'Período',
        'page'       => 'Página',
        'export'     => 'Exportação',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('filter_type', $type);
    }
}
