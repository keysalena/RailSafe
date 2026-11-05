<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GateState extends Model
{
    protected $fillable = ['device_id', 'state', 'mode', 'changed_by'];

    public function device() { return $this->belongsTo(Device::class); }
}
