<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = ['name', 'description', 'base_price', 'is_active'];
    protected $casts = ['base_price' => 'decimal:2', 'is_active' => 'boolean'];
}
