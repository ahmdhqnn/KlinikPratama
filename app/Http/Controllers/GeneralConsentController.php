<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicDocumentProfile;
use App\DocumentVerificationQr;
use App\GeneralConsentService;
use App\Models\GeneralConsent;
use App\Models\Kunjungan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GeneralConsentController extends Controller
{
    private function authorizeVisit(Request $request, Kunjungan $visit, bool $write = false): void
    {
        $user = $request->user();
        abort_unless($user?->is_active && in_array($user->role, $write ? ['admin', 'pendaftaran'] : ['admin', 'pendaftaran', 'dokter', 'perawat'], true), 403);
        if (in_array($user->role, ['dokter', 'perawat'], true)) {
            Gate::authorize('viewClinical', $visit);
        }
    }

    public function show(Request $request, Kunjungan $kunjungan, GeneralConsentService $service, ClinicalAuditRecorder $audit): Response
    {
        $this->authorizeVisit($request, $kunjungan);
        $audit->record($request, 'general_consent.view', $kunjungan->pasien_id, $kunjungan->id);

        return Inertia::render('pelayanan/kunjungan/general-consent', [
            'visit' => ['id' => $kunjungan->id, 'number' => $kunjungan->no_kunjungan, 'patientName' => $kunjungan->pasien->nama, 'medicalRecordNumber' => $kunjungan->pasien->no_rm],
            'summary' => $service->summary($kunjungan),
            'text' => GeneralConsentService::TEXT,
            'textVersion' => GeneralConsentService::VERSION,
            'canWrite' => in_array($request->user()->role, ['admin', 'pendaftaran'], true),
            'releaseUrl' => in_array($request->user()->role, ['dokter', 'pendaftaran'], true) ? route('information-release.show', $kunjungan) : null,
            'storeUrl' => route('general-consent.store', $kunjungan),
            'requestId' => (string) Str::uuid(),
            'backUrl' => route($request->user()->role === 'pendaftaran' ? 'pendaftaran.kunjungan.show' : ($request->user()->role === 'dokter' ? 'pelayanan.pemeriksaan.show' : 'pelayanan.kunjungan.show'), $kunjungan),
            'history' => GeneralConsent::where('pasien_id', $kunjungan->pasien_id)->latest('id')->get()->map(fn (GeneralConsent $item): array => [
                'id' => $item->id, 'decision' => $item->decision, 'version' => $item->text_version,
                'signer' => $item->signer_name, 'relationship' => $item->signer_relationship,
                'reason' => $item->reason, 'at' => $item->recorded_at->format('d/m/Y H:i'),
                'evidenceUrl' => $item->evidence_path ? route('general-consent.evidence', [$kunjungan, $item]) : null,
                'verificationUrl' => $item->signature_token ? route('general-consent.verify', $item->signature_token) : null,
            ]),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan, GeneralConsentService $service, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, true);
        $inPerson = $request->boolean('in_person');
        $data = $request->validate([
            'in_person' => ['sometimes', 'boolean'],
            'request_id' => ['required', 'uuid'], 'current_id' => ['nullable', 'integer'],
            'decision' => ['required', Rule::in(['accepted', 'refused', 'withdrawn'])],
            'text_version' => ['required', Rule::in([GeneralConsentService::VERSION])],
            'signer_name' => [
                Rule::excludeIf($inPerson && $request->input('signer_relationship') === 'patient'),
                'required', 'string', 'min:2', 'max:150',
            ],
            'signer_relationship' => ['required', Rule::in(['patient', 'parent', 'spouse', 'guardian', 'other'])],
            'representative_verification' => [Rule::requiredIf($request->input('signer_relationship') !== 'patient'), 'nullable', 'string', 'min:10', 'max:1000'],
            'reason' => [Rule::requiredIf($request->input('decision') !== 'accepted'), 'nullable', 'string', 'min:10', 'max:2000'],
            'explained' => ['required', 'accepted'],
            'evidence' => $inPerson ? ['prohibited'] : ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $requestValues = collect($data)->except('evidence')->all();
        $file = $request->file('evidence');
        $requestValues['evidence_sha256'] = $file ? hash_file('sha256', $file->getRealPath()) : null;
        $requestHash = hash('sha256', json_encode($requestValues, JSON_THROW_ON_ERROR));
        $path = null;
        try {
            DB::transaction(function () use ($request, $kunjungan, $service, $audit, $data, $requestHash, $inPerson, $file, &$path): void {
                $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
                // Patient lock serializes decisions entered from different visits of the same person.
                DB::table('pasien')->where('id', $visit->pasien_id)->lockForUpdate()->first();
                $latest = $service->latest($visit);
                $retry = GeneralConsent::where('request_id', $data['request_id'])->first();
                if ($retry) {
                    abort_unless($retry->kunjungan_id === $visit->id && $retry->recorded_by === $request->user()->id
                        && hash_equals($retry->request_hash, $requestHash), 409, 'Permintaan sudah dipakai. Muat ulang sebelum mencatat keputusan berbeda.');

                    return;
                }
                if (($latest?->id ?? null) !== (isset($data['current_id']) ? (int) $data['current_id'] : null)) {
                    throw ValidationException::withMessages(['current_id' => 'Keputusan pasien telah berubah. Muat ulang dan tinjau riwayat terbaru.']);
                }
                if ($data['decision'] === 'withdrawn' && $latest?->decision !== 'accepted') {
                    throw ValidationException::withMessages(['decision' => 'Pencabutan memerlukan persetujuan yang masih berlaku.']);
                }
                if ($file) {
                    $path = $file->store('consents/general/'.$visit->pasien_id, 'local');
                    abort_unless(is_string($path), 503, 'Bukti persetujuan belum dapat disimpan.');
                }
                $item = new GeneralConsent([
                    'pasien_id' => $visit->pasien_id, 'kunjungan_id' => $visit->id, 'supersedes_id' => $latest?->id,
                    'request_id' => $data['request_id'], 'request_hash' => $requestHash, 'decision' => $data['decision'],
                    'text_version' => GeneralConsentService::VERSION, 'text_snapshot' => GeneralConsentService::TEXT,
                    'signer_name' => $data['signer_name'] ?? $visit->pasien->nama, 'signer_relationship' => $data['signer_relationship'],
                    'representative_verification' => $data['representative_verification'] ?? null, 'reason' => $data['reason'] ?? null,
                    'evidence_path' => $path, 'evidence_name' => $file ? Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, '') : null,
                    'evidence_mime' => $file?->getMimeType(), 'evidence_sha256' => $file ? hash_file('sha256', $file->getRealPath()) : null,
                    'recorded_by' => $request->user()->id, 'recorded_at' => now(),
                ]);
                if ($inPerson) {
                    $item->signature_token = (string) Str::uuid();
                    $item->signature_hash = $service->signature($item, (string) config('app.key'));
                }
                $item->save();
                $audit->record($request, 'general_consent.recorded', $visit->pasien_id, $visit->id, [
                    'consent_id' => $item->id, 'supersedes_id' => $latest?->id,
                    'old_values' => ['decision' => $latest?->decision], 'new_values' => ['decision' => $item->decision, 'text_version' => $item->text_version],
                    'evidence_sha256' => $item->evidence_sha256,
                    'method' => $inPerson ? 'staff_attestation' : 'uploaded_evidence',
                ]);
            });
        } catch (Throwable $error) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('success', 'Keputusan persetujuan umum tercatat. Bukti sebelumnya tetap tersimpan.');
    }

    public function verify(Request $request, string $token, GeneralConsentService $service, DocumentVerificationQr $qr, ClinicalAuditRecorder $audit): \Illuminate\Http\Response
    {
        $consent = GeneralConsent::where('signature_token', $token)->firstOrFail();
        $visit = Kunjungan::findOrFail($consent->kunjungan_id);
        $this->authorizeVisit($request, $visit);
        $audit->record($request, 'general_consent.view', $visit->pasien_id, $visit->id, ['consent_id' => $consent->id, 'purpose' => 'verification']);

        $valid = $service->verifies($consent);
        $current = $service->latest($visit)?->is($consent) && $consent->text_version === GeneralConsentService::VERSION;

        return response()->view('general-consent-verification', [
            'valid' => $valid, 'current' => $current, 'consent' => $consent, 'visit' => $visit,
            'clinic' => app(ClinicDocumentProfile::class)->data(),
            'recorder' => DB::table('users')->where('id', $consent->recorded_by)->value('name'),
            'qrDataUrl' => $valid ? $qr->dataUrl(route('general-consent.verify', $token)) : null,
        ], $valid ? 200 : 409, ['Cache-Control' => 'private, no-store']);
    }

    public function evidence(Request $request, Kunjungan $kunjungan, GeneralConsent $consent, ClinicalAuditRecorder $audit): StreamedResponse
    {
        $this->authorizeVisit($request, $kunjungan);
        abort_unless($consent->pasien_id === $kunjungan->pasien_id && $consent->evidence_path && Storage::disk('local')->exists($consent->evidence_path), 404);
        abort_unless(hash_equals($consent->evidence_sha256, hash_file('sha256', Storage::disk('local')->path($consent->evidence_path))), 409, 'Integritas bukti consent perlu diperiksa.');
        $audit->record($request, 'general_consent.download', $kunjungan->pasien_id, $kunjungan->id, ['consent_id' => $consent->id]);

        return Storage::disk('local')->download($consent->evidence_path, $consent->evidence_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
