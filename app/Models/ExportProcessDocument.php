<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportProcessDocument extends Model
{
    protected $fillable = [
        'export_process_id',
        'name',
        'file_path',
        'file_type',
        'uploaded_by',
    ];

    public function exportProcess()
    {
        return $this->belongsTo(ExportProcess::class, 'export_process_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
