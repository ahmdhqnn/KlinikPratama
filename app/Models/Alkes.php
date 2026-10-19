<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alkes extends Model
{
    protected $table = 'alkes';

    use SoftDeletes;

    protected $fillable = [
        'kode', 'nama', 'satuan', 'stok', 'stok_minimum',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
