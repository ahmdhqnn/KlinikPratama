<?php

namespace App\Models;

use App\ClinicDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kunjungan extends Model
{
    protected $table = 'kunjungan';

    protected $fillable = [
        'no_kunjungan', 'pasien_id', 'poliklinik_id', 'dokter_id',
        'tanggal', 'status', 'jenis_pasien', 'catatan', 'hak_layanan_snapshot', 'verified_by', 'verified_at',
        'unit_kerja', 'kategori_peserta', 'tindakan_started_at',
    ];

    protected $casts = ['tanggal' => 'date', 'hak_layanan_snapshot' => 'array', 'verified_at' => 'datetime', 'lampiran' => 'array', 'tindakan_started_at' => 'datetime'];

    protected $hidden = ['lampiran'];

    /** @return array<int, array{id: string, name: string, note: ?string, size: int, mime: string, downloadUrl: string, deleteUrl: string}> */
    public function attachmentSummaries(): array
    {
        return array_map(fn (array $attachment): array => [
            'id' => $attachment['id'],
            'name' => $attachment['name'],
            'note' => $attachment['note'],
            'size' => $attachment['size'],
            'mime' => $attachment['mime'],
            'downloadUrl' => route('pelayanan.kunjungan.attachments.show', [$this, $attachment['id']]),
            'deleteUrl' => route('pelayanan.kunjungan.attachments.destroy', [$this, $attachment['id']]),
        ], $this->lampiran ?? []);
    }

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

    public function screening(): HasOne
    {
        return $this->hasOne(Screening::class);
    }

    public function canUpdateScreening(): bool
    {
        if ($this->tanggal?->isFuture()) {
            return false;
        }

        if (in_array($this->status, ['menunggu', 'screening'], true)) {
            return true;
        }

        return $this->status === 'pemeriksaan'
            && $this->screening?->submitted_at !== null
            && $this->pemeriksaan?->started_at === null
            && $this->pemeriksaan?->signed_at === null;
    }

    public function canOpenExamination(): bool
    {
        if ($this->status === 'pemeriksaan') {
            return $this->screening?->submitted_at !== null;
        }

        return in_array($this->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true);
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

        return app(ClinicDocumentNumber::class)->next('kunjungan-'.$date, 'KNJ-'.$date.'-', 4, 'kunjungan', 'no_kunjungan');
    }
}
