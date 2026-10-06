<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RujukanInternal extends Model
{
    protected $table = 'rujukan_internal';

    protected $fillable = ['kunjungan_id', 'dari_poli_id', 'ke_poli_id', 'catatan', 'status'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dariPoli(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class, 'dari_poli_id');
    }

    public function kePoli(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class, 'ke_poli_id');
    }
}
