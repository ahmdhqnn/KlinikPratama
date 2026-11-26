<?php

namespace App\Models;

use App\Models\Concerns\RetainsCancelledClinicalEntries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResepObat extends Model
{
    use RetainsCancelledClinicalEntries;

    protected $table = 'resep_obat';

    protected $fillable = [
        'resep_id', 'obat_id', 'nama_obat', 'jumlah',
        'satuan', 'aturan_pakai', 'frekuensi', 'periode', 'periode_unit',
        'dosis_sekali_pakai', 'dosis_unit', 'rute_pemberian', 'waktu_pakai',
        'durasi_hari', 'satusehat_medication_request_id',
        'catatan', 'allergy_override_reason', 'allergy_matched_term',
        'jenis', 'is_resep_luar', 'stok_dikurangi',
    ];

    protected $casts = [
        'is_resep_luar' => 'boolean',
        'stok_dikurangi' => 'boolean',
        'frekuensi' => 'integer',
        'periode' => 'integer',
        'dosis_sekali_pakai' => 'decimal:2',
        'durasi_hari' => 'integer',
    ];

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function farmasiItem(): HasMany
    {
        return $this->hasMany(FarmasiItem::class);
    }

    public function farmasiResepLuar(): HasOne
    {
        return $this->hasOne(FarmasiResepLuar::class);
    }
}
