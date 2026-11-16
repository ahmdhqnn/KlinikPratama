<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RujukanInternal extends Model
{
    protected $table = 'rujukan_internal';

    protected $fillable = ['kunjungan_id', 'dari_poli_id', 'ke_poli_id', 'surat_template_id', 'nomor_surat', 'konten_surat', 'catatan', 'status', 'document_data'];

    protected $casts = ['document_data' => 'array'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function kunjunganTujuan(): HasMany
    {
        return $this->hasMany(Kunjungan::class, 'rujukan_internal_id');
    }

    public function kunjunganTujuanTerakhir(): HasOne
    {
        return $this->hasOne(Kunjungan::class, 'rujukan_internal_id')->latestOfMany();
    }

    public function statusLayanan(): string
    {
        return match ($this->kunjunganTujuanTerakhir?->status) {
            'batal' => 'batal',
            'selesai' => 'selesai',
            'menunggu' => 'terdaftar',
            'screening', 'pemeriksaan', 'tindakan', 'farmasi' => 'dilayani',
            default => $this->status,
        };
    }

    public function canRegisterDestination(): bool
    {
        return $this->status === 'menunggu'
            && in_array($this->statusLayanan(), ['menunggu', 'batal'], true)
            && $this->kunjungan !== null && $this->kunjungan->status !== 'batal'
            && $this->kePoli?->is_active
            && in_array($this->kePoli->jenis, ['umum', 'gigi'], true);
    }

    public function dariPoli(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class, 'dari_poli_id');
    }

    public function kePoli(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class, 'ke_poli_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceTemplate::class, 'surat_template_id');
    }
}
