<?php

namespace App;

use App\Models\GeneralConsent;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeneralConsentService
{
    public const VERSION = 'GC-2026-11-v1';

    public const TEXT = 'Pasien menerima penjelasan tentang tata tertib pelayanan, pencatatan rekam medis elektronik, kerahasiaan, dan hak memperoleh salinan informasi sesuai prosedur. Data diproses untuk pelayanan dan kewajiban hukum yang berlaku. Pasien dapat bertanya dan menyampaikan keputusan melalui pasien sendiri atau wakil yang sah. Persetujuan umum ini bukan persetujuan seluruh tindakan medis atau izin pelepasan informasi kepada semua pihak. Tindakan tertentu dan pelepasan informasi memerlukan penilaian serta persetujuan/dasar hukum tersendiri. Pilihan opsional dapat ditolak atau ditarik tanpa otomatis menolak seluruh pelayanan. Pencabutan tidak menghapus rekam medis yang wajib disimpan.';

    public function latest(Kunjungan $visit): ?GeneralConsent
    {
        return GeneralConsent::where('pasien_id', $visit->pasien_id)->latest('id')->first();
    }

    public function activeDate(?GeneralConsent $consent): ?string
    {
        return $consent?->decision === 'accepted'
            && $consent->text_version === self::VERSION
            && ($consent->signature_token ? $this->verifies($consent) : $consent->evidence_sha256)
            ? $consent->recorded_at->format('d-m-Y')
            : null;
    }

    public function recordFirstAcceptance(Request $request, Kunjungan $visit, Pasien $patient, array $confirmation): void
    {
        $requestId = (string) Str::uuid();
        $consent = new GeneralConsent([
            'pasien_id' => $patient->id, 'kunjungan_id' => $visit->id,
            'request_id' => $requestId, 'request_hash' => hash('sha256', $requestId),
            'decision' => 'accepted', 'text_version' => self::VERSION, 'text_snapshot' => self::TEXT,
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

        $payload = json_encode([
            'general-consent-attestation-v1', $consent->signature_token,
            (int) $consent->pasien_id, (int) $consent->kunjungan_id,
            $consent->supersedes_id === null ? null : (int) $consent->supersedes_id,
            $consent->request_id, $consent->request_hash, $consent->decision,
            $consent->text_version, $consent->text_snapshot, $consent->signer_name,
            $consent->signer_relationship, $consent->representative_verification,
            $consent->reason, (int) $consent->recorded_by, $consent->recorded_at->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR);

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

    public function summary(Kunjungan $visit): array
    {
        $latest = $this->latest($visit);

        return [
            'status' => $latest?->signature_token && ! $this->verifies($latest) ? 'invalid' : ($latest?->decision ?? 'undocumented'),
            'currentId' => $latest?->id,
            'recordedAt' => $latest?->recorded_at->format('d/m/Y H:i'),
            'versionCurrent' => $latest?->text_version === self::VERSION,
            'required' => (bool) $visit->general_consent_required,
            'url' => route('general-consent.show', $visit),
        ];
    }

    public function ensureDocumented(Request $request, Kunjungan $visit, bool $emergency): void
    {
        $consent = $this->latest($visit);
        if ($consent && in_array($consent->decision, ['accepted', 'refused'], true)
            && $consent->text_version === self::VERSION
            && ($consent->signature_token ? $this->verifies($consent) : $consent->evidence_sha256)) {
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
        throw ValidationException::withMessages(['general_consent' => 'Dokumentasikan persetujuan atau penolakan umum melalui petugas pendaftaran. Pada kondisi darurat, catat alasan penundaan administrasi minimal 20 karakter.']);
    }
}
