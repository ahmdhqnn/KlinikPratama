<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiayaPendaftaran extends Model
{
    protected $table = 'biaya_pendaftaran';

    protected $fillable = ['poliklinik_id', 'dokter_id', 'jenis_pasien', 'tarif'];

    protected $casts = ['tarif' => 'decimal:2'];

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }
}
