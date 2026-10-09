<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalDokter extends Model
{
    protected $table = 'jadwal_dokter';

    protected $fillable = [
        'dokter_id',
        'poliklinik_id',
        'hari',
        'berlaku_mulai',
        'berlaku_sampai',
        'jam_mulai',
        'jam_selesai',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'berlaku_mulai' => 'date', 'berlaku_sampai' => 'date'];

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public static function getHariList(): array
    {
        return ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
    }

    public static function getDokterByPoliAndHari(int $poliklinik_id, string $hari)
    {
        return static::with('dokter')
            ->where('poliklinik_id', $poliklinik_id)
            ->where('hari', strtolower($hari))
            ->where('is_active', true)
            ->get()
            ->pluck('dokter');
    }
}
