<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    protected $table = 'tagihan';

    protected $fillable = [
        'no_tagihan', 'kunjungan_id', 'kasir_id',
        'subtotal', 'diskon', 'total', 'bayar', 'kembalian',
        'metode_bayar', 'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
    ];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'kasir_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }
}
