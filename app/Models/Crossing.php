<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crossing extends Model
{
    protected $guarded = [];

    public function devices() { return $this->hasMany(Device::class); }
    public function users() { return $this->belongsToMany(User::class); }
}
