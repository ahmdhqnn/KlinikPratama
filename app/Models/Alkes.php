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
        'harga_beli', 'harga_jual', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
    ];
}
