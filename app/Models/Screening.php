<?php

namespace App\Models;

use App\DentalAssessment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screening extends Model
{
    protected $table = 'screening';

    protected $fillable = [
        'kunjungan_id', 'petugas_id', 'recorded_by_user_id', 'keluhan',
        'extraoral_symmetry', 'extraoral_swelling', 'extraoral_notes', 'dental_red_flags',
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
        'dental_red_flags' => 'array',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
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
    public function dentalObservationSummary(): array
    {
        $labels = ['symmetric' => 'Tampak simetris', 'asymmetric' => 'Tampak asimetris', 'none' => 'Tidak tampak', 'present' => 'Tampak pembengkakan', 'unknown' => 'Belum dapat dinilai'];

        return [
            'Visual ekstraoral awal perawat - simetri wajah' => $labels[$this->extraoral_symmetry ?? ''] ?? null,
            'Visual ekstraoral awal perawat - pembengkakan' => $labels[$this->extraoral_swelling ?? ''] ?? null,
            'Catatan visual ekstraoral awal perawat' => $this->extraoral_notes,
            'Tanda bahaya gigi / mulut' => $this->dental_red_flags ? implode(', ', array_map(fn (string $value): string => DentalAssessment::redFlagLabels()[$value] ?? $value, $this->dental_red_flags)) : null,
        ];
    }

    /** @return array<string, ?string> */
    public function handoverSummary(): array
    {
        $labels = [
            'ada' => 'Ada riwayat', 'tidak_ada' => 'Tidak ada, telah dikaji', 'belum_diketahui' => 'Belum dapat diketahui',
            'pasien' => 'Pasien sendiri', 'keluarga' => 'Keluarga', 'pendamping' => 'Pendamping',
            'tidak_dapat_diperoleh' => 'Belum dapat diperoleh', 'ya' => 'Ya', 'tidak' => 'Tidak',
            'teraba' => 'Teraba', 'tidak_teraba' => 'Tidak teraba', 'normal' => 'Normal',
            'tidak_normal' => 'Tidak normal', 'sadar' => 'Sadar', 'menurun' => 'Menurun',
            'tidak_sadar' => 'Tidak sadar', 'terganggu' => 'Terganggu', 'rendah' => 'Rendah',
            'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'kurang' => 'Gizi kurang', 'lebih' => 'Gizi lebih',
            'merah' => 'Merah - perlu respons segera', 'kuning' => 'Kuning - perlu penilaian lanjut',
            'hijau' => 'Hijau - prioritas rutin', 'darurat' => 'Respons darurat', 'segera' => 'Penilaian segera',
            'hipertensi' => 'Hipertensi', 'diabetes' => 'Diabetes melitus', 'penyakit_jantung' => 'Penyakit jantung',
            'gangguan_pembekuan' => 'Gangguan pembekuan darah', 'antikoagulan' => 'Obat antikoagulan',
            'hepatitis' => 'Hepatitis', 'hiv' => 'HIV', 'infeksi_lain' => 'Infeksi lain',
            'dingin' => 'Dingin', 'manis' => 'Manis', 'panas' => 'Panas', 'spontan' => 'Spontan',
            'mengunyah' => 'Mengunyah', 'lainnya' => 'Lainnya',
        ];
        $choice = static fn (?string $value): ?string => filled($value) ? ($labels[$value] ?? $value) : null;
        $join = static fn (?string ...$values): ?string => ($joined = implode(' — ', array_filter($values, static fn (?string $value): bool => filled($value)))) !== '' ? $joined : null;
        $choices = static fn (?array $values): ?string => $values ? implode(', ', array_map(static fn (string $value): string => $labels[$value] ?? $value, $values)) : null;
        $response = filled($this->kesimpulan_triase)
            ? $choice($this->kesimpulan_triase).(filled($this->prioritas_layanan) ? ' · '.$choice($this->prioritas_layanan) : '')
            : null;

        return [
            'Keluhan tambahan' => $this->keluhan_tambahan,
            'Lama keluhan' => $this->lama_keluhan,
            'Sumber informasi' => $choice($this->sumber_informasi),
            'Status riwayat penyakit' => $choice($this->status_riwayat_penyakit),
            'Nama penyakit' => $this->penyakit_nama,
            'Keterangan riwayat penyakit' => $this->penyakit_keterangan,
            'Obat yang digunakan' => $this->riwayat_obat,
            'Status alergi saat ini' => $choice($this->status_alergi),
            'Jenis / reaksi alergi' => $join($this->alergi_jenis, $this->alergi_reaksi),
            'Catatan Pengkajian' => $this->alasan_pengukuran_tidak_lengkap,
            'Nyeri dada' => $choice($this->nyeri_dada),
            'Kejang' => $choice($this->kejang),
            'Nadi teraba' => $choice($this->nadi_teraba),
            'Pola pernapasan' => $choice($this->pola_pernapasan),
            'Kesadaran' => $choice($this->kesadaran),
            'Kondisi psikiatri' => $choice($this->kondisi_psikiatri),
            'Risiko jatuh' => $choice($this->risiko_jatuh_visual),
            'Skala nyeri' => $this->skala_nyeri === null ? null : $this->skala_nyeri.' / 10',
            'Kebutuhan respons awal' => $response,
            'Catatan penapisan' => $this->catatan_triase,
            'Pengkajian gizi' => $choice($this->skrining_gizi),
            'Observasi awal' => $this->pemeriksaan_fisik,
            ...$this->dentalObservationSummary(),
            'Lokasi keluhan / nyeri' => $this->lokasi_nyeri_gigi,
            'Pemicu keluhan' => $choices($this->pemicu_nyeri_gigi),
            'Pola / lama keluhan' => $this->durasi_keluhan_gigi,
            'Kondisi kesehatan yang perlu diketahui dokter gigi' => $choices($this->risiko_medis_gigi),
            'Riwayat infeksi atau bengkak' => $choices($this->riwayat_infeksi_gigi),
            'Catatan untuk dokter gigi' => $this->catatan_medis_gigi,
        ];
    }
}
