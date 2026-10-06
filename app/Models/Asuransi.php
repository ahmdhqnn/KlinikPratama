<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asuransi extends Model
{
    use SoftDeletes;

    protected $table = 'asuransi';

    protected $fillable = [
        'kode', 'nama', 'jenis', 'alamat', 'telepon', 'catatan', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function pasien(): HasMany
    {
        return $this->hasMany(Pasien::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function asuransiHarga(): HasMany
    {
        return $this->hasMany(AsuransiHarga::class);
    }
}
