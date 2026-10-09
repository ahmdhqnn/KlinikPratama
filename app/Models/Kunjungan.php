<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kunjungan extends Model
{
    protected $table = 'kunjungan';

    protected $fillable = [
        'no_kunjungan', 'pasien_id', 'poliklinik_id', 'dokter_id',
        'asuransi_id', 'tanggal', 'status', 'jenis_pasien',
        'jenis_bayar', 'catatan',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function asuransi(): BelongsTo
    {
        return $this->belongsTo(Asuransi::class);
    }

    public function screening(): HasOne
    {
        return $this->hasOne(Screening::class);
    }

    public function pemeriksaan(): HasOne
    {
        return $this->hasOne(Pemeriksaan::class);
    }

    public function resep(): HasOne
    {
        return $this->hasOne(Resep::class);
    }

    public function tindakanKunjungan(): HasMany
    {
        return $this->hasMany(TindakanKunjungan::class);
    }

    public function labHasil(): HasMany
    {
        return $this->hasMany(LabHasil::class);
    }

    public function farmasi(): HasOne
    {
        return $this->hasOne(Farmasi::class);
    }

    public function tagihan(): HasOne
    {
        return $this->hasOne(Tagihan::class);
    }

    public function suratMedis(): HasMany
    {
        return $this->hasMany(SuratMedis::class);
    }

    public function rujukanInternal(): HasMany
    {
        return $this->hasMany(RujukanInternal::class);
    }

    public function informedConsent(): HasMany
    {
        return $this->hasMany(InformedConsent::class);
    }

    public function odontogramFindings(): HasMany
    {
        return $this->hasMany(OdontogramFinding::class);
    }

    public static function generateNomor(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count();

        return 'KNJ-'.$date.'-'.str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
