<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanItem extends Model
{
    protected $table = 'tagihan_item';

    protected $fillable = [
        'tagihan_id',
        'jenis',
        'referensi_id',
        'nama',
        'jumlah',
        'tarif',
        'total',
    ];

    protected $casts = [
        'tarif' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }
}
