<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['customer_id', 'event_type_id', 'name', 'event_date', 'guest_count', 'venue', 'budget', 'start_time', 'end_time', 'notes', 'message', 'status', 'reference'];
    protected $casts = ['event_date' => 'date:Y-m-d', 'budget' => 'decimal:2'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function eventType() { return $this->belongsTo(EventType::class); }
    public function services() { return $this->belongsToMany(Service::class)->withPivot(['quantity', 'unit_price', 'subtotal'])->withTimestamps(); }
    public function staff() { return $this->belongsToMany(Staff::class)->withTimestamps(); }
    public function vendors() { return $this->belongsToMany(Vendor::class)->withTimestamps(); }
    public function tasks() { return $this->hasMany(Task::class); }
    public function quotations() { return $this->hasMany(Quotation::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function messages() { return $this->hasMany(Message::class); }
    public function equipmentReservations() { return $this->hasMany(EquipmentReservation::class); }
}
