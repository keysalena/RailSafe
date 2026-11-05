<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'event_id', 'device_id', 'event_type', 'confidence', 'status',
        'triggered_at', 'acknowledged_at', 'acknowledged_by',
    ];
    protected $casts = ['triggered_at' => 'datetime', 'acknowledged_at' => 'datetime'];

    public function device() { return $this->belongsTo(Device::class); }
}
