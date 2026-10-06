<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabHasil extends Model
{
    protected $table = 'lab_hasil';

    protected $fillable = ['kunjungan_id', 'laboratorium_id', 'petugas_id', 'status'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function laboratorium(): BelongsTo
    {
        return $this->belongsTo(Laboratorium::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'petugas_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(LabHasilDetail::class);
    }
}
