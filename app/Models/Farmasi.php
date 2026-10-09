<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmasi extends Model
{
    protected $table = 'farmasi';

    protected $fillable = ['kunjungan_id', 'resep_id', 'petugas_id', 'status', 'catatan', 'dispensed_by', 'dispensed_at'];

    protected $casts = ['dispensed_at' => 'datetime'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'petugas_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FarmasiItem::class);
    }
}
