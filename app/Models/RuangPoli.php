<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuangPoli extends Model
{
    protected $table = 'ruang_poli';

    protected $fillable = ['poliklinik_id', 'kode', 'nama', 'satusehat_location_id', 'physical_type_code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }
}
