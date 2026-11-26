<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Obat extends Model
{
    use SoftDeletes;

    protected $table = 'obat';

    protected $fillable = [
        'kode', 'kode_kfa', 'kode_kfa_poa', 'kode_kfa_pov', 'nama_kfa',
        'kode_bentuk_sediaan', 'nama_bentuk_sediaan', 'satusehat_medication_id',
        'nama', 'satuan_besar', 'satuan_kecil',
        'konversi_satuan', 'indikasi',
        'kandungan', 'stok', 'stok_minimum', 'jenis', 'is_active', 'boleh_diberikan_langsung',
        'is_high_alert', 'is_lasa', 'rute_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'boleh_diberikan_langsung' => 'boolean',
        'is_high_alert' => 'boolean',
        'is_lasa' => 'boolean',
    ];

    public function scopeLowUsableStock(Builder $query, ?int $depoId = null): Builder
    {
        return $query->where(ObatBatch::query()->usable()->forDepo($depoId)->whereColumn('obat_batch.obat_id', 'obat.id')
            ->selectRaw('COALESCE(SUM(stok), 0)'), '<=', DB::raw('obat.stok_minimum'));
    }

    public function scopeWithStockAlert(Builder $query, ?int $depoId = null): Builder
    {
        return $query->where('is_active', true)->where(fn (Builder $items) => $items
            ->lowUsableStock($depoId)
            ->orWhereHas('batches', fn (Builder $batch) => $batch->usable()->forDepo($depoId)->whereBetween('expired_at', [today()->addDay()->toDateString(), today()->addDays(90)->toDateString()])));
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
