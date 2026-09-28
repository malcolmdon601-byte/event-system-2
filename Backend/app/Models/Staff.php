<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'phone', 'role_title', 'status'];
    public function events() { return $this->belongsToMany(Event::class)->withTimestamps(); }
    public function tasks() { return $this->hasMany(Task::class, 'assigned_staff_id'); }
}
