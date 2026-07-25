<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessReview extends Model
{
    protected $fillable = [
        'export_process_id',
        'user_id',
        'rating',
        'comment',
        'admin_reply',
        'replied_by',
        'replied_at',
    ];

    protected $casts = [
        'rating'     => 'integer',
        'replied_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function process()
    {
        return $this->belongsTo(ExportProcess::class, 'export_process_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getRatingLabelAttribute(): string
    {
        return ['', 'Muito Ruim', 'Ruim', 'Regular', 'Bom', 'Excelente'][$this->rating] ?? '—';
    }
}
