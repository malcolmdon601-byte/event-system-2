<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'name', 'email', 'phone', 'address'];
    public function user() { return $this->hasOne(User::class); }
    public function events() { return $this->hasMany(Event::class); }
}
