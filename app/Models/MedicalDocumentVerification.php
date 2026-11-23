<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalDocumentVerification extends Model
{
    public $timestamps = false;

    protected $fillable = ['surat_medis_id', 'token_hash', 'token', 'content_hash', 'issued_by', 'issued_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'issued_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Bukti penerbitan tidak boleh diubah.'));
        static::deleting(fn () => throw new \LogicException('Bukti penerbitan tidak boleh dihapus.'));
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(SuratMedis::class, 'surat_medis_id');
    }
}
