<?php

namespace App\Models;

use App\ClinicDocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kunjungan extends Model
{
    public const POST_EXAMINATION_STATUSES = ['tindakan', 'farmasi', 'selesai'];

    public const STATUSES = ['menunggu', 'screening', 'pemeriksaan', ...self::POST_EXAMINATION_STATUSES, 'batal'];

    protected $table = 'kunjungan';

    protected $fillable = [
        'no_kunjungan', 'no_antrian', 'satusehat_encounter_id', 'pasien_id', 'poliklinik_id', 'ruang_poli_id', 'dokter_id',
        'tanggal', 'arrived_at', 'finished_at', 'status', 'jenis_pasien', 'catatan', 'hak_layanan_snapshot', 'verified_by', 'verified_at',
        'unit_kerja', 'kategori_peserta', 'tindakan_started_at',
        'kontrol_asal_id', 'kontrol_pending', 'kontrol_catatan', 'rujukan_internal_id',
        'registered_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'general_consent_required',
    ];

    protected $casts = ['tanggal' => 'date', 'arrived_at' => 'datetime', 'finished_at' => 'datetime', 'no_antrian' => 'integer', 'kontrol_pending' => 'boolean', 'hak_layanan_snapshot' => 'array', 'verified_at' => 'datetime', 'lampiran' => 'array', 'tindakan_started_at' => 'datetime'];

    protected $hidden = ['lampiran', 'kontrol_catatan'];

    /** @return array<int, array{id: string, name: string, note: ?string, size: int, mime: string, downloadUrl: string, deleteUrl: string}> */
    public function attachmentSummaries(bool $archived = false): array
    {
        return array_map(fn (array $attachment): array => [
            'id' => $attachment['id'],
            'name' => $attachment['name'],
            'note' => $attachment['note'],
            'size' => $attachment['size'],
            'mime' => $attachment['mime'],
            'downloadUrl' => route('pelayanan.kunjungan.attachments.show', [$this, $attachment['id'], ...($archived ? ['archive' => 1] : [])]),
            'deleteUrl' => route('pelayanan.kunjungan.attachments.destroy', [$this, $attachment['id']]),
            'restoreUrl' => route('pelayanan.kunjungan.attachments.restore', [$this, $attachment['id']]),
            'voidedAt' => $attachment['voided_at'] ?? null,
            'voidReason' => $attachment['void_reason'] ?? null,
        ], array_values(array_filter($this->lampiran ?? [], fn (array $attachment): bool => ! empty($attachment['voided_at']) === $archived)));
    }

    protected static function booted(): void
    {
        static::creating(function (Kunjungan $visit): void {
            if (! $visit->kontrol_pending && $visit->status !== 'batal' && $visit->arrived_at === null && (! $visit->tanggal || ! $visit->tanggal->isAfter(today()))) {
                $visit->arrived_at = now();
            }
            if ($visit->status === 'selesai' && $visit->finished_at === null) {
                $visit->finished_at = now();
            }
        });
        static::updating(function (Kunjungan $visit): void {
            if ($visit->isDirty('kontrol_pending') && ! $visit->kontrol_pending && $visit->arrived_at === null) {
                $visit->arrived_at = now();
            }
            if ($visit->isDirty('status') && $visit->status === 'selesai' && $visit->finished_at === null) {
                $visit->finished_at = now();
            }
        });
        static::deleting(fn () => throw new \LogicException('Kunjungan tidak boleh dihapus. Gunakan pembatalan beralasan.'));
    }

    public function ruangPoli(): BelongsTo
    {
        return $this->belongsTo(RuangPoli::class, 'ruang_poli_id');
    }

    public function generalConsent(): BelongsTo
    {
        return $this->belongsTo(GeneralConsent::class, 'general_consent_id');
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

    public function scopeForClinicType(Builder $query, ?string $type): Builder
    {
        return $query->when($type, fn (Builder $visits) => $visits->whereHas('poliklinik', fn (Builder $clinic) => $clinic->where('jenis', $type)));
    }

    public function scopeUpcomingReservations(Builder $query): Builder
    {
        return $query->where('status', 'menunggu')
            ->where('tanggal', '>=', today()->addDay()->toDateString())
            ->where('tanggal', '<', today()->addDays(8)->toDateString());
    }

    public function isPendingReservation(): bool
    {
        return $this->status === 'menunggu' && $this->kontrol_pending;
    }

    public function canUpdateScreening(): bool
    {
        if ($this->isPendingReservation() || $this->tanggal?->isFuture()) {
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

        return in_array($this->status, self::POST_EXAMINATION_STATUSES, true);
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

    public function rujukanMasuk(): BelongsTo
    {
        return $this->belongsTo(RujukanInternal::class, 'rujukan_internal_id');
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

    public static function nextQueueNumber(int $clinicId, string $date): int
    {
        return app(ClinicDocumentNumber::class)->nextQueueNumber($clinicId, substr($date, 0, 10));
    }
}
