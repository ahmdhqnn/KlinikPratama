<?php

namespace App;

use App\Models\InformedConsent;
use App\Models\TindakanKunjungan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProcedureConsentService
{
    public const VERSION = 'IC-2026-11-v1';

    public function plan(TindakanKunjungan $procedure): array
    {
        return [
            'id' => $procedure->id,
            'visit_id' => $procedure->kunjungan_id,
            'name' => $procedure->nama_tindakan ?? $procedure->nama_tindakan_manual ?? $procedure->tindakan?->nama ?? 'Tindakan tanpa nama',
            'code' => $procedure->kode_icd9cm,
            'code_system' => $procedure->code_system,
            'code_release' => $procedure->code_release,
            'code_display' => $procedure->coding_display,
            'tooth' => $procedure->tooth_fdi,
            'quantity' => $procedure->jumlah,
            'role' => $procedure->execution_role,
        ];
    }

    public function planHash(TindakanKunjungan $procedure): string
    {
        return hash('sha256', json_encode($this->plan($procedure), JSON_THROW_ON_ERROR));
    }

    public function latest(TindakanKunjungan $procedure): ?InformedConsent
    {
        return InformedConsent::where('tindakan_kunjungan_id', $procedure->id)->latest('id')->first();
    }

    public function summary(TindakanKunjungan $procedure): array
    {
        $latest = $this->latest($procedure);

        return [
            'decision' => $latest?->decision ?? 'unreviewed',
            'currentId' => $latest?->id,
            'recordedAt' => $latest?->recorded_at?->format('d/m/Y H:i'),
            'planMatches' => $latest !== null && hash_equals((string) $latest->plan_hash, $this->planHash($procedure)),
            'requiredReview' => (bool) $procedure->consent_review_required,
            'url' => route('procedure-consent.show', [$procedure->kunjungan_id, $procedure]),
        ];
    }

    public function ensureCanPerform(TindakanKunjungan $procedure): void
    {
        $latest = $this->latest($procedure);
        if (! $latest && ! $procedure->consent_review_required) {
            return; // Legacy remains unreviewed; no historical consent is manufactured.
        }
        if (! $latest || ! hash_equals((string) $latest->plan_hash, $this->planHash($procedure))
            || ! in_array($latest->decision, ['accepted', 'not_required', 'emergency'], true)) {
            throw ValidationException::withMessages(['procedure_consent' => 'Tinjau kebutuhan dan bukti persetujuan untuk tindakan '.$this->plan($procedure)['name'].' sebelum pelaksanaan. Penolakan atau pencabutan tidak boleh dilewati.']);
        }
        if ($latest->decision === 'accepted') {
            $actualHash = $latest->evidence_path && Storage::disk('local')->exists($latest->evidence_path)
                ? hash_file('sha256', Storage::disk('local')->path($latest->evidence_path))
                : false;
            if (! is_string($actualHash) || ! hash_equals((string) $latest->evidence_sha256, $actualHash)) {
                throw ValidationException::withMessages(['procedure_consent' => 'Bukti persetujuan tindakan tidak tersedia atau integritasnya perlu diperiksa.']);
            }
        }
        if ($latest->decision === 'emergency' && $procedure->kunjungan->screening?->prioritas_layanan !== 'darurat') {
            throw ValidationException::withMessages(['procedure_consent' => 'Tinjau kembali persetujuan tindakan; kondisi darurat tidak lagi tercatat.']);
        }
    }
}
