<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['invoice_id', 'event_id', 'customer_id', 'payment_reference', 'amount', 'method', 'status', 'is_simulated', 'paid_at'];
    protected $casts = ['paid_at' => 'datetime', 'is_simulated' => 'boolean'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function event() { return $this->belongsTo(Event::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
