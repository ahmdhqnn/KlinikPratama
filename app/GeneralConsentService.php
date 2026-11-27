<?php

namespace App;

use App\Models\GeneralConsent;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeneralConsentService
{
    public const VERSION = 'GC-2026-11-v1';

    public const TEXT = 'Pasien menerima penjelasan tentang hak dan kewajiban pasien, tata tertib pelayanan, pemeriksaan klinis rutin non-invasif, pencatatan rekam medis elektronik, kerahasiaan data medis, serta hak memperoleh salinan informasi medis sesuai prosedur dan PMK No. 24 Tahun 2022. Data diproses untuk pelayanan kesehatan, pelaporan resmi, dan integrasi rekam medis elektronik nasional (SATUSEHAT) sesuai pilihan persetujuan berbagi data pasien. Pasien dapat bertanya dan menyampaikan keputusan melalui pasien sendiri atau wakil yang sah. Persetujuan umum ini bukan persetujuan seluruh tindakan medis invasif/berisiko tinggi atau izin pelepasan informasi kepada semua pihak. Tindakan tertentu dan pelepasan informasi memerlukan penilaian serta persetujuan/dasar hukum tersendiri. Pilihan opsional dapat ditolak atau ditarik tanpa otomatis menolak seluruh pelayanan. Pencabutan tidak menghapus rekam medis yang wajib disimpan.';

    public function latest(Kunjungan $visit): ?GeneralConsent
    {
        return GeneralConsent::where('pasien_id', $visit->pasien_id)->latest('id')->first();
    }

    public function activeDate(?GeneralConsent $consent): ?string
    {
        return $consent?->decision === 'accepted'
            && $consent->text_version === self::VERSION
            && $this->evidenceIsIntact($consent)
            ? $consent->recorded_at->format('d-m-Y')
            : null;
    }

    public function recordFirstAcceptance(Request $request, Kunjungan $visit, Pasien $patient, array $confirmation): void
    {
        $requestId = (string) Str::uuid();
        $consent = new GeneralConsent([
            'pasien_id' => $patient->id, 'kunjungan_id' => $visit->id,
            'request_id' => $requestId, 'request_hash' => hash('sha256', $requestId),
            'decision' => 'accepted',
            'satusehat_sharing_decision' => $confirmation['satusehat_sharing_decision'] ?? 'undocumented',
            'text_version' => self::VERSION, 'text_snapshot' => self::TEXT,
            'signer_name' => $confirmation['general_consent_representative'] ?? $patient->nama,
            'signer_relationship' => $confirmation['general_consent_relationship'],
            'representative_verification' => $confirmation['general_consent_authority'] ?? null,
            'recorded_by' => $request->user()->id, 'recorded_at' => now(),
            'signature_token' => (string) Str::uuid(),
        ]);
        $consent->signature_hash = $this->signature($consent, (string) config('app.key'));
        $consent->save();
        $visit->forceFill(['general_consent_id' => $consent->id])->save();

        app(ClinicalAuditRecorder::class)->record($request, 'general_consent.recorded', $patient->id, $visit->id, [
            'consent_id' => $consent->id, 'method' => 'staff_attestation',
            'new_values' => ['decision' => 'accepted', 'text_version' => self::VERSION],
        ]);
    }

    public function signature(GeneralConsent $consent, string $key): string
    {
        if ($key === '') {
            throw new \LogicException('APP_KEY diperlukan untuk konfirmasi digital.');
        }

        $sharingDecision = $consent->satusehat_sharing_decision ?? 'undocumented';
        $payload = json_encode(
            $sharingDecision === 'undocumented'
                ? [
                    'general-consent-attestation-v1', $consent->signature_token,
                    (int) $consent->pasien_id, (int) $consent->kunjungan_id,
                    $consent->supersedes_id === null ? null : (int) $consent->supersedes_id,
                    $consent->request_id, $consent->request_hash, $consent->decision,
                    $consent->text_version, $consent->text_snapshot, $consent->signer_name,
                    $consent->signer_relationship, $consent->representative_verification,
                    $consent->reason, (int) $consent->recorded_by, $consent->recorded_at->format('Y-m-d H:i:s'),
                ]
                : [
                    'general-consent-attestation-v2', $consent->signature_token,
                    (int) $consent->pasien_id, (int) $consent->kunjungan_id,
                    $consent->supersedes_id === null ? null : (int) $consent->supersedes_id,
                    $consent->request_id, $consent->request_hash, $consent->decision, $sharingDecision,
                    $consent->text_version, $consent->text_snapshot, $consent->signer_name,
                    $consent->signer_relationship, $consent->representative_verification,
                    $consent->reason, (int) $consent->recorded_by, $consent->recorded_at->format('Y-m-d H:i:s'),
                ],
            JSON_THROW_ON_ERROR
        );

        return hash_hmac('sha256', $payload, $key);
    }

    public function verifies(GeneralConsent $consent): bool
    {
        if (! $consent->signature_token || ! $consent->signature_hash) {
            return false;
        }

        foreach ([config('app.key'), ...config('app.previous_keys', [])] as $key) {
            if (is_string($key) && $key !== '' && hash_equals($consent->signature_hash, $this->signature($consent, $key))) {
                return true;
            }
        }

        return false;
    }

    public function hasActiveConsent(Kunjungan $visit): bool
    {
        $latest = $this->latest($visit);

        return $latest !== null
            && $latest->decision === 'accepted'
            && $latest->text_version === self::VERSION
            && $this->evidenceIsIntact($latest);
    }

    public function isExplicitlyRefused(Kunjungan $visit): bool
    {
        $latest = $this->latest($visit);

        return $latest !== null
            && in_array($latest->decision, ['refused', 'revoked'], true)
            && $this->evidenceIsIntact($latest);
    }

    public function summary(Kunjungan $visit): array
    {
        $latest = $this->latest($visit);
        $intact = $latest !== null && $this->evidenceIsIntact($latest);

        return [
            'status' => $latest && ! $intact ? 'invalid' : ($latest?->decision ?? 'undocumented'),
            'active' => $intact && $latest->decision === 'accepted' && $latest->text_version === self::VERSION,
            'satusehatSharing' => $latest?->satusehat_sharing_decision ?? 'undocumented',
            'currentId' => $latest?->id,
            'recordedAt' => $latest?->recorded_at->format('d/m/Y H:i'),
            'signerName' => $latest?->signer_name,
            'signerRelationship' => $latest?->signer_relationship,
            'versionCurrent' => $latest?->text_version === self::VERSION,
            'required' => (bool) $visit->general_consent_required,
            'url' => route('general-consent.show', $visit),
        ];
    }

    public function ensureDocumented(Request $request, Kunjungan $visit, bool $emergency): void
    {
        $consent = $this->latest($visit);
        if ($consent && $consent->decision === 'accepted'
            && $consent->text_version === self::VERSION
            && $this->evidenceIsIntact($consent)) {
            $visit->forceFill(['general_consent_id' => $consent->id])->save();

            return;
        }
        if (! $visit->general_consent_required) {
            // Legacy encounters retain their true unknown state and existing workflow.
            return;
        }
        $reason = $request->input('general_consent_emergency_reason');
        if ($emergency && is_string($reason) && mb_strlen(trim($reason)) >= 20 && mb_strlen($reason) <= 2000) {
            app(ClinicalAuditRecorder::class)->record($request, 'general_consent.emergency_deferral', $visit->pasien_id, $visit->id, ['reason' => trim($reason)]);

            return;
        }
        if ($consent && $consent->decision === 'refused' && $consent->text_version === self::VERSION && $this->evidenceIsIntact($consent)) {
            $visit->forceFill(['general_consent_id' => $consent->id])->save();

            throw ValidationException::withMessages(['general_consent' => 'Pasien menolak persetujuan umum. Pelayanan rutin tidak dapat dilanjutkan tanpa persetujuan baru atau dasar kondisi darurat (minimal 20 karakter).']);
        }
        throw ValidationException::withMessages(['general_consent' => 'Dokumentasikan persetujuan umum melalui petugas pendaftaran sebelum pelayanan rutin. Pada kondisi darurat, catat alasan penundaan administrasi minimal 20 karakter.']);
    }

    /**
     * A signed in-person attestation is verified by its HMAC. Uploaded evidence
     * must additionally be present and match its immutable content hash.
     */
    public function evidenceIsIntact(GeneralConsent $consent): bool
    {
        if ($consent->signature_token) {
            return $this->verifies($consent);
        }

        if (! $consent->evidence_path || ! $consent->evidence_sha256
            || ! Storage::disk('local')->exists($consent->evidence_path)) {
            return false;
        }

        $actual = hash_file('sha256', Storage::disk('local')->path($consent->evidence_path));

        return is_string($actual) && hash_equals((string) $consent->evidence_sha256, $actual);
    }
}
