<?php

namespace App;

use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\ProcedureConsentTemplate;
use App\Models\ProcedureSignatureSession;
use App\Models\TindakanKunjungan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProcedureConsentService
{
    public const VERSION = 'IC-2026-11-v1';

    public function publishedTemplate(Kunjungan $visit, TindakanKunjungan $procedure, bool $includeArchived = false): ?ProcedureConsentTemplate
    {
        if ($procedure->code_system !== 'icd9cm_procedure' || blank($procedure->code_release)) {
            return null;
        }

        $template = ProcedureConsentTemplate::withTrashed()->where('code', $procedure->kode_icd9cm)
            ->where('release', $procedure->code_release)
            ->where('clinic_type', $visit->poliklinik?->jenis)
            ->whereNotNull('published_at')->latest('published_at')->latest('id')->first();

        return ! $includeArchived && $template?->trashed() ? null : $template;
    }

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
        if ($procedure->relationLoaded('informedConsents')) {
            return $procedure->informedConsents->sortByDesc('id')->first();
        }

        return InformedConsent::where('tindakan_kunjungan_id', $procedure->id)->latest('id')->first();
    }

    public function summary(TindakanKunjungan $procedure): array
    {
        $latest = $this->latest($procedure);
        $needsDoctorStart = $this->requiresDoctorStart($procedure, $latest);

        return [
            'decision' => $latest?->decision ?? 'unreviewed',
            'currentId' => $latest?->id,
            'recordedAt' => $latest?->recorded_at?->format('d/m/Y H:i'),
            'planMatches' => $latest !== null && $this->matchesCurrentPlan($procedure, $latest),
            'requiredReview' => (bool) $procedure->consent_review_required,
            'signaturePending' => $procedure->execution_status !== 'completed'
                && $this->hasPendingSignature($procedure),
            'doctorStartRequired' => $needsDoctorStart,
            'doctorStartedAt' => $latest && $procedure->doctor_start_consent_id === $latest->id
                ? $procedure->doctor_started_at?->format('d/m/Y H:i') : null,
            'url' => route('procedure-consent.show', [$procedure->kunjungan_id, $procedure]),
        ];
    }

    public function requiresDoctorStart(TindakanKunjungan $procedure, ?InformedConsent $consent): bool
    {
        return $procedure->execution_role === 'dokter'
            && $procedure->consent_review_required
            && $procedure->execution_status === 'pending'
            && $consent?->decision === 'accepted';
    }

    public function ensureCanPerform(TindakanKunjungan $procedure): ?InformedConsent
    {
        if ($this->hasPendingSignature($procedure)) {
            throw ValidationException::withMessages(['procedure_consent' => 'Selesaikan sesi tanda tangan dan konfirmasi saksi untuk '.$this->plan($procedure)['name'].'.']);
        }
        $latest = $this->latest($procedure);
        if (! $latest && ! $procedure->consent_review_required) {
            return null; // Legacy remains unreviewed; no historical consent is manufactured.
        }
        if (! $latest || ! $this->matchesCurrentPlan($procedure, $latest)
            || ! in_array($latest->decision, ['accepted', 'oral', 'not_required', 'emergency'], true)) {
            throw ValidationException::withMessages(['procedure_consent' => 'Tinjau kebutuhan dan bukti persetujuan untuk tindakan '.$this->plan($procedure)['name'].' sebelum pelaksanaan. Penolakan atau pencabutan tidak boleh dilewati.']);
        }
        if ($latest->decision === 'accepted' && ! $this->evidenceIsIntact($latest)) {
            throw ValidationException::withMessages(['procedure_consent' => 'Bukti persetujuan tindakan tidak tersedia atau integritasnya perlu diperiksa.']);
        }

        return $latest;
    }

    public function hasPendingSignature(TindakanKunjungan $procedure): bool
    {
        return ProcedureSignatureSession::where('procedure_id', $procedure->id)->pending()->exists();
    }

    public function ensureNoPendingSignature(Kunjungan $visit): void
    {
        $sessions = ProcedureSignatureSession::whereHas('procedure', fn ($query) => $query->where('kunjungan_id', $visit->id));
        if ((clone $sessions)->pending()->exists()) {
            throw ValidationException::withMessages(['procedure_consent' => 'Selesaikan atau batalkan sesi tanda tangan tindakan yang masih terbuka untuk kunjungan ini.']);
        }
        $archives = InformedConsent::whereIn('id', $sessions->whereNotNull('consent_id')->select('consent_id'))->get();
        foreach ($archives as $archive) {
            if (! $this->evidenceIsIntact($archive)) {
                throw ValidationException::withMessages(['procedure_consent' => 'Arsip PDF keputusan tindakan tidak tersedia atau berubah. Periksa bukti sebelum menutup kunjungan.']);
            }
        }
    }

    private function evidenceIsIntact(InformedConsent $consent): bool
    {
        $actual = $consent->evidence_path && Storage::disk('local')->exists($consent->evidence_path)
            ? hash_file('sha256', Storage::disk('local')->path($consent->evidence_path))
            : false;

        return is_string($actual) && hash_equals((string) $consent->evidence_sha256, $actual);
    }

    private function matchesCurrentPlan(TindakanKunjungan $procedure, InformedConsent $consent): bool
    {
        if (! hash_equals((string) $consent->plan_hash, $this->planHash($procedure))) {
            return false;
        }
        if ($consent->decision === 'emergency' && $procedure->execution_status !== 'completed') {
            return $procedure->kunjungan->screening?->prioritas_layanan === 'darurat'
                && ($consent->procedure_snapshot['emergency_without_available_signer'] ?? false) === true;
        }
        if (! in_array($consent->decision, ['oral', 'not_required'], true) || $procedure->performed_at || $procedure->execution_status === 'completed') {
            return true;
        }

        $template = $this->publishedTemplate($procedure->kunjungan, $procedure, includeArchived: true);
        if ($consent->decision === 'not_required') {
            return $template === null;
        }
        $snapshot = $consent->procedure_snapshot['consent_template'] ?? [];

        return $template !== null && ! $template->trashed()
            && $template->id === ($snapshot['id'] ?? null)
            && $template->mode === ($snapshot['mode'] ?? null)
            && (! isset($snapshot['revision']) || hash_equals($snapshot['revision'], $template->revision()));
    }
}
