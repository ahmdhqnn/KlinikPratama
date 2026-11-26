<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmasiMandiri extends Model
{
    protected $table = 'farmasi_mandiris';

    protected $fillable = [
        'request_key', 'request_fingerprint', 'nomor', 'pasien_id', 'poliklinik_id', 'depo_id',
        'obat_id', 'petugas_id', 'jumlah', 'aturan_pakai', 'dasar_pemberian', 'catatan', 'diserahkan_pada',
    ];

    protected $casts = ['diserahkan_pada' => 'datetime'];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class)->withTrashed();
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function depo(): BelongsTo
    {
        return $this->belongsTo(DepoObat::class, 'depo_id');
    }

    public function scopeForClinic(Builder $query, ?int $poliklinikId): Builder
    {
        return $poliklinikId !== null ? $query->where('poliklinik_id', $poliklinikId) : $query;
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
