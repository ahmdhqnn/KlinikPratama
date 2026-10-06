<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabHasilDetail extends Model
{
    protected $table = 'lab_hasil_detail';

    protected $fillable = [
        'lab_hasil_id',
        'indikator_id',
        'nilai',
        'keterangan',
    ];

    public function labHasil(): BelongsTo
    {
        return $this->belongsTo(LabHasil::class);
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(LabIndikator::class, 'indikator_id');
    }
}
