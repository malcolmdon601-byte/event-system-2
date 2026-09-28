<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['name', 'description', 'pricing_type', 'base_price', 'is_active'];
    protected $casts = ['base_price' => 'decimal:2', 'is_active' => 'boolean'];
    public function events() { return $this->belongsToMany(Event::class)->withPivot(['quantity', 'unit_price', 'subtotal'])->withTimestamps(); }
}
