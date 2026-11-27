<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tindakan extends Model
{
    use SoftDeletes;

    public const CONSENT_MODES = [
        'general_consent' => 'Cukup General Consent',
        'oral' => 'Persetujuan lisan',
        'written' => 'Wajib tertulis (tanda tangan)',
    ];

    protected $table = 'tindakan';

    protected $fillable = [
        'kode', 'kode_icd9', 'nama', 'snomed_code', 'snomed_display', 'kategori', 'can_delegate_to_nurse', 'consent_mode', 'poliklinik_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_delegate_to_nurse' => 'boolean',
    ];

    public function consentMode(): string
    {
        return array_key_exists((string) $this->consent_mode, self::CONSENT_MODES)
            ? (string) $this->consent_mode
            : 'general_consent';
    }

    public function consentModeLabel(): string
    {
        return self::CONSENT_MODES[$this->consentMode()];
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class);
    }

    public function bhp(): HasMany
    {
        return $this->hasMany(TindakanBhp::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(TindakanKunjungan::class);
    }

    public function informedConsent(): HasMany
    {
        return $this->hasMany(InformedConsent::class);
    }
}
