<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use SoftDeletes;

    protected $table = 'pasien';

    protected $fillable = [
        'no_rm', 'nama', 'nik', 'ihs_number', 'nip', 'kepesertaan_id', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'golongan_darah', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan',
        'kode_provinsi', 'kode_kabupaten', 'kode_kecamatan', 'kode_kelurahan', 'kode_pos',
        'telepon', 'pekerjaan', 'pendidikan', 'unit_kerja', 'agama', 'kewarganegaraan', 'no_paspor', 'alasan_tanpa_nik',
        'status_perkawinan', 'nama_wali', 'nama_ibu', 'telepon_wali', 'riwayat_alergi',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function latestGeneralConsent(): HasOne
    {
        return $this->hasOne(GeneralConsent::class)->latestOfMany();
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
