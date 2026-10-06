<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabIndikator extends Model
{
    protected $table = 'lab_indikator';

    protected $fillable = [
        'laboratorium_id', 'nama', 'satuan',
        'nilai_rujukan_min', 'nilai_rujukan_max',
        'format_input', 'pilihan', 'urutan',
    ];

    protected $casts = ['pilihan' => 'array'];

    public function laboratorium(): BelongsTo
    {
        return $this->belongsTo(Laboratorium::class);
    }

    public function hasilDetail(): HasMany
    {
        return $this->hasMany(LabHasilDetail::class, 'indikator_id');
    }
}
