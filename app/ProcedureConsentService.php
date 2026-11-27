<?php

namespace App;

use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\ProcedureConsentTemplate;
use App\Models\ProcedureSignatureSession;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProcedureConsentService
{
    public const VERSION = 'IC-2026-11-v1';

    public const RELATIONSHIP_LABELS = [
        'patient' => 'Pasien sendiri',
        'parent' => 'Orang tua',
        'spouse' => 'Suami / istri',
        'guardian' => 'Wali / pengampu',
        'other' => 'Wakil lain yang sah',
    ];

    public const EFFECTIVE_MODE_LABELS = [
        'written' => 'Wajib tertulis (tanda tangan)',
        'conditional' => 'Kondisional sesuai SOP',
        'oral' => 'Persetujuan lisan / tertulis',
        'general_consent' => 'Cukup General Consent (tindakan rutin)',
    ];

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

    public function masterConsentMode(TindakanKunjungan $procedure): ?string
    {
        if (! $procedure->tindakan_id) {
            return null;
        }

        $master = $procedure->relationLoaded('tindakan') ? $procedure->tindakan : $procedure->tindakan()->first();

        return $master instanceof Tindakan ? $master->consentMode() : null;
    }

    /**
     * Combine published ICD-9-CM SOP template and Master Tindakan consent classification.
     * Strictest patient-safety requirement always takes precedence.
     *
     * @return array{mode: string, source: string, label: string, allowsNotRequired: bool, allowsOral: bool, template: ?ProcedureConsentTemplate, masterMode: ?string}
     */
    public function effectiveRequirement(Kunjungan $visit, TindakanKunjungan $procedure, bool $includeArchived = false): array
    {
        $latestTemplate = $this->publishedTemplate($visit, $procedure, includeArchived: true);
        $activeTemplate = $latestTemplate?->trashed() ? null : $latestTemplate;
        $masterMode = $this->masterConsentMode($procedure);

        if ($activeTemplate?->mode === 'written' || $masterMode === 'written') {
            return [
                'mode' => 'written',
                'source' => $activeTemplate?->mode === 'written' ? 'sop' : 'master',
                'label' => self::EFFECTIVE_MODE_LABELS['written'],
                'allowsNotRequired' => false,
                'allowsOral' => false,
                'template' => $activeTemplate,
                'masterMode' => $masterMode,
            ];
        }

        if ($activeTemplate?->mode === 'conditional') {
            return [
                'mode' => 'conditional',
                'source' => 'sop',
                'label' => self::EFFECTIVE_MODE_LABELS['conditional'],
                'allowsNotRequired' => false,
                'allowsOral' => true,
                'template' => $activeTemplate,
                'masterMode' => $masterMode,
            ];
        }

        if ($activeTemplate?->mode === 'oral' || $masterMode === 'oral') {
            return [
                'mode' => 'oral',
                'source' => $activeTemplate?->mode === 'oral' ? 'sop' : 'master',
                'label' => self::EFFECTIVE_MODE_LABELS['oral'],
                'allowsNotRequired' => false,
                'allowsOral' => true,
                'template' => $activeTemplate,
                'masterMode' => $masterMode,
            ];
        }

        if ($includeArchived && $latestTemplate?->trashed()) {
            return [
                'mode' => 'written',
                'source' => 'sop_archived',
                'label' => self::EFFECTIVE_MODE_LABELS['written'],
                'allowsNotRequired' => false,
                'allowsOral' => false,
                'template' => null,
                'masterMode' => $masterMode,
            ];
        }

        return [
            'mode' => 'general_consent',
            'source' => $masterMode !== null ? 'master' : 'default',
            'label' => self::EFFECTIVE_MODE_LABELS['general_consent'],
            'allowsNotRequired' => true,
            'allowsOral' => false,
            'template' => null,
            'masterMode' => $masterMode,
        ];
    }

    public function generalConsentPermitsRoutine(Kunjungan $visit): bool
    {
        $service = app(GeneralConsentService::class);
        if ($service->isExplicitlyRefused($visit)) {
            return false;
        }
        if (! $visit->general_consent_required) {
            return true;
        }

        return $service->hasActiveConsent($visit);
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
        $visit = $procedure->kunjungan;
        $latest = $this->latest($procedure);
        $needsDoctorStart = $this->requiresDoctorStart($procedure, $latest);
        $requirement = $visit ? $this->effectiveRequirement($visit, $procedure) : [
            'mode' => 'general_consent',
            'source' => 'default',
            'label' => self::EFFECTIVE_MODE_LABELS['general_consent'],
            'masterMode' => null,
        ];
        $blockReason = $this->blockReason($procedure);
        $recordedByRole = $latest?->procedure_snapshot['recorded_by_role']
            ?? ($latest?->recordedBy?->role ?? null);

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
            'effectiveMode' => $requirement['mode'],
            'effectiveModeLabel' => $requirement['label'],
            'masterConsentMode' => $requirement['masterMode'],
            'signerName' => $latest?->nama_penandatangan,
            'signerRelationship' => $latest?->hubungan_pasien,
            'signerRelationshipLabel' => $latest?->hubungan_pasien ? (self::RELATIONSHIP_LABELS[$latest->hubungan_pasien] ?? $latest->hubungan_pasien) : null,
            'witnessName' => $latest?->witness_name,
            'recordedBy' => $latest?->recordedBy?->name,
            'recordedByRole' => $recordedByRole,
            'reason' => $latest?->reason,
            'evidenceUrl' => $latest?->evidence_path
                ? route('procedure-consent.evidence', [$procedure->kunjungan_id, $procedure, $latest])
                : null,
            'followupRequired' => $latest?->decision === 'emergency'
                && (($latest->procedure_snapshot['followup_documentation_required'] ?? true) === true),
            'generalConsentActive' => $visit ? $this->generalConsentPermitsRoutine($visit) : true,
            'canPerform' => $blockReason === null,
            'blockReason' => $blockReason,
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

    public function blockReason(TindakanKunjungan $procedure): ?string
    {
        $name = $this->plan($procedure)['name'];
        if ($this->hasPendingSignature($procedure)) {
            return 'Selesaikan sesi tanda tangan dan konfirmasi saksi untuk '.$name.'.';
        }
        $latest = $this->latest($procedure);
        if (! $latest && ! $procedure->consent_review_required) {
            return null; // Legacy remains unreviewed; no historical consent is manufactured.
        }
        if (! $latest) {
            return 'Tinjau kebutuhan dan bukti persetujuan untuk tindakan '.$name.' sebelum pelaksanaan. Penolakan atau pencabutan tidak boleh dilewati.';
        }
        if (in_array($latest->decision, ['refused', 'withdrawn'], true)) {
            return 'Pasien atau wakil telah '.($latest->decision === 'refused' ? 'menolak' : 'mencabut persetujuan').' tindakan '.$name.'. Penolakan atau pencabutan tidak boleh dilewati.';
        }
        if ($latest->decision === 'not_required' && $procedure->performed_at === null && $procedure->execution_status !== 'completed'
            && ! $this->generalConsentPermitsRoutine($procedure->kunjungan)) {
            return 'Tindakan rutin '.$name.' memerlukan Persetujuan Umum (General Consent) yang aktif sebelum pelaksanaan.';
        }
        if (! $this->matchesCurrentPlan($procedure, $latest)
            || ! in_array($latest->decision, ['accepted', 'oral', 'not_required', 'emergency'], true)) {
            return 'Tinjau kebutuhan dan bukti persetujuan untuk tindakan '.$name.' sebelum pelaksanaan. Penolakan atau pencabutan tidak boleh dilewati.';
        }
        if ($latest->decision === 'accepted' && ! $this->evidenceIsIntact($latest)) {
            return 'Bukti persetujuan tindakan tidak tersedia atau integritasnya perlu diperiksa.';
        }

        return null;
    }

    public function ensureCanPerform(TindakanKunjungan $procedure): ?InformedConsent
    {
        $reason = $this->blockReason($procedure);
        if ($reason !== null) {
            throw ValidationException::withMessages(['procedure_consent' => $reason]);
        }

        return $this->latest($procedure);
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

        $requirement = $this->effectiveRequirement($procedure->kunjungan, $procedure, includeArchived: true);
        if ($consent->decision === 'not_required') {
            return $requirement['allowsNotRequired']
                && $this->generalConsentPermitsRoutine($procedure->kunjungan);
        }

        if ($requirement['mode'] === 'written') {
            return false;
        }

        $template = $this->publishedTemplate($procedure->kunjungan, $procedure, includeArchived: true);
        if ($template === null) {
            return $requirement['masterMode'] === 'oral';
        }

        $snapshot = $consent->procedure_snapshot['consent_template'] ?? [];

        return ! $template->trashed()
            && $template->id === ($snapshot['id'] ?? null)
            && $template->mode === ($snapshot['mode'] ?? null)
            && (! isset($snapshot['revision']) || hash_equals($snapshot['revision'], $template->revision()));
    }
}
