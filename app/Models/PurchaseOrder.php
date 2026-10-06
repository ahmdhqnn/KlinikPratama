<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_order';

    protected $fillable = [
        'no_po',
        'depo_id',
        'supplier',
        'tanggal',
        'tanggal_kirim',
        'status',
        'catatan',
        'total',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_kirim' => 'date',
        'total' => 'decimal:2',
    ];

    public function depo(): BelongsTo
    {
        return $this->belongsTo(DepoObat::class, 'depo_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }
}
