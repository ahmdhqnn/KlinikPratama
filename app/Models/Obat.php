<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Obat extends Model
{
    use SoftDeletes;

    protected $table = 'obat';

    protected $fillable = [
        'kode', 'kode_kfa', 'nama', 'satuan_besar', 'satuan_kecil',
        'konversi_satuan', 'harga_beli', 'harga_jual', 'indikasi',
        'kandungan', 'stok', 'stok_minimum', 'jenis', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
    ];

    public function stokObat(): HasMany
    {
        return $this->hasMany(StokObat::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ObatBatch::class);
    }

    public function tindakanBhp(): HasMany
    {
        return $this->hasMany(TindakanBhp::class);
    }

    public function asuransiHarga(): HasMany
    {
        return $this->hasMany(AsuransiHarga::class);
    }

    public function stokMutasi(): HasMany
    {
        return $this->hasMany(StokMutasi::class);
    }

    public function labBhp(): HasMany
    {
        return $this->hasMany(LabBhp::class);
    }
}
