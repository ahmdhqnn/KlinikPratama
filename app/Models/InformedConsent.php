<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformedConsent extends Model
{
    protected $table = 'informed_consent';

    protected $fillable = [
        'kunjungan_id',
        'tindakan_id',
        'jenis',
        'isi',
        'nama_penandatangan',
        'hubungan_pasien',
        'tanggal',
        'tindakan_kunjungan_id', 'supersedes_id', 'request_id', 'request_hash', 'decision', 'text_version',
        'procedure_snapshot', 'plan_hash', 'explanation', 'representative_verification', 'witness_name', 'reason',
        'evidence_path', 'evidence_name', 'evidence_sha256', 'recorded_by', 'recorded_at',
    ];

    protected $casts = ['tanggal' => 'date', 'procedure_snapshot' => 'array', 'explanation' => 'array', 'recorded_at' => 'datetime'];

    protected $hidden = ['evidence_path'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Bukti persetujuan tindakan tidak boleh ditimpa.'));
        static::deleting(fn () => throw new \LogicException('Bukti persetujuan tindakan tidak boleh dihapus.'));
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TindakanKunjungan::class, 'tindakan_kunjungan_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
