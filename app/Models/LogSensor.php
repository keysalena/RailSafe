<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSensor extends Model
{
    protected $table = 'log_sensor';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }
}
