<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObatBatch extends Model
{
    use HasFactory;

    protected $table = 'obat_batch';

    protected $fillable = ['obat_id', 'depo_id', 'nomor_batch', 'expired_at', 'stok', 'harga_beli', 'status', 'sumber', 'referensi'];

    protected $casts = ['expired_at' => 'date', 'stok' => 'decimal:2', 'harga_beli' => 'decimal:2'];

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function depo(): BelongsTo
    {
        return $this->belongsTo(DepoObat::class, 'depo_id');
    }

    public function scopeUsable(Builder $query): void
    {
        $query->where('status', 'tersedia')->where('stok', '>', 0)->whereDate('expired_at', '>', today());
    }
}
