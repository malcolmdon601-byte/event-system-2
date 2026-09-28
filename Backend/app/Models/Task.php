<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['event_id', 'assigned_staff_id', 'title', 'due_date', 'priority', 'status'];
    protected $casts = ['due_date' => 'date:Y-m-d'];
    public function event() { return $this->belongsTo(Event::class); }
    public function assignedStaff() { return $this->belongsTo(Staff::class, 'assigned_staff_id'); }
}
