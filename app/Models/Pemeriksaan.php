<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pemeriksaan extends Model
{
    protected $table = 'pemeriksaan';

    protected $casts = [
        'kontrol_berikutnya' => 'date',
        'signed_at' => 'datetime',
        'started_at' => 'datetime',
        'pemeriksaan_fisik_terstruktur' => 'array',
        'oral_hygiene_index' => 'decimal:2',
    ];

    protected $fillable = [
        'kunjungan_id', 'dokter_id', 'anamnesis',
        'pemeriksaan_fisik', 'catatan', 'edukasi', 'kontrol_berikutnya',
        'status', 'signed_by_user_id', 'signed_at', 'latest_note_hash',
        'started_at', 'riwayat_penyakit_sekarang', 'riwayat_penyakit_dahulu',
        'riwayat_penyakit_keluarga', 'riwayat_alergi', 'pemeriksaan_fisik_terstruktur',
        'pemeriksaan_ekstraoral', 'oral_hygiene_index', 'diagnosis_banding',
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
