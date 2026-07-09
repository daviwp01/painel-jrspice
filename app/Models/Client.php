<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'name',
        'type',
        'country',
    ];

    public function exportProcessesAsExporter()
    {
        return $this->hasMany(ExportProcess::class, 'exporter_id');
    }

    public function exportProcessesAsImporter()
    {
        return $this->hasMany(ExportProcess::class, 'importer_id');
    }
}
