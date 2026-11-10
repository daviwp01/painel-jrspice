<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'name_es',
        'country_id',
        'harvest_month',
        'is_default_filter',
    ];

    protected $casts = ['is_default_filter' => 'boolean'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Locale-aware label for display only. Never use in queries, joins,
     * sorting or matching — `name` remains the canonical identifier.
     */
    public function displayName(): string
    {
        $locale = app()->getLocale();

        if ($locale === 'en' && filled($this->name_en)) {
            return (string) $this->name_en;
        }

        if ($locale === 'es' && filled($this->name_es)) {
            return (string) $this->name_es;
        }

        return (string) $this->name;
    }
}
