<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\TindakanKunjungan;
use App\ProcedureConsentService;
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

    public function show(Request $request, Kunjungan $kunjungan, TindakanKunjungan $procedure, ProcedureConsentService $service, ClinicalAuditRecorder $audit): Response
    {
        $this->authorizeVisit($request, $kunjungan, $procedure);
        $audit->record($request, 'procedure_consent.view', $kunjungan->pasien_id, $kunjungan->id, ['procedure_id' => $procedure->id]);

        return Inertia::render('pelayanan/pemeriksaan/procedure-consent', [
            'visit' => ['id' => $kunjungan->id, 'patient' => $kunjungan->pasien->nama, 'medicalRecordNumber' => $kunjungan->pasien->no_rm],
            'procedure' => $service->plan($procedure), 'summary' => $service->summary($procedure), 'textVersion' => ProcedureConsentService::VERSION,
            'planHash' => $service->planHash($procedure),
            'canWrite' => $request->user()->role === 'dokter', 'requestId' => (string) Str::uuid(),
            'storeUrl' => route('procedure-consent.store', [$kunjungan, $procedure]),
            'backUrl' => route($request->user()->role === 'dokter' ? 'pelayanan.pemeriksaan.show' : 'pelayanan.tindakan.show', $kunjungan),
            'history' => InformedConsent::with('recordedBy')->where('tindakan_kunjungan_id', $procedure->id)->latest('id')->get()->map(fn (InformedConsent $item): array => [
                'id' => $item->id, 'decision' => $item->decision, 'signer' => $item->nama_penandatangan, 'relationship' => $item->hubungan_pasien,
                'witness' => $item->witness_name, 'actor' => $item->recordedBy?->name, 'at' => $item->recorded_at?->format('d/m/Y H:i'),
                'reason' => $item->reason, 'explanation' => $item->explanation,
                'evidenceUrl' => $item->evidence_path ? route('procedure-consent.evidence', [$kunjungan, $procedure, $item]) : null,
            ]),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan, TindakanKunjungan $procedure, ProcedureConsentService $service, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, $procedure, true);
        $patientDecision = in_array($request->input('decision'), ['accepted', 'refused', 'withdrawn'], true);
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'current_id' => ['nullable', 'integer'],
            'plan_hash' => ['required', 'string', 'size:64'],
            'text_version' => ['required', Rule::in([ProcedureConsentService::VERSION])],
            'decision' => ['required', Rule::in(['accepted', 'refused', 'withdrawn', 'not_required', 'emergency'])],
            'signature_password' => ['required', 'current_password'], 'explained' => ['required', 'accepted'],
            'signer_name' => [Rule::requiredIf($patientDecision), 'nullable', 'string', 'min:2', 'max:150'],
            'signer_relationship' => [Rule::requiredIf($patientDecision), 'nullable', Rule::in(['patient', 'parent', 'spouse', 'guardian', 'other'])],
            'representative_verification' => [Rule::requiredIf($patientDecision && $request->input('signer_relationship') !== 'patient'), 'nullable', 'string', 'min:10', 'max:1000'],
            'witness_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'explanation' => ['required', 'array:indication,method,benefits,risks,alternatives,without_procedure,prognosis'],
            'explanation.*' => ['required', 'string', 'min:3', 'max:3000'],
            'evidence' => [Rule::requiredIf($patientDecision), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        foreach (['indication', 'method', 'benefits', 'risks', 'alternatives', 'without_procedure', 'prognosis'] as $field) {
            if (blank($data['explanation'][$field] ?? null)) {
                throw ValidationException::withMessages(['explanation.'.$field => 'Lengkapi penjelasan klinis tindakan.']);
            }
        }
        $evidenceHash = $request->hasFile('evidence') ? hash_file('sha256', $request->file('evidence')->getRealPath()) : null;
        $hash = hash('sha256', json_encode([...collect($data)->except(['signature_password', 'evidence'])->all(), 'evidence_sha256' => $evidenceHash], JSON_THROW_ON_ERROR));
        $path = null;
        try {
            DB::transaction(function () use ($request, $kunjungan, $procedure, $service, $audit, $data, $hash, $evidenceHash, &$path): void {
                $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
                $procedure = $visit->tindakanKunjungan()->whereKey($procedure->id)->lockForUpdate()->firstOrFail();
                $this->authorizeVisit($request, $visit, $procedure, true);
                if ($retry = InformedConsent::where('request_id', $data['request_id'])->first()) {
                    abort_unless((int) $retry->recorded_by === $request->user()->id && (int) $retry->tindakan_kunjungan_id === $procedure->id && hash_equals($retry->request_hash, $hash), 409, 'Permintaan sudah digunakan untuk keputusan berbeda.');

                    return;
                }
                abort_if($procedure->execution_status === 'completed' || $procedure->performed_at !== null, 409, 'Tindakan sudah dilaksanakan. Bukti baru tidak boleh dibuat seolah tercatat sebelum tindakan.');
                if (! hash_equals($service->planHash($procedure), $data['plan_hash'])) {
                    throw ValidationException::withMessages(['plan_hash' => 'Rencana tindakan sudah berubah. Muat ulang sebelum mencatat keputusan.']);
                }
                $latest = $service->latest($procedure);
                if (($latest?->id ?? null) !== (isset($data['current_id']) ? (int) $data['current_id'] : null)) {
                    throw ValidationException::withMessages(['current_id' => 'Keputusan tindakan sudah berubah. Muat ulang riwayat.']);
                }
                if ($data['decision'] === 'not_required' && in_array($latest?->decision, ['refused', 'withdrawn'], true)) {
                    throw ValidationException::withMessages(['decision' => 'Penolakan atau pencabutan tindakan memerlukan keputusan pasien baru, bukan perubahan klasifikasi.']);
                }
                if ($data['decision'] === 'withdrawn' && $latest?->decision !== 'accepted') {
                    throw ValidationException::withMessages(['decision' => 'Tidak ada persetujuan aktif yang dapat dicabut.']);
                }
                if ($data['decision'] === 'emergency' && $visit->screening?->prioritas_layanan !== 'darurat') {
                    throw ValidationException::withMessages(['decision' => 'Pengecualian darurat memerlukan penilaian klinis darurat yang terdokumentasi.']);
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
                    'procedure_snapshot' => $service->plan($procedure), 'plan_hash' => $data['plan_hash'],
                    'explanation' => $data['explanation'], 'nama_penandatangan' => $data['signer_name'] ?? null,
                    'hubungan_pasien' => $data['signer_relationship'] ?? null, 'representative_verification' => $data['representative_verification'] ?? null,
                    'witness_name' => $data['witness_name'] ?? null, 'reason' => $data['reason'],
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

        return back()->with('success', 'Penilaian dan keputusan tindakan tercatat. Bukti lama tetap tersimpan.');
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
