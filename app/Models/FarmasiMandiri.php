<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmasiMandiri extends Model
{
    protected $table = 'farmasi_mandiris';

    protected $fillable = ['nomor', 'pasien_id', 'obat_id', 'petugas_id', 'jumlah', 'aturan_pakai', 'dasar_pemberian', 'catatan', 'diserahkan_pada'];

    protected $casts = ['diserahkan_pada' => 'datetime'];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
