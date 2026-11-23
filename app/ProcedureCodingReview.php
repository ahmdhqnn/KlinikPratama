<?php

namespace App;

use App\Models\ClinicalTerminology;
use App\Models\TindakanKunjungan;
use Illuminate\Validation\ValidationException;

class ProcedureCodingReview
{
    public function revision(TindakanKunjungan $procedure): string
    {
        return hash('sha256', json_encode([
            app(ProcedureConsentService::class)->plan($procedure), $procedure->code_system,
            $procedure->code_release, $procedure->coding_status, $procedure->coding_display, $procedure->coding_review_reason,
            $procedure->coding_reviewed_by, $procedure->coding_reviewed_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }

    public function verified(TindakanKunjungan $procedure): bool
    {
        if ($procedure->coding_status === 'not_applicable') {
            return filled($procedure->coding_review_reason) && $procedure->coding_reviewed_by !== null && $procedure->coding_reviewed_at !== null && blank($procedure->kode_icd9cm);
        }
        if ($procedure->coding_status === 'legacy' && ! $procedure->consent_review_required) {
            return true;
        }
        if ($procedure->coding_status !== 'verified') {
            return false;
        }
        if ($procedure->code_system !== 'icd9cm_procedure' || blank($procedure->code_release)
            || ! ClinicalTerminology::query()->where('code_system', 'icd9cm_procedure')->where('code_type', 'procedure')
                ->where('is_active', true)->where('code', $procedure->kode_icd9cm)->where('release', $procedure->code_release)->where('display', $procedure->coding_display)->exists()) {
            throw ValidationException::withMessages(['procedure_coding' => 'Kode ICD-9-CM atau rilis tindakan '.$procedure->nama_tindakan.' tidak lagi aktif. Tinjau pemetaan sebelum finalisasi.']);
        }

        return true;
    }
}
