<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorTelemetry extends Model
{
    protected $fillable = [
        'device_id', 'vibration_class', 'confidence', 'battery_percent', 'battery_voltage',
        'lora_rssi', 'lora_snr', 'distance_cm', 'recorded_at',
    ];
    protected $casts = ['recorded_at' => 'datetime', 'confidence' => 'float', 'distance_cm' => 'float'];

    public function device() { return $this->belongsTo(Device::class); }
}
