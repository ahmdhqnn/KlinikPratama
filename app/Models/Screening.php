<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screening extends Model
{
    protected $table = 'screening';

    protected $fillable = [
        'kunjungan_id', 'petugas_id', 'keluhan',
        'td_sistole', 'td_diastole', 'nadi', 'suhu',
        'berat_badan', 'tinggi_badan', 'spo2', 'respirasi',
        'riwayat_penyakit', 'riwayat_alergi', 'risiko_jatuh',
        'risiko_nyeri', 'skrining_gizi', 'pemeriksaan_fisik',
    ];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'petugas_id');
    }

    public function getImtAttribute(): ?float
    {
        if ($this->berat_badan && $this->tinggi_badan && $this->tinggi_badan > 0) {
            $tinggiM = $this->tinggi_badan / 100;

            return round($this->berat_badan / ($tinggiM * $tinggiM), 2);
        }

        return null;
    }
}
