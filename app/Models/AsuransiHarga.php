<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsuransiHarga extends Model
{
    protected $table = 'asuransi_harga';

    protected $fillable = ['asuransi_id', 'obat_id', 'harga_khusus'];

    protected $casts = ['harga_khusus' => 'decimal:2'];

    public function asuransi(): BelongsTo
    {
        return $this->belongsTo(Asuransi::class);
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }
}
