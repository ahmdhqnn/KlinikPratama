<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmasiResepLuar extends Model
{
    protected $table = 'farmasi_resep_luar';

    protected $fillable = ['resep_obat_id', 'petugas_id'];

    public function resepObat(): BelongsTo
    {
        return $this->belongsTo(ResepObat::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
