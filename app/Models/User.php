<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'last_login_at'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function crossings(): BelongsToMany
    {
        return $this->belongsToMany(Crossing::class);
    }

    /** ID pos yang boleh dilihat: superadmin semua, operator hanya yang ditugaskan. */
    public function accessibleCrossingIds(): array
    {
        return $this->isSuperadmin()
            ? Crossing::pluck('id')->all()
            : $this->crossings()->pluck('crossings.id')->all();
    }

    /** Batasi ke satu pos jika dipilih dan memang boleh diakses. */
    public function scopedCrossingIds(?int $selected = null): array
    {
        $ids = $this->accessibleCrossingIds();
        return ($selected && in_array($selected, $ids, true)) ? [$selected] : $ids;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }
}
