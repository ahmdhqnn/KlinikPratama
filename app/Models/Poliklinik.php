<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poliklinik extends Model
{
    use SoftDeletes;

    protected $table = 'poliklinik';

    protected $fillable = ['kode', 'nama', 'jenis', 'depo_obat_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeRegistrable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereIn('jenis', ['umum', 'gigi']);
    }

    public function depoObat(): BelongsTo
    {
        return $this->belongsTo(DepoObat::class, 'depo_obat_id');
    }

    public function ruangPoli(): HasMany
    {
        return $this->hasMany(RuangPoli::class);
    }

    public function tindakan(): HasMany
    {
        return $this->hasMany(Tindakan::class);
    }

    public function laboratorium(): HasMany
    {
        return $this->hasMany(Laboratorium::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function biayaPendaftaran(): HasMany
    {
        return $this->hasMany(BiayaPendaftaran::class);
    }
}
