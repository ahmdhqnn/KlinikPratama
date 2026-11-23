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
        'nama_tindakan', 'kode_icd9cm', 'code_system', 'code_release',
        'jumlah', 'tooth_fdi', 'catatan', 'bhp_consumed_at',
        'execution_role', 'execution_status', 'performed_by', 'performed_at', 'execution_result',
        'consent_review_required',
        'coding_status', 'coding_display', 'coding_review_reason', 'coding_reviewed_by', 'coding_reviewed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'consent_review_required' => 'boolean',
        'coding_reviewed_at' => 'datetime',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
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
