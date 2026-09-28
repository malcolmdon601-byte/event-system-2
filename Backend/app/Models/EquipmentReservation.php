<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentReservation extends Model
{
    protected $fillable = ['equipment_id', 'event_id', 'quantity', 'reserved_from', 'reserved_until'];
    public function equipment() { return $this->belongsTo(Equipment::class); }
    public function event() { return $this->belongsTo(Event::class); }
}
