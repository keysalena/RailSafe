<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $action, ?string $type = null, $id = null, array $meta = []): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id !== null ? (string) $id : null,
            'meta' => $meta ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
