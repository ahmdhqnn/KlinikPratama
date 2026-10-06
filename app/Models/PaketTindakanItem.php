<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaketTindakanItem extends Model
{
    protected $table = 'paket_tindakan_items';

    protected $fillable = ['paket_tindakan_id', 'tindakan_id', 'laboratorium_id', 'jenis'];

    public function paketTindakan(): BelongsTo
    {
        return $this->belongsTo(PaketTindakan::class);
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function laboratorium(): BelongsTo
    {
        return $this->belongsTo(Laboratorium::class);
    }
}
