<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenjualanLangsung extends Model
{
    protected $table = 'penjualan_langsung';

    protected $fillable = [
        'no_transaksi',
        'tanggal',
        'kasir_id',
        'nama_pembeli',
        'total',
        'bayar',
        'kembalian',
        'metode_bayar',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
    ];

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'kasir_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PenjualanLangsungItem::class);
    }
}
