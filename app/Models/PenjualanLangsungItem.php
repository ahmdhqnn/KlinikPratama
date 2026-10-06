<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenjualanLangsungItem extends Model
{
    protected $table = 'penjualan_langsung_item';

    protected $fillable = [
        'penjualan_langsung_id',
        'obat_id',
        'jumlah',
        'harga',
        'total',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'total' => 'decimal:2',
        'jumlah' => 'decimal:2',
    ];

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(PenjualanLangsung::class, 'penjualan_langsung_id');
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }
}
