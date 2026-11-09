<?php

namespace App\Models;

use App\DentalAssessment;
use App\GeneralAssessment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Screening extends Model
{
    public const SAFETY_FIELDS = ['dental_red_flags_other', 'risiko_medis_gigi_lainnya', 'pemicu_nyeri_gigi_lainnya', 'riwayat_infeksi_gigi_lainnya', 'dental_medication_categories_other', 'alergi_keparahan', 'alergi_verifikasi', 'nyeri_karakter', 'nyeri_pereda', 'nyeri_dampak', 'riwayat_kondisi_khusus', 'observasi_mulut', 'kebiasaan_perawatan_mulut'];

    public const OTHER_DETAILS = [
        'dental_red_flags' => ['other', 'dental_red_flags_other'],
        'risiko_medis_gigi' => ['lainnya', 'risiko_medis_gigi_lainnya'],
        'pemicu_nyeri_gigi' => ['lainnya', 'pemicu_nyeri_gigi_lainnya'],
        'riwayat_infeksi_gigi' => ['infeksi_lain', 'riwayat_infeksi_gigi_lainnya'],
        'dental_medication_categories' => ['other', 'dental_medication_categories_other'],
    ];

    public const ORAL_AREAS = ['lidah' => 'Lidah', 'pipi' => 'Pipi', 'bibir' => 'Bibir', 'palatum' => 'Palatum', 'gusi' => 'Gusi'];

    public const ORAL_HABITS = [
        'teh_kopi' => 'Minum teh atau kopi', 'alkohol' => 'Minum minuman beralkohol',
        'soda' => 'Minum minuman bersoda', 'merokok' => 'Merokok', 'satu_sisi' => 'Mengunyah satu sisi',
        'sirih_tembakau' => 'Mengunyah sirih atau tembakau', 'benda_keras' => 'Menggigit benda keras',
        'bruksisme' => 'Menggemeretakkan gigi (bruksisme)', 'lainnya' => 'Lainnya',
        'tidak_ada' => 'Tidak ada kebiasaan yang dilaporkan', 'belum_diketahui' => 'Belum diketahui',
    ];

    protected $table = 'screening';

    protected $fillable = [
        'submitted_by_user_id', 'submitted_petugas_id', 'last_submitted_at',
        'vitals_unmeasurable', 'general_assessment', 'vitals_measured_at', 'triaged_at',
        'status_riwayat_obat', 'mukosa_mulut', 'kelainan_mukosa', 'kebiasaan_gigi_opsi',
        ...self::SAFETY_FIELDS,
        'dental_vitals_unmeasurable',
        'dental_medication_categories',
        'riwayat_perawatan_gigi', 'kebiasaan_gigi',
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
        'vitals_unmeasurable' => 'boolean', 'general_assessment' => 'array', 'vitals_measured_at' => 'datetime', 'triaged_at' => 'datetime',
        'mukosa_mulut' => 'array', 'kebiasaan_gigi_opsi' => 'array',
        'dental_vitals_unmeasurable' => 'boolean',
        'dental_medication_categories' => 'array',
        'dental_red_flags' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_submitted_at' => 'datetime',
        'lingkar_perut' => 'decimal:1',
        'skala_nyeri' => 'integer',
        'pemicu_nyeri_gigi' => 'array',
        'risiko_medis_gigi' => 'array',
        'riwayat_infeksi_gigi' => 'array',
    ];

    /** @return array<string, string> */
    public static function dentalMedicationCategories(): array
    {
        return [
            'anticoagulant' => 'Antikoagulan',
            'antiplatelet' => 'Antiplatelet',
            'antidiabetic' => 'Obat diabetes',
            'antihypertensive' => 'Antihipertensi',
            'other' => 'Obat lain',
            'none' => 'Tidak ada menurut pasien',
            'unknown' => 'Belum diketahui',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScreeningVersion::class)->orderBy('version');
    }

    public function generalAssessmentSummary(): array
    {
        return GeneralAssessment::nursingSummary($this->general_assessment);
    }

    public function versionSummaries(): array
    {
        $versions = $this->versions()->with('actor')->get();
        if ($versions->isEmpty()) {
            return [];
        }
        $staff = Nakes::whereIn('id', $versions->pluck('payload.petugas_id')->filter()->unique())->get()->keyBy('id');
        $submitters = User::whereIn('id', $versions->pluck('payload.submitted_by_user_id')->filter()->unique())->get()->keyBy('id');

        return $versions->map(function (ScreeningVersion $version) use ($staff, $submitters): array {
            $snapshot = new self($version->payload);
            $snapshot->setRelation('kunjungan', $this->kunjungan);
            $snapshot->setRelation('petugas', $staff->get($snapshot->petugas_id));
            $snapshot->setRelation('submittedBy', $submitters->get($snapshot->submitted_by_user_id));

            return [
                'version' => $version->version, 'reason' => $version->reason,
                'recordedAt' => $version->recorded_at?->format('d/m/Y H:i'),
                'actor' => $version->actor?->name,
                'summary' => [
                    'Keluhan utama' => $snapshot->keluhan,
                    'Tekanan darah' => $snapshot->td_sistole !== null ? $snapshot->td_sistole.'/'.$snapshot->td_diastole.' mmHg' : null,
                    'Nadi' => $snapshot->nadi !== null ? $snapshot->nadi.' kali/menit' : null,
                    'Suhu' => $snapshot->suhu !== null ? $snapshot->suhu.' °C' : null,
                    'Frekuensi napas' => $snapshot->respirasi !== null ? $snapshot->respirasi.' kali/menit' : null,
                    'SpO₂' => $snapshot->spo2 !== null ? $snapshot->spo2.'%' : null,
                    ...$snapshot->handoverSummary(),
                ],
            ];
        })->all();
    }

    public function revisionToken(): string
    {
        return hash('sha256', serialize([$this->getRawOriginal(), $this->versions()->max('version')]));
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
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

    public function reportedMedicalHistory(): ?string
    {
        return match ($this->status_riwayat_penyakit) {
            'belum_diketahui' => null,
            'tidak_ada' => 'Pasien menyangkal riwayat penyakit',
            default => $this->riwayat_penyakit ?: $this->reportedDiseaseChoices(),
        };
    }

    private function reportedDiseaseChoices(): ?string
    {
        $labels = ['hipertensi' => 'Hipertensi', 'diabetes' => 'Diabetes melitus', 'penyakit_jantung' => 'Penyakit jantung', 'gangguan_pembekuan' => 'Gangguan pembekuan darah', 'antikoagulan' => 'Penggunaan antikoagulan', 'lainnya' => $this->risiko_medis_gigi_lainnya];
        $reported = array_filter(array_map(static fn (string $value): ?string => $labels[$value] ?? null, $this->risiko_medis_gigi ?? []));

        return $reported ? implode(', ', $reported) : null;
    }

    private function hasReportedDiseaseDetails(): bool
    {
        return ! in_array($this->status_riwayat_penyakit, ['tidak_ada', 'belum_diketahui'], true);
    }

    public function vitalMeasurementSummary(): ?string
    {
        // Describe recorded measurements only; this is not a claim of clinical normality.
        $fields = ['td_sistole', 'td_diastole', 'nadi', 'suhu', 'respirasi'];
        $recorded = count(array_filter($fields, fn (string $field): bool => $this->getAttribute($field) !== null));
        if ($recorded === count($fields)) {
            return 'Tekanan darah, nadi, suhu, dan frekuensi napas tercatat';
        }
        if ($this->dental_vitals_unmeasurable || $this->vitals_unmeasurable) {
            return $recorded > 0 ? 'Sebagian TTV tidak dapat diukur' : 'TTV tidak dapat diukur';
        }

        return $recorded > 0 ? 'Pengukuran TTV belum lengkap' : null;
    }

    /** @return array<string, ?string> */
    public function mucosaSiteSummary(): array
    {
        $statuses = ['normal' => 'Normal', 'abnormal' => 'Abnormal', 'belum_dinilai' => 'Belum dapat dinilai'];

        return collect(self::ORAL_AREAS)->mapWithKeys(function (string $label, string $site) use ($statuses): array {
            $status = $statuses[$this->mukosa_mulut[$site] ?? ''] ?? null;
            $note = $this->mukosa_mulut['catatan'][$site] ?? null;

            return ['Observasi mukosa - '.$label => filled($note) ? ($status ?? 'Belum diamati').' — '.$note : $status];
        })->all();
    }

    public function handoverSummary(): array
    {
        $labels = [
            'belum_dikonfirmasi' => 'Belum dapat dikonfirmasi', 'ada' => 'Ada riwayat', 'tidak_ada' => 'Tidak ada, telah dikaji', 'belum_diketahui' => 'Belum dapat diketahui',
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

        $details = [];
        foreach (self::SAFETY_FIELDS as $field) {
            $details[$field] = $this->getAttribute($field);
        }
        foreach (self::OTHER_DETAILS as $source => [$option, $detail]) {
            if (! in_array($option, $this->getAttribute($source) ?? [], true)) {
                $details[$detail] = null;
            }
        }

        return [
            ...($this->kunjungan?->poliklinik?->jenis === 'umum' ? $this->generalAssessmentSummary() : GeneralAssessment::handoverSummary($this->general_assessment)),
            'Pengirim pengkajian pertama' => $this->submittedBy?->name,
            'Waktu pengiriman pertama' => $this->submitted_at?->format('d/m/Y H:i'),
            'Waktu pengiriman terakhir' => $this->last_submitted_at?->format('d/m/Y H:i'),
            'Waktu pencatatan TTV' => $this->vitals_measured_at?->format('d/m/Y H:i'),
            'Petugas pengkajian TTV' => $this->vitals_measured_at ? $this->petugas?->nama : null,
            'Waktu pencatatan triase' => $this->triaged_at?->format('d/m/Y H:i'),
            ...array_combine(['Keterangan tanda bahaya lainnya', 'Keterangan kondisi sistemik lainnya', 'Keterangan pemicu lainnya', 'Keterangan infeksi lainnya', 'Keterangan obat lainnya', 'Tingkat keparahan alergi jika diketahui', 'Catatan verifikasi alergi', 'Karakter dan penjalaran nyeri', 'Hal yang mengurangi nyeri', 'Dampak nyeri pada aktivitas atau tidur', 'Kondisi khusus, operasi, atau rawat inap yang relevan', 'Observasi awal mulut dan rahang', 'Perawatan mulut dan kebiasaan sehari-hari'], array_values($details)),
            'Status penggunaan obat' => $choice($this->status_riwayat_obat),
            ...$this->mucosaSiteSummary(),
            (in_array('abnormal', $this->mukosa_mulut ?? [], true) ? 'Kelainan mukosa yang ditemukan' : 'Observasi mukosa - Catatan tambahan') => $this->kelainan_mukosa,
            'Kebiasaan gigi dan mulut yang dilaporkan' => $this->kebiasaan_gigi_opsi ? implode(', ', array_map(fn (string $value): string => self::ORAL_HABITS[$value] ?? $value, $this->kebiasaan_gigi_opsi)) : null,
            'Keluhan tambahan' => $this->keluhan_tambahan,
            'Lama keluhan' => $this->lama_keluhan,
            'Sumber informasi' => $choice($this->sumber_informasi),
            'Status riwayat penyakit' => $choice($this->status_riwayat_penyakit),
            'Nama penyakit' => $this->hasReportedDiseaseDetails() ? $this->penyakit_nama : null,
            'Keterangan riwayat penyakit' => $this->hasReportedDiseaseDetails() ? $this->penyakit_keterangan : null,
            'Obat yang digunakan' => match ($this->status_riwayat_obat) {
                'tidak_ada' => 'Tidak menggunakan obat menurut pasien',
                'belum_diketahui' => 'Belum dapat dikonfirmasi',
                default => $this->riwayat_obat,
            },
            'Kategori obat yang dilaporkan' => $this->dental_medication_categories
                ? implode(', ', array_intersect_key(self::dentalMedicationCategories(), array_flip($this->dental_medication_categories)))
                : null,
            'Status alergi saat ini' => $choice($this->status_alergi),
            'Jenis / reaksi alergi' => $join($this->alergi_jenis, $this->alergi_reaksi),
            'Status pengukuran TTV' => $this->vitalMeasurementSummary(),
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
            'Riwayat perawatan gigi' => $this->riwayat_perawatan_gigi,
            'Kebiasaan terkait gigi dan mulut' => $this->kebiasaan_gigi_opsi ? implode(', ', array_map(fn (string $value): string => self::ORAL_HABITS[$value] ?? $value, $this->kebiasaan_gigi_opsi)).(filled($this->kebiasaan_gigi) ? ' — '.$this->kebiasaan_gigi : '') : $this->kebiasaan_gigi,
            'Lokasi keluhan / nyeri' => $this->lokasi_nyeri_gigi,
            'Pemicu keluhan' => $choices($this->pemicu_nyeri_gigi),
            'Pola / lama keluhan' => $this->durasi_keluhan_gigi,
            'Kondisi kesehatan yang perlu diketahui dokter gigi' => $choices($this->risiko_medis_gigi),
            'Riwayat infeksi atau bengkak' => $choices($this->riwayat_infeksi_gigi),
            'Catatan untuk dokter gigi' => $this->catatan_medis_gigi,
        ];
    }
}
