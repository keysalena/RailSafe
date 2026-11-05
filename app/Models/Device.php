<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['device_id', 'name', 'type', 'location', 'api_key_hash', 'last_seen_at'];
    protected $hidden = ['api_key_hash'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function isOnline(int $seconds = 60): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds($seconds));
    }

    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
