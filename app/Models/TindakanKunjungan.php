<?php

namespace App\Models;

use App\Models\Concerns\RetainsCancelledClinicalEntries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TindakanKunjungan extends Model
{
    use RetainsCancelledClinicalEntries;

    protected $table = 'tindakan_kunjungan';

    protected $fillable = [
        'kunjungan_id', 'tindakan_id', 'nama_tindakan_manual', 'dokter_id',
        'nama_tindakan', 'kode_icd9cm', 'snomed_code', 'snomed_display', 'code_system', 'code_release',
        'jumlah', 'tooth_fdi', 'surfaces', 'catatan', 'bhp_consumed_at',
        'execution_role', 'execution_status', 'performed_by', 'performed_by_nakes_id', 'performed_at', 'execution_result',
        'consent_review_required',
        'doctor_started_at', 'started_at', 'doctor_start_consent_id', 'satusehat_procedure_id',
        'coding_status', 'coding_display', 'coding_review_reason', 'coding_reviewed_by', 'coding_reviewed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'doctor_started_at' => 'datetime',
        'started_at' => 'datetime',
        'doctor_start_consent_id' => 'integer',
        'consent_review_required' => 'boolean',
        'coding_reviewed_at' => 'datetime',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function performedByNakes(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'performed_by_nakes_id');
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Nakes::class, 'dokter_id');
    }

    public function informedConsents(): HasMany
    {
        return $this->hasMany(InformedConsent::class, 'tindakan_kunjungan_id');
    }
}
