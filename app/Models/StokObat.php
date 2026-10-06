<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokObat extends Model
{
    protected $table = 'stok_obat';

    protected $fillable = [
        'obat_id',
        'depo_id',
        'stok',
    ];

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function depo(): BelongsTo
    {
        return $this->belongsTo(DepoObat::class, 'depo_id');
    }
}
