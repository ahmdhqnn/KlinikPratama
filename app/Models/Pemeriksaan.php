<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pemeriksaan extends Model
{
    protected $table = 'pemeriksaan';

    protected $casts = [
        'supporting_exams' => 'array', 'review_of_systems' => 'array', 'clinical_assessment' => 'array',
        'kontrol_berikutnya' => 'date',
        'signed_at' => 'datetime',
        'started_at' => 'datetime',
        'pemeriksaan_fisik_terstruktur' => 'array',
        'body_map' => 'array',
        'oral_hygiene_index' => 'decimal:2',
        'td_sistole_ulang' => 'integer',
        'td_diastole_ulang' => 'integer',
        'nadi_ulang' => 'integer',
        'suhu_ulang' => 'decimal:1',
        'coding_review_due_at' => 'datetime',
    ];

    protected $fillable = [
        'supporting_exams', 'review_of_systems', 'clinical_assessment', 'riwayat_sosial', 'kondisi_akhir', 'kondisi_akhir_snomed', 'disposisi', 'discharge_disposition_code',
        'riwayat_perawatan_gigi', 'kebiasaan_gigi', 'riwayat_obat',
        'kunjungan_id', 'dokter_id', 'anamnesis',
        'pemeriksaan_fisik', 'td_sistole_ulang', 'td_diastole_ulang', 'nadi_ulang', 'suhu_ulang',
        'ringkasan_odontogram', 'catatan', 'edukasi', 'non_farmakologis', 'kontrol_berikutnya',
        'status', 'signed_by_user_id', 'signed_at', 'signature_path_snapshot', 'latest_note_hash',
        'started_at', 'riwayat_penyakit_sekarang', 'riwayat_penyakit_dahulu',
        'riwayat_penyakit_keluarga', 'riwayat_alergi', 'pemeriksaan_fisik_terstruktur',
        'pemeriksaan_ekstraoral', 'oral_hygiene_index', 'diagnosis_banding', 'prognosis_code', 'body_map',
        'coding_status', 'coding_unresolved_reason', 'coding_review_due_at',
        'satusehat_clinical_impression_id', 'satusehat_composition_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pemeriksaan $examination): void {
            $examination->started_at ??= now();
        });
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function diagnosa(): HasMany
    {
        return $this->hasMany(Diagnosa::class);
    }

    public function clinicalNoteVersions(): HasMany
    {
        return $this->hasMany(ClinicalNoteVersion::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
