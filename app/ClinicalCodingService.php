<?php

namespace App;

use App\Models\ClinicalTerminology;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClinicalCodingService
{
    public function finalize(Request $request, Kunjungan $visit, Pemeriksaan $examination): void
    {
        if (is_string($request->input('coding_downtime_reason'))) {
            $request->merge(['coding_downtime_reason' => trim($request->input('coding_downtime_reason'))]);
        }
        $diagnoses = $examination->diagnosa()->get();
        $invalidVerified = $diagnoses->filter(function ($diagnosis): bool {
            if ($diagnosis->coding_status !== 'verified') {
                return false;
            }

            return $diagnosis->code_system !== 'icd10_who' || blank($diagnosis->code_release) || ! ClinicalTerminology::query()
                ->where('code_system', 'icd10_who')
                ->where('code_type', 'diagnosis')
                ->where('is_active', true)
                ->where('code', $diagnosis->kode_icd10)
                ->where('display', $diagnosis->nama_diagnosa)
                ->where('release', $diagnosis->code_release)
                ->exists();
        });
        if ($invalidVerified->isNotEmpty()) {
            throw ValidationException::withMessages([
                'coding_review' => 'Katalog ICD-10 WHO untuk diagnosis berubah atau tidak aktif. Pilih ulang kode sebelum menandatangani pemeriksaan.',
            ]);
        }
        $unresolved = $diagnoses->filter(fn ($diagnosis): bool => $diagnosis->coding_status === 'unresolved');

        $procedures = $visit->tindakanKunjungan()->orderBy('id')->lockForUpdate()->get();
        $unresolvedProcedures = $procedures->filter(fn ($procedure): bool => ! app(ProcedureCodingReview::class)->verified($procedure));
        if ($unresolved->isEmpty() && $unresolvedProcedures->isEmpty()) {
            $examination->forceFill([
                'coding_status' => $diagnoses->contains(fn ($diagnosis): bool => $diagnosis->coding_status === 'legacy')
                    || $procedures->contains(fn ($procedure): bool => $procedure->coding_status === 'legacy') ? 'legacy' : 'verified',
                'coding_unresolved_reason' => null,
                'coding_review_due_at' => null,
            ])->save();

            return;
        }

        $emergency = $visit->screening?->prioritas_layanan === 'darurat';
        $downtime = (bool) config('clinical_coding.downtime_active', false);
        if (! $emergency && ! $downtime) {
            throw ValidationException::withMessages([
                'coding_review' => 'Diagnosis atau tindakan belum terverifikasi. Pilih kode ICD-10 WHO/ICD-9-CM aktif, atau dokumentasikan tindakan yang tidak memerlukan coding prosedur sebelum finalisasi normal.',
            ]);
        }

        $data = $request->validate([
            'coding_downtime_acknowledged' => ['required', 'accepted'],
            'coding_downtime_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'coding_downtime_acknowledged.required' => 'Konfirmasi pencatatan diagnosis unresolved untuk kondisi darurat/downtime.',
            'coding_downtime_acknowledged.accepted' => 'Konfirmasi pencatatan diagnosis unresolved untuk kondisi darurat/downtime.',
            'coding_downtime_reason.required' => 'Catat alasan dan rencana rekonsiliasi coding.',
        ]);

        $examination->forceFill([
            'coding_status' => 'unresolved',
            'coding_unresolved_reason' => trim($data['coding_downtime_reason']),
            'coding_review_due_at' => now()->addDay(),
        ])->save();
    }
}
