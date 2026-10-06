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
    ];

    protected $casts = ['tanggal' => 'date'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }
}
