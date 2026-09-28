<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = ['event_id', 'uploaded_by', 'name', 'path', 'mime_type'];
    public function event() { return $this->belongsTo(Event::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
