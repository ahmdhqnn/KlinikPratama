<?php

namespace App\Models;

use Database\Factories\CorrespondenceTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrespondenceTemplate extends Model
{
    /** @use HasFactory<CorrespondenceTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'kode', 'nama', 'jenis', 'prefix', 'format_nomor', 'nomor_berikutnya', 'isi', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nomor_berikutnya' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function suratMedis(): HasMany
    {
        return $this->hasMany(SuratMedis::class, 'surat_template_id');
    }

    public function rujukanInternal(): HasMany
    {
        return $this->hasMany(RujukanInternal::class, 'surat_template_id');
    }
}
