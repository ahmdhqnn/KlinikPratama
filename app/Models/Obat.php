<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Obat extends Model
{
    use SoftDeletes;

    protected $table = 'obat';

    protected $fillable = [
        'kode', 'kode_kfa', 'nama', 'satuan_besar', 'satuan_kecil',
        'konversi_satuan', 'indikasi',
        'kandungan', 'stok', 'stok_minimum', 'jenis', 'is_active', 'boleh_diberikan_langsung',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'boleh_diberikan_langsung' => 'boolean',
    ];

    public function scopeLowUsableStock(Builder $query): Builder
    {
        return $query->where(ObatBatch::query()->usable()->whereColumn('obat_batch.obat_id', 'obat.id')
            ->selectRaw('COALESCE(SUM(stok), 0)'), '<=', DB::raw('obat.stok_minimum'));
    }

    public function scopeWithStockAlert(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(fn (Builder $items) => $items
            ->lowUsableStock()
            ->orWhereHas('batches', fn (Builder $batch) => $batch->usable()->whereBetween('expired_at', [today()->addDay()->toDateString(), today()->addDays(90)->toDateString()])));
    }

    public function stokObat(): HasMany
    {
        return $this->hasMany(StokObat::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ObatBatch::class);
    }

    public function tindakanBhp(): HasMany
    {
        return $this->hasMany(TindakanBhp::class);
    }

    public function stokMutasi(): HasMany
    {
        return $this->hasMany(StokMutasi::class);
    }
}
