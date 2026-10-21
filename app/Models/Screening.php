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
        'lingkar_perut', 'fungsi_penciuman', 'tingkat_kesadaran',
        'alergi_jenis', 'alergi_reaksi', 'penyakit_nama', 'penyakit_keterangan',
        'nyeri_dada', 'kondisi_psikiatri', 'nadi_teraba', 'kejang',
        'pola_pernapasan', 'kesadaran', 'risiko_jatuh_visual',
        'kesimpulan_triase', 'prioritas_layanan',
        'riwayat_penyakit_keluarga', 'skala_nyeri', 'lokasi_nyeri_gigi',
        'pemicu_nyeri_gigi', 'durasi_keluhan_gigi', 'risiko_medis_gigi',
        'riwayat_infeksi_gigi', 'catatan_medis_gigi',
        'keluhan_tambahan', 'lama_keluhan', 'riwayat_obat', 'status_alergi',
        'status_riwayat_penyakit', 'sumber_informasi', 'alasan_pengukuran_tidak_lengkap',
        'triase_perawat', 'catatan_triase', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'lingkar_perut' => 'decimal:1',
        'skala_nyeri' => 'integer',
        'pemicu_nyeri_gigi' => 'array',
        'risiko_medis_gigi' => 'array',
        'riwayat_infeksi_gigi' => 'array',
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

    /** @return array<string, ?string> */
    public function handoverSummary(): array
    {
        $historyLabels = ['ada' => 'Ada riwayat', 'tidak_ada' => 'Tidak ada, telah dikaji', 'belum_diketahui' => 'Belum dapat diketahui'];

        return [
            'Keluhan tambahan' => $this->keluhan_tambahan,
            'Lama keluhan' => $this->lama_keluhan,
            'Sumber informasi' => match ($this->sumber_informasi) {
                'pasien' => 'Pasien sendiri', 'keluarga' => 'Keluarga', 'pendamping' => 'Pendamping',
                'tidak_dapat_diperoleh' => 'Belum dapat diperoleh', default => null,
            },
            'Status alergi saat ini' => $historyLabels[$this->status_alergi] ?? null,
            'Status riwayat penyakit' => $historyLabels[$this->status_riwayat_penyakit] ?? null,
            'Obat yang digunakan' => $this->riwayat_obat,
            'Catatan Pengkajian' => $this->alasan_pengukuran_tidak_lengkap,
            'Catatan triase' => $this->catatan_triase,
            'Observasi perawat' => $this->pemeriksaan_fisik,
        ];
    }
}
