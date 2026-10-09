<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokMutasi extends Model
{
    protected $table = 'stok_mutasi';

    protected $fillable = [
        'obat_id',
        'depo_id',
        'jenis',
        'referensi_type',
        'referensi_id',
        'jumlah',
        'harga',
        'stok_sebelum',
        'stok_sesudah',
        'keterangan',
        'batch_id', 'actor_id', 'kunjungan_id', 'farmasi_item_id', 'mutasi_asal_id',
        'cost_center', 'batch_stok_sebelum', 'batch_stok_sesudah',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'harga' => 'decimal:2',
        'stok_sebelum' => 'decimal:2',
        'stok_sesudah' => 'decimal:2',
    ];

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ObatBatch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function depo(): BelongsTo
    {
        return $this->belongsTo(DepoObat::class, 'depo_id');
    }
}
