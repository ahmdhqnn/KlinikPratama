<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\ProcedureSignatureSession;
use App\Models\TindakanKunjungan;
use App\ProcedureConsentService;
use Illuminate\Http\JsonResponse;
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

class ProcedureConsentController extends Controller
{
    private function authorizeVisit(Request $request, Kunjungan $visit, TindakanKunjungan $procedure, bool $write = false): void
    {
        Gate::authorize('viewClinical', $visit);
        abort_unless((int) $procedure->kunjungan_id === $visit->id, 404);
        abort_if($write && $request->user()->role !== 'dokter', 403);
        abort_if($write && $visit->status === 'batal', 409, 'Kunjungan dibatalkan.');
    }

    public function show(Request $request, Kunjungan $kunjungan, TindakanKunjungan $procedure, ProcedureConsentService $service, ClinicalAuditRecorder $audit): Response|JsonResponse
    {
        $this->authorizeVisit($request, $kunjungan, $procedure);
        $audit->record($request, 'procedure_consent.view', $kunjungan->pasien_id, $kunjungan->id, ['procedure_id' => $procedure->id]);
        $isDoctor = $request->user()->role === 'dokter';
        $canWrite = $isDoctor && in_array($kunjungan->status, ['pemeriksaan', 'tindakan'], true)
            && $procedure->execution_status !== 'completed' && $procedure->performed_at === null;
        $latestTemplate = $canWrite ? $service->publishedTemplate($kunjungan, $procedure, includeArchived: true) : null;
        $template = $latestTemplate?->trashed() ? null : $latestTemplate;
        $signatureSession = ProcedureSignatureSession::where('procedure_id', $procedure->id)->pending()->latest()->first();

        $props = [
            'visit' => ['id' => $kunjungan->id, 'patient' => $kunjungan->pasien->nama, 'medicalRecordNumber' => $kunjungan->pasien->no_rm],
            'procedure' => $service->plan($procedure), 'summary' => $service->summary($procedure), 'textVersion' => ProcedureConsentService::VERSION,
            'planHash' => $service->planHash($procedure),
            'canWrite' => $canWrite, 'requestId' => (string) Str::uuid(),
            'witnessSuggestion' => $canWrite ? $kunjungan->screening?->petugas?->nama : null,
            'diagnosisSuggestion' => $canWrite ? $kunjungan->pemeriksaan?->diagnosa()->where('jenis', 'utama')->value('nama_diagnosa') : null,
            'passwordVerified' => $canWrite && $this->hasRecentVerification($request, $kunjungan),
            'template' => $template ? [...$template->only(['id', 'version', 'mode', 'sop_reference', 'trigger_condition', 'explanation']), 'revision' => $template->revision()] : null,
            'templateArchived' => $latestTemplate?->trashed() ?? false,
            'signatureSessionUrl' => $signatureSession ? route('procedure-signature.show', $signatureSession) : null,
            'signatureAvailable' => $canWrite && ($request->isSecure() || app()->environment('local', 'testing')),
            'emergencyAvailable' => $canWrite && $kunjungan->screening?->prioritas_layanan === 'darurat',
            'storeUrl' => route('procedure-consent.store', [$kunjungan, $procedure]),
            'backUrl' => $isDoctor
                ? route('pelayanan.pemeriksaan.show', ['kunjungan' => $kunjungan, 'tab' => 'tindakan'])
                : route($kunjungan->status === 'tindakan' ? 'pelayanan.tindakan.show' : 'pelayanan.screening.show', $kunjungan),
            'history' => InformedConsent::with('recordedBy')->where('tindakan_kunjungan_id', $procedure->id)->latest('id')->get()->map(fn (InformedConsent $item): array => [
                'id' => $item->id, 'decision' => $item->decision, 'signer' => $item->nama_penandatangan, 'relationship' => $item->hubungan_pasien,
                'witness' => $item->witness_name, 'actor' => $item->recordedBy?->name, 'at' => $item->recorded_at?->format('d/m/Y H:i'),
                'reason' => $item->reason, 'explanation' => $item->explanation,
                'evidenceUrl' => $item->evidence_path ? route('procedure-consent.evidence', [$kunjungan, $procedure, $item]) : null,
            ]),
        ];

        return $request->expectsJson()
            ? response()->json($props)->header('Cache-Control', 'private, no-store')
            : Inertia::render('pelayanan/pemeriksaan/procedure-consent', $props);
    }

    public function store(Request $request, Kunjungan $kunjungan, TindakanKunjungan $procedure, ProcedureConsentService $service, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, $procedure, true);
        $startSignature = $request->boolean('start_signature');
        if ($startSignature) {
            abort_unless($request->isSecure() || app()->environment('local', 'testing'), 403);
            abort_unless(in_array($request->input('decision'), ['accepted', 'refused', 'withdrawn'], true), 422);
        }
        $oralDecision = $request->input('decision') === 'oral';
        $patientDecision = $oralDecision || in_array($request->input('decision'), ['accepted', 'refused', 'withdrawn'], true);
        $classificationOnly = $request->input('decision') === 'not_required';
        $emergency = $request->input('decision') === 'emergency';
        $recentlyVerified = $this->hasRecentVerification($request, $kunjungan);
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'current_id' => ['nullable', 'integer'],
            'plan_hash' => ['required', 'string', 'size:64'],
            'text_version' => ['required', Rule::in([ProcedureConsentService::VERSION])],
            'decision' => ['required', Rule::in(['accepted', 'oral', 'refused', 'withdrawn', 'not_required', 'emergency'])],
            'signature_password' => [Rule::requiredIf(! $recentlyVerified), 'nullable', 'current_password'],
            'remember_verification' => ['sometimes', 'boolean'],
            'template_id' => ['sometimes', 'nullable', 'integer'],
            'template_revision' => ['sometimes', 'nullable', 'string', 'size:64'],
            'written_trigger_absent' => ['sometimes', 'boolean'],
            'explained' => [Rule::excludeIf($emergency), 'accepted'],
            'emergency_without_available_signer' => [Rule::excludeIf(! $emergency), 'accepted'],
            'signer_name' => [Rule::requiredIf($patientDecision), 'nullable', 'string', 'min:2', 'max:150'],
            'signer_relationship' => [Rule::requiredIf($patientDecision), 'nullable', Rule::in(['patient', 'parent', 'spouse', 'guardian', 'other'])],
            'representative_verification' => [Rule::requiredIf($patientDecision && $request->input('signer_relationship') !== 'patient'), 'nullable', 'string', 'min:10', 'max:1000'],
            'witness_name' => [Rule::requiredIf(! $startSignature && $request->input('decision') === 'accepted'), 'nullable', 'string', 'max:150'],
            'reason' => [Rule::requiredIf(! $oralDecision), 'nullable', 'string', 'min:20', 'max:2000'],
            'explanation' => [Rule::requiredIf(! $classificationOnly), 'array:indication,method,benefits,risks,alternatives,without_procedure,prognosis'],
            'explanation.*' => ['required', 'string', 'min:3', 'max:3000'],
            'evidence' => [$startSignature ? 'prohibited' : Rule::requiredIf($patientDecision && ! $oralDecision), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
            'identity_verified' => [Rule::excludeIf(! $startSignature), 'accepted'],
        ], [
            'signature_password.required' => 'Masukkan kata sandi dokter. Konfirmasi sebelumnya mungkin sudah berakhir.',
            'emergency_without_available_signer.accepted' => 'Konfirmasi kondisi pasien dan ketersediaan pihak yang dapat memberi persetujuan.',
        ]);
        foreach (['indication', 'method', 'benefits', 'risks', 'alternatives', 'without_procedure', 'prognosis'] as $field) {
            if (! $classificationOnly && blank($data['explanation'][$field] ?? null)) {
                throw ValidationException::withMessages(['explanation.'.$field => 'Lengkapi penjelasan klinis tindakan.']);
            }
        }
        $evidenceHash = $request->hasFile('evidence') ? hash_file('sha256', $request->file('evidence')->getRealPath()) : null;
        $hash = hash('sha256', json_encode([...collect($data)->except(['signature_password', 'remember_verification', 'evidence'])->all(), 'evidence_sha256' => $evidenceHash], JSON_THROW_ON_ERROR));
        $path = null;
        $signing = null;
        try {
            DB::transaction(function () use ($request, $kunjungan, $procedure, $service, $audit, $data, $hash, $evidenceHash, $startSignature, &$path, &$signing): void {
                $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
                $procedure = $visit->tindakanKunjungan()->whereKey($procedure->id)->lockForUpdate()->firstOrFail();
                $this->authorizeVisit($request, $visit, $procedure, true);
                abort_if($startSignature && ! in_array($visit->status, ['pemeriksaan', 'tindakan'], true), 409, 'Kunjungan tidak berada pada tahap pemeriksaan atau tindakan.');
                if ($retry = InformedConsent::where('request_id', $data['request_id'])->first()) {
                    abort_unless((int) $retry->recorded_by === $request->user()->id && (int) $retry->tindakan_kunjungan_id === $procedure->id && hash_equals($retry->request_hash, $hash), 409, 'Permintaan sudah digunakan untuk keputusan berbeda.');

                    return;
                }
                abort_if($procedure->execution_status === 'completed' || $procedure->performed_at !== null, 409, 'Tindakan sudah dilaksanakan. Bukti baru tidak boleh dibuat seolah tercatat sebelum tindakan.');
                if (! hash_equals($service->planHash($procedure), $data['plan_hash'])) {
                    throw ValidationException::withMessages(['plan_hash' => 'Rencana tindakan sudah berubah. Muat ulang sebelum mencatat keputusan.']);
                }
                $latestTemplate = $service->publishedTemplate($visit, $procedure, includeArchived: true);
                $template = $latestTemplate?->trashed() ? null : $latestTemplate;
                if ($template?->id !== (isset($data['template_id']) ? (int) $data['template_id'] : null)
                    || $template?->revision() !== ($data['template_revision'] ?? null)) {
                    throw ValidationException::withMessages(['template_id' => 'Versi SOP tindakan berubah. Buka ulang dialog sebelum mencatat keputusan.']);
                }
                if ($data['decision'] === 'not_required' && $latestTemplate) {
                    throw ValidationException::withMessages(['decision' => 'Catat keputusan pasien sesuai bentuk persetujuan pada SOP.']);
                }
                $latest = $service->latest($procedure);
                if ($data['decision'] === 'oral') {
                    $permitsOral = $template?->mode === 'oral'
                        || ($template?->mode === 'conditional' && ! empty($data['written_trigger_absent']));
                    if (! $permitsOral || in_array($latest?->decision, ['accepted', 'refused', 'withdrawn'], true)) {
                        throw ValidationException::withMessages(['decision' => 'Pastikan SOP mengizinkan persetujuan lisan dan tidak ada keputusan tertulis sebelumnya.']);
                    }
                }
                if (($latest?->id ?? null) !== (isset($data['current_id']) ? (int) $data['current_id'] : null)) {
                    throw ValidationException::withMessages(['current_id' => 'Keputusan tindakan sudah berubah. Muat ulang riwayat.']);
                }
                if ($data['decision'] === 'not_required' && in_array($latest?->decision, ['accepted', 'oral', 'refused', 'withdrawn'], true)) {
                    throw ValidationException::withMessages(['decision' => 'Keputusan pasien sudah tercatat. Catat keputusan baru jika diperlukan untuk rencana ini.']);
                }
                if ($data['decision'] === 'withdrawn' && ! in_array($latest?->decision, ['accepted', 'oral'], true)) {
                    throw ValidationException::withMessages(['decision' => 'Tidak ada persetujuan aktif yang dapat dicabut.']);
                }
                if ($data['decision'] === 'emergency' && $visit->screening?->prioritas_layanan !== 'darurat') {
                    throw ValidationException::withMessages(['decision' => 'Pengecualian darurat memerlukan penilaian klinis darurat yang terdokumentasi.']);
                }
                if ($data['decision'] === 'emergency' && in_array($latest?->decision, ['refused', 'withdrawn'], true)) {
                    throw ValidationException::withMessages(['decision' => 'Penolakan atau pencabutan yang tercatat memerlukan keputusan baru pasien sebelum tindakan dapat diteruskan.']);
                }
                if ($service->hasPendingSignature($procedure)) {
                    throw ValidationException::withMessages(['decision' => 'Selesaikan atau batalkan sesi QR yang masih tercatat.']);
                }
                if ($startSignature) {
                    $token = bin2hex(random_bytes(32));
                    $signing = new ProcedureSignatureSession;
                    $signing->forceFill([
                        'procedure_id' => $procedure->id, 'doctor_id' => $request->user()->id,
                        'token_hash' => hash('sha256', $token),
                        'credentials' => ['token' => $token, 'code' => (string) random_int(100000, 999999)],
                        'expires_at' => now()->addMinutes(5),
                        'snapshot' => [
                            ...collect($data)->except(['signature_password', 'remember_verification', 'evidence', 'witness_name'])->all(),
                            'patient' => $visit->pasien->nama, 'medical_record_number' => $visit->pasien->no_rm,
                            'assigned_doctor_id' => $visit->dokter_id,
                            'signer_name' => $data['signer_relationship'] === 'patient' ? $visit->pasien->nama : $data['signer_name'],
                            'doctor' => $request->user()->name, 'plan' => $service->plan($procedure),
                            'consent_template' => $template?->only(['id', 'version', 'mode', 'sop_reference', 'trigger_condition']),
                        ],
                    ])->save();
                    $audit->record($request, 'procedure_signature.opened', $visit->pasien_id, $visit->id, ['session_id' => $signing->id]);

                    return;
                }
                $file = $request->file('evidence');
                if ($file) {
                    $path = $file->store('consents/procedures/'.$visit->id, 'local');
                    abort_unless(is_string($path), 503, 'Bukti belum dapat disimpan.');
                }
                $item = InformedConsent::create([
                    'kunjungan_id' => $visit->id, 'tindakan_id' => $procedure->tindakan_id, 'tindakan_kunjungan_id' => $procedure->id,
                    'supersedes_id' => $latest?->id, 'request_id' => $data['request_id'], 'request_hash' => $hash,
                    'jenis' => $data['decision'], 'decision' => $data['decision'], 'text_version' => ProcedureConsentService::VERSION,
                    'procedure_snapshot' => [
                        ...$service->plan($procedure),
                        ...($template ? ['consent_template' => [
                            ...$template->only(['id', 'version', 'mode', 'sop_reference', 'trigger_condition']),
                            'revision' => $template->revision(),
                        ]] : []),
                        ...($data['decision'] === 'oral' && $template?->mode === 'conditional' ? ['written_trigger_absent' => true] : []),
                        ...($data['decision'] === 'emergency' ? ['emergency_without_available_signer' => true] : []),
                    ], 'plan_hash' => $data['plan_hash'],
                    'explanation' => $data['explanation'] ?? null, 'nama_penandatangan' => $data['signer_name'] ?? null,
                    'hubungan_pasien' => $data['signer_relationship'] ?? null, 'representative_verification' => $data['representative_verification'] ?? null,
                    'witness_name' => $data['witness_name'] ?? null, 'reason' => $data['reason'] ?? null,
                    'evidence_path' => $path, 'evidence_name' => $file ? Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, '') : null,
                    'evidence_sha256' => $evidenceHash, 'recorded_by' => $request->user()->id, 'recorded_at' => now(), 'tanggal' => today(),
                ]);
                $audit->record($request, 'procedure_consent.recorded', $visit->pasien_id, $visit->id, ['procedure_id' => $procedure->id, 'consent_id' => $item->id,
                    'old_values' => ['decision' => $latest?->decision], 'new_values' => ['decision' => $item->decision], 'plan_hash' => $item->plan_hash, 'evidence_sha256' => $evidenceHash]);
            });
        } catch (Throwable $error) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        if ($request->boolean('remember_verification') && filled($data['signature_password'] ?? null)) {
            $request->session()->put('procedure_consent.verification', [
                'doctor' => $request->user()->getAuthIdentifier(),
                'visit' => $kunjungan->id,
                'expires' => now()->addMinutes(15)->timestamp,
            ]);
        }

        return $signing
            ? redirect()->route('procedure-signature.show', $signing)
            : back()->with('success', 'Penilaian dan keputusan tindakan tercatat. Bukti lama tetap tersimpan.');
    }

    private function hasRecentVerification(Request $request, Kunjungan $visit): bool
    {
        $verification = $request->session()->get('procedure_consent.verification', []);

        return ($verification['doctor'] ?? null) === $request->user()->getAuthIdentifier()
            && ($verification['visit'] ?? null) === $visit->id
            && ($verification['expires'] ?? 0) > now()->timestamp;
    }

    public function evidence(Request $request, Kunjungan $kunjungan, TindakanKunjungan $procedure, InformedConsent $consent, ClinicalAuditRecorder $audit): StreamedResponse
    {
        $this->authorizeVisit($request, $kunjungan, $procedure);
        abort_unless((int) $consent->tindakan_kunjungan_id === $procedure->id && $consent->evidence_path && Storage::disk('local')->exists($consent->evidence_path), 404);
        $actualHash = hash_file('sha256', Storage::disk('local')->path($consent->evidence_path));
        abort_unless(is_string($actualHash) && hash_equals((string) $consent->evidence_sha256, $actualHash), 409, 'Integritas bukti tindakan perlu diperiksa.');
        $audit->record($request, 'procedure_consent.download', $kunjungan->pasien_id, $kunjungan->id, ['consent_id' => $consent->id]);

        return Storage::disk('local')->download($consent->evidence_path, $consent->evidence_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
