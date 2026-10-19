<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakanKunjungan extends Model
{
    protected $table = 'tindakan_kunjungan';

    protected $fillable = [
        'kunjungan_id', 'tindakan_id', 'nama_tindakan_manual', 'dokter_id',
        'nama_tindakan', 'kode_icd9cm', 'code_system', 'code_release',
        'jumlah', 'tooth_fdi', 'catatan', 'bhp_consumed_at',
    ];

    protected $casts = [
    ];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }
}
