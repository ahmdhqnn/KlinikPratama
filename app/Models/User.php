<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['admin', 'dokter', 'perawat', 'farmasi', 'pendaftaran'];

    public const SINGLE_ACTIVE_ROLES = ['admin', 'pendaftaran'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
        'address',
        'photo_path',
        'signature_path',
        'must_change_password',
        'session_version',
        'signing_pin_hash',
        'signing_pin_set_at',
        'must_setup_signing_pin',
    ];

    protected $hidden = [
        'password',
        'signing_pin_hash',
        'remember_token',
        'photo_path',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'must_setup_signing_pin' => 'boolean',
            'signing_pin_set_at' => 'datetime',
            'session_version' => 'integer',
        ];
    }

    public function hasSigningPin(): bool
    {
        return filled($this->signing_pin_hash);
    }

    public function supportsSigningPin(): bool
    {
        return in_array($this->role, ['dokter', 'perawat', 'farmasi', 'admin'], true);
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value): string => strtolower(trim($value)));
    }

    public function scopeForLoginEmail(Builder $query, string $email): Builder
    {
        return $query->whereRaw('LOWER(email) = ?', [strtolower(trim($email))]);
    }

    public function nakes(): HasOne
    {
        return $this->hasOne(Nakes::class);
    }

    public static function occupiedSingleRoles(?int $except = null): array
    {
        return static::query()->whereIn('role', self::SINGLE_ACTIVE_ROLES)->where('is_active', true)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->pluck('role')->all();
    }

    public function linkedProfileAllowsAccess(): bool
    {
        $positions = Nakes::positionsForRole($this->role);
        if ($positions === []) {
            return true;
        }

        return ! $this->nakes()->withTrashed()->where(fn ($query) => $query
            ->where('is_active', false)
            ->orWhereNotNull('deleted_at')
            ->orWhereNotIn('jabatan', $positions))->exists();
    }

    public function revokeAccess(): void
    {
        $this->forceFill([
            'is_active' => false,
            'session_version' => (int) $this->session_version + 1,
            'remember_token' => null,
        ])->save();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isPerawat(): bool
    {
        return $this->role === 'perawat';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'dokter' => 'Dokter',
            'perawat' => 'Perawat',
            'farmasi' => 'Farmasi',
            'pendaftaran' => 'Pendaftaran',
            default => ucfirst($this->role),
        };
    }
}
