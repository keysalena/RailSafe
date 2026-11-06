<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Device extends Model
{
    protected $guarded = [];
    protected $hidden = ['api_token_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public const OFFLINE_AFTER_SECONDS = 90;

    public function crossing() { return $this->belongsTo(Crossing::class); }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Buat token baru, simpan hash-nya, kembalikan token asli (ditampilkan sekali saja). */
    public function issueToken(): string
    {
        $plain = 'rs_'.Str::random(40);
        $this->update(['api_token_hash' => self::hashToken($plain)]);
        return $plain;
    }

    /** ACTIVE | DISCONNECTED | FAULT | UNKNOWN (empat status di proposal). */
    public function getStatusAttribute(): string
    {
        if (! $this->last_seen_at) return 'UNKNOWN';
        if (now()->timestamp - $this->last_seen_at->timestamp > self::OFFLINE_AFTER_SECONDS) return 'DISCONNECTED';
        return $this->connection_status === 'FAULT' ? 'FAULT' : 'ACTIVE';
    }
}
