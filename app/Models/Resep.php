<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resep extends Model
{
    protected $table = 'resep';

    protected $fillable = ['no_resep', 'kunjungan_id', 'dokter_id', 'status', 'is_resep_luar'];

    protected $casts = ['is_resep_luar' => 'boolean'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function resepObat(): HasMany
    {
        return $this->hasMany(ResepObat::class);
    }
}
