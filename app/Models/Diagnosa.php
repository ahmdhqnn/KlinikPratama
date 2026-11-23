<?php

namespace App\Models;

use App\Models\Concerns\RetainsCancelledClinicalEntries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosa extends Model
{
    use RetainsCancelledClinicalEntries;

    protected $table = 'diagnosa';

    protected $fillable = ['pemeriksaan_id', 'kode_icd10', 'code_system', 'code_release', 'nama_diagnosa', 'keterangan', 'jenis', 'tooth_fdi', 'surfaces', 'source_finding_code', 'coding_status', 'coding_unresolved_reason', 'coding_verified_by', 'coding_verified_at'];

    protected function casts(): array
    {
        return ['surfaces' => 'array', 'coding_verified_at' => 'datetime'];
    }

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(Pemeriksaan::class);
    }
}
