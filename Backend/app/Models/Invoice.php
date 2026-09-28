<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['event_id', 'quotation_id', 'invoice_number', 'total', 'amount_paid', 'balance', 'due_date', 'status'];
    protected $casts = ['due_date' => 'date:Y-m-d'];
    public function event() { return $this->belongsTo(Event::class); }
    public function quotation() { return $this->belongsTo(Quotation::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
