<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosa extends Model
{
    protected $table = 'diagnosa';

    protected $fillable = ['pemeriksaan_id', 'kode_icd10', 'nama_diagnosa', 'jenis'];

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(Pemeriksaan::class);
    }
}
