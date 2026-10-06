<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tindakan extends Model
{
    use SoftDeletes;

    protected $table = 'tindakan';

    protected $fillable = [
        'kode', 'kode_icd9', 'nama', 'kategori', 'poliklinik_id',
        'tarif', 'tarif_dokter', 'tarif_asisten', 'tarif_klinik', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tarif' => 'decimal:2',
        'tarif_dokter' => 'decimal:2',
        'tarif_asisten' => 'decimal:2',
        'tarif_klinik' => 'decimal:2',
    ];

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function bhp(): HasMany
    {
        return $this->hasMany(TindakanBhp::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(TindakanKunjungan::class);
    }

    public function paketItems(): HasMany
    {
        return $this->hasMany(PaketTindakanItem::class);
    }

    public function informedConsent(): HasMany
    {
        return $this->hasMany(InformedConsent::class);
    }
}
