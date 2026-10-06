<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepoObat extends Model
{
    use SoftDeletes;

    protected $table = 'depo_obat';

    protected $fillable = ['kode', 'nama', 'deskripsi', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function poliklinik(): HasMany
    {
        return $this->hasMany(Poliklinik::class, 'depo_obat_id');
    }

    public function stokObat(): HasMany
    {
        return $this->hasMany(StokObat::class, 'depo_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'depo_id');
    }
}
