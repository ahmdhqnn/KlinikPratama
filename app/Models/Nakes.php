<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nakes extends Model
{
    use SoftDeletes;

    protected $table = 'nakes';

    protected $fillable = [
        'kode', 'nama', 'jenis_kelamin', 'kategori', 'jabatan',
        'poliklinik_id', 'no_sip', 'no_str', 'telepon', 'user_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public const LOGIN_POSITIONS = [
        'dokter' => 'dokter',
        'perawat' => 'perawat',
        'bidan' => 'perawat',
        'terapis_gigi' => 'perawat',
        'farmasi' => 'farmasi',
        'apotek' => 'farmasi',
        'pendaftaran' => 'pendaftaran',
    ];

    public static function positionsForRole(string $role): array
    {
        return array_keys(self::LOGIN_POSITIONS, $role, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class, 'dokter_id');
    }

    public function pemeriksaan(): HasMany
    {
        return $this->hasMany(Pemeriksaan::class, 'dokter_id');
    }

    public function screening(): HasMany
    {
        return $this->hasMany(Screening::class, 'petugas_id');
    }

    public function farmasi(): HasMany
    {
        return $this->hasMany(Farmasi::class, 'petugas_id');
    }
}
