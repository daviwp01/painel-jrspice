<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'type',
        'country',
    ];

    public function contractsAsExporter(): HasMany
    {
        return $this->hasMany(Contract::class, 'exporter_id');
    }

    public function contractsAsImporter(): HasMany
    {
        return $this->hasMany(Contract::class, 'importer_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
