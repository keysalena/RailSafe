<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogKontrol extends Model
{
    protected $table = 'log_kontrol';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['obstacle_checked' => 'boolean', 'obstacle_detected' => 'boolean', 'emergency_alarm' => 'boolean', 'completed_at' => 'datetime'];
    }

    public const ACTIONS = [
        'MANUAL_OK' => 'GATE_CLOSED_MANUAL',
        'FAIL_SAFE_AUTOGATE' => 'GATE_CLOSED_AUTO',
        'CRITICAL_OBSTACLE' => 'GATE_HELD_OBSTACLE',
    ];

    public const STATUS_TEXT = [
        'GATE_CLOSED_MANUAL' => 'Ditutup petugas',
        'GATE_CLOSED_AUTO' => 'Ditutup otomatis',
        'GATE_HELD_OBSTACLE' => 'Ditahan (rintangan)',
    ];

    public function getGateTextAttribute(): string
    {
        return self::STATUS_TEXT[$this->action] ?? '-';
    }
}
