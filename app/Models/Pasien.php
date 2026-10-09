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
        'no_rm', 'nama', 'nik', 'kepesertaan_id', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'golongan_darah', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan',
        'telepon', 'pekerjaan', 'agama', 'status_perkawinan', 'nama_wali',
        'nama_ibu', 'telepon_wali',
        'asuransi_id', 'no_asuransi', 'riwayat_alergi',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function asuransi(): BelongsTo
    {
        return $this->belongsTo(Asuransi::class);
    }

    public function kepesertaan(): BelongsTo
    {
        return $this->belongsTo(Kepesertaan::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function getUmurAttribute(): ?int
    {
        return $this->tanggal_lahir?->age;
    }
}
