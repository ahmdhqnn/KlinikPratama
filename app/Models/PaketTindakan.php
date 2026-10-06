<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaketTindakan extends Model
{
    protected $table = 'paket_tindakan';

    protected $fillable = ['kode', 'nama', 'deskripsi', 'tarif', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'tarif' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PaketTindakanItem::class);
    }
}
