<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pemeriksaan extends Model
{
    protected $table = 'pemeriksaan';

    protected $fillable = [
        'kunjungan_id', 'dokter_id', 'anamnesis',
        'pemeriksaan_fisik', 'catatan', 'kontrol_berikutnya', 'status',
    ];

    protected $casts = ['kontrol_berikutnya' => 'date'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function diagnosa(): HasMany
    {
        return $this->hasMany(Diagnosa::class);
    }
}
