<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Laboratorium extends Model
{
    use SoftDeletes;

    protected $table = 'laboratorium';

    protected $fillable = ['kode', 'nama', 'poliklinik_id', 'tarif', 'deskripsi', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'tarif' => 'decimal:2',
    ];

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(LabIndikator::class);
    }

    public function bhp(): HasMany
    {
        return $this->hasMany(LabBhp::class);
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(LabHasil::class);
    }

    public function paketItems(): HasMany
    {
        return $this->hasMany(PaketTindakanItem::class);
    }
}
