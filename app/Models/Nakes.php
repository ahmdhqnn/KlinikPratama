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
        'no_sip', 'no_str', 'telepon', 'user_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class, 'kasir_id');
    }

    public function biayaPendaftaran(): HasMany
    {
        return $this->hasMany(BiayaPendaftaran::class, 'dokter_id');
    }
}
