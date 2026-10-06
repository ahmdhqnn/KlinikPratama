<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiayaAdmin extends Model
{
    protected $table = 'biaya_admin';

    protected $fillable = ['nama', 'tarif', 'keterangan', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'tarif' => 'decimal:2',
    ];
}
