<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratMedis extends Model
{
    protected $table = 'surat_medis';

    protected $fillable = [
        'kunjungan_id', 'dokter_id', 'surat_template_id', 'jenis',
        'konten', 'tanggal', 'nomor_surat',
    ];

    protected $casts = ['tanggal' => 'date'];

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
