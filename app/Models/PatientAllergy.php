<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    protected $table = 'patient_allergies';

    protected $fillable = [
        'pasien_id',
        'kunjungan_id',
        'recorded_by_user_id',
        'category',
        'allergen_code_system',
        'allergen_code',
        'allergen_display',
        'criticality',
        'reaction_text',
        'reaction_snomed',
        'clinical_status',
        'verification_status',
        'satusehat_allergy_id',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
