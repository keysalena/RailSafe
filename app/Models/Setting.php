<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public const DEFAULTS = [
        'confidence_threshold' => 85,   // % ambang keyakinan TinyML (proposal: 85%)
        'countdown_seconds' => 15,      // waktu konfirmasi petugas (proposal: 15 detik)
        'gate_reopen_seconds' => 30,    // palang kembali terbuka setelah kereta lewat
    ];

    public static function get(string $key, $default = null)
    {
        $row = static::find($key);
        return $row ? $row->value : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function all_(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $k => $v) $out[$k] = (int) static::get($k, $v);
        return $out;
    }
}
