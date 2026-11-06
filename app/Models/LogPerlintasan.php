<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogPerlintasan extends Model
{
    protected $table = 'log_perlintasan';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'confidence' => 'float', 'train_passed_confirmed' => 'boolean'];
    }

    public function kontrol() { return $this->hasOne(LogKontrol::class, 'event_id', 'event_id'); }
    public function crossing() { return $this->belongsTo(Crossing::class); }
    public function device() { return $this->belongsTo(Device::class); }
    public function notes() { return $this->hasMany(EventNote::class, 'event_id', 'event_id'); }
}
