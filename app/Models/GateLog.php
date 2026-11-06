<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GateLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime', 'confidence_score' => 'float', 'obstacle_detected' => 'boolean'];
    }

    public function crossing() { return $this->belongsTo(Crossing::class); }
    public function device() { return $this->belongsTo(Device::class); }
    public function evaluations() { return $this->hasMany(EventNote::class); }

    public function getStatusTextAttribute(): string
    {
        $auto = $this->trigger_type === 'AUTOMATIC';
        return match ($this->gate_action) {
            'OPENED' => $auto ? 'Dibuka otomatis' : 'Dibuka petugas',
            'HELD' => 'Ditahan (rintangan)',
            default => $auto ? 'Ditutup otomatis' : 'Ditutup petugas',
        };
    }

    /** Kelas warna tag di tampilan. */
    public function getToneAttribute(): string
    {
        return match ($this->gate_action) {
            'OPENED' => 't-mute',
            'HELD' => 't-bad',
            default => $this->trigger_type === 'AUTOMATIC' ? 't-warn' : 't-ok',
        };
    }
}
