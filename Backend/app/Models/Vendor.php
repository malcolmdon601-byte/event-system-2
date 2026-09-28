<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = ['name', 'category', 'email', 'phone', 'address', 'status'];
    public function events() { return $this->belongsToMany(Event::class)->withTimestamps(); }
}
