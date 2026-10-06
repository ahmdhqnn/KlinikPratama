<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KlinikSetting extends Model
{
    protected $table = 'klinik_settings';

    protected $fillable = [
        'nama_klinik',
        'alamat',
        'telepon',
        'email',
        'logo',
        'kepala_klinik',
        'nip_kepala',
        'tagline',
        'website',
    ];
}
