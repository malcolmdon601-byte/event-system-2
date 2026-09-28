<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $fillable = ['event_id', 'quotation_number', 'subtotal', 'discount', 'tax', 'total', 'deposit_amount', 'valid_until', 'notes', 'status'];
    protected $casts = ['valid_until' => 'date:Y-m-d'];
    public function event() { return $this->belongsTo(Event::class); }
    public function items() { return $this->hasMany(QuotationItem::class); }
    public function invoice() { return $this->hasOne(Invoice::class); }
}
