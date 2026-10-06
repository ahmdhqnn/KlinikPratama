<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use SoftDeletes;

    protected $table = 'pasien';

    protected $fillable = [
        'no_rm', 'nama', 'nik', 'tanggal_lahir', 'jenis_kelamin',
        'golongan_darah', 'alamat', 'telepon', 'pekerjaan', 'agama',
        'status_perkawinan', 'nama_wali', 'telepon_wali',
        'asuransi_id', 'no_asuransi', 'riwayat_alergi',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function asuransi(): BelongsTo
    {
        return $this->belongsTo(Asuransi::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function getUmurAttribute(): int
    {
        return $this->tanggal_lahir ? $this->tanggal_lahir->age : 0;
    }
}
