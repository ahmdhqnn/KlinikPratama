<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class PatientDocument extends Model
{
    public const CATEGORY_RME_LAMA = 'rme_lama';

    public const CATEGORY_HASIL_LAB = 'hasil_lab';

    public const CATEGORY_RADIOLOGI_UMUM = 'radiologi_umum';

    public const CATEGORY_RADIOLOGI_GIGI = 'radiologi_gigi';

    public const CATEGORY_SURAT_RUJUKAN = 'surat_rujukan_eksternal';

    public const CATEGORY_RESUME_MEDIS = 'resume_medis_luar';

    public const CATEGORY_HASIL_MCU = 'hasil_mcu';

    public const CATEGORY_DOKUMEN_IDENTITAS = 'dokumen_identitas';

    public const CATEGORY_LAINNYA = 'lainnya';

    public const SENSITIVITY_CLINICAL = 'clinical';

    public const SENSITIVITY_ADMINISTRATIVE = 'administrative';

    /** @var array<string, array{label: string, sensitivity: string, loinc: ?string}> */
    public const CATEGORY_META = [
        self::CATEGORY_RME_LAMA => ['label' => 'Rekam Medis Sebelumnya', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '34133-9'],
        self::CATEGORY_HASIL_LAB => ['label' => 'Hasil Laboratorium Luar', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '11502-2'],
        self::CATEGORY_RADIOLOGI_UMUM => ['label' => 'Hasil Radiologi Umum (Rontgen / USG / EKG)', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '18748-4'],
        self::CATEGORY_RADIOLOGI_GIGI => ['label' => 'Radiologi Gigi (Panoramik / Periapikal / CBCT)', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '18748-4'],
        self::CATEGORY_SURAT_RUJUKAN => ['label' => 'Surat Rujukan / Balasan Rujukan Eksternal', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '57133-1'],
        self::CATEGORY_RESUME_MEDIS => ['label' => 'Resume Medis Fasyankes Lain', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '18842-5'],
        self::CATEGORY_HASIL_MCU => ['label' => 'Hasil Medical Check-Up (MCU)', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => '51845-6'],
        self::CATEGORY_DOKUMEN_IDENTITAS => ['label' => 'Dokumen Identitas / Kepesertaan / Persetujuan', 'sensitivity' => self::SENSITIVITY_ADMINISTRATIVE, 'loinc' => '64290-0'],
        self::CATEGORY_LAINNYA => ['label' => 'Dokumen Penunjang Lainnya', 'sensitivity' => self::SENSITIVITY_CLINICAL, 'loinc' => null],
    ];

    /** @var array<string, array{label: string, sensitivity: string, loinc: ?string}> */
    public const CATEGORIES = self::CATEGORY_META;

    protected $table = 'patient_documents';

    protected $fillable = [
        'uuid',
        'pasien_id',
        'kunjungan_id',
        'poliklinik_id',
        'category',
        'sensitivity',
        'title',
        'document_date',
        'issuer_facility',
        'tooth_fdi',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'sha256',
        'notes',
        'uploaded_by',
        'archived_at',
        'archived_by',
        'archive_reason',
        'loinc_document_type',
        'satusehat_document_reference_id',
    ];

    protected $casts = [
        'document_date' => 'date',
        'file_size' => 'integer',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PatientDocument $document): void {
            $document->uuid ??= (string) Str::uuid();
            $meta = self::CATEGORY_META[$document->category] ?? null;
            if ($meta !== null) {
                $document->sensitivity ??= $meta['sensitivity'];
                $document->loinc_document_type ??= $meta['loinc'];
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('Dokumen pasien tidak boleh dihapus permanen; gunakan pengarsipan dengan alasan.');
        });
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return array_map(static fn (array $meta): string => $meta['label'], self::CATEGORY_META);
    }

    public static function sensitivityForCategory(string $category): string
    {
        return self::CATEGORY_META[$category]['sensitivity'] ?? self::SENSITIVITY_CLINICAL;
    }

    public static function loincForCategory(string $category): ?string
    {
        return self::CATEGORY_META[$category]['loinc'] ?? null;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_META[$this->category]['label'] ?? $this->category;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class)->withTrashed();
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
