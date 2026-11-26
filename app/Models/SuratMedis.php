<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratMedis extends Model
{
    protected $table = 'surat_medis';

    protected $fillable = [
        'kunjungan_id', 'dokter_id', 'surat_template_id', 'jenis',
        'konten', 'tanggal', 'nomor_surat', 'document_data', 'satusehat_service_request_id',
    ];

    protected $casts = ['tanggal' => 'date', 'document_data' => 'array'];

    public function scopeExternalReferral(Builder $query): Builder
    {
        return $query->where('surat_medis.jenis', 'rujukan')->whereNotExists(fn ($events) => $events
            ->from('medical_document_verification_events as events')
            ->join('medical_document_verifications as verification', 'verification.id', '=', 'events.verification_id')
            ->whereColumn('verification.surat_medis_id', 'surat_medis.id')
            ->whereIn('events.action', ['void', 'replaced']));
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceTemplate::class, 'surat_template_id');
    }
}
