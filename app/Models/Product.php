<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'country_id', 'harvest_month', 'is_default_filter'];

    protected $casts = ['is_default_filter' => 'boolean'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }
}
