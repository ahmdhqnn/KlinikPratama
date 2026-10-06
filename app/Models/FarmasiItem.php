<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmasiItem extends Model
{
    protected $table = 'farmasi_item';

    protected $fillable = [
        'farmasi_id',
        'resep_obat_id',
        'obat_id',
        'jumlah_diberikan',
        'aturan_pakai',
        'catatan',
    ];

    public function farmasi(): BelongsTo
    {
        return $this->belongsTo(Farmasi::class);
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function resepObat(): BelongsTo
    {
        return $this->belongsTo(ResepObat::class);
    }
}
