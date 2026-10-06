<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResepObat extends Model
{
    protected $table = 'resep_obat';

    protected $fillable = [
        'resep_id', 'obat_id', 'nama_obat', 'jumlah',
        'satuan', 'aturan_pakai', 'catatan', 'jenis', 'is_resep_luar',
    ];

    protected $casts = ['is_resep_luar' => 'boolean'];

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function farmasiItem(): HasMany
    {
        return $this->hasMany(FarmasiItem::class);
    }
}
