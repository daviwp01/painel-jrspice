<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardPage extends Model
{
    protected $fillable = ['title', 'slug', 'component', 'is_active', 'order'];
}
