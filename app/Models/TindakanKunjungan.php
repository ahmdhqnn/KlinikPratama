<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakanKunjungan extends Model
{
    protected $table = 'tindakan_kunjungan';

    protected $fillable = [
        'kunjungan_id', 'tindakan_id', 'nama_tindakan_manual', 'dokter_id',
        'jumlah', 'tarif', 'tarif_dokter', 'tooth_fdi', 'catatan', 'bhp_consumed_at',
    ];

    protected $casts = [
        'tarif' => 'decimal:2',
        'tarif_dokter' => 'decimal:2',
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
