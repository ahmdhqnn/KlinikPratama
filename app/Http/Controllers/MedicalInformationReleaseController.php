<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\DoctorAccessService;
use App\MedicalDocumentVerificationService;
use App\Models\Kunjungan;
use App\Models\MedicalDocumentVerification;
use App\Models\MedicalInformationReleaseEvent;
use App\Rules\ClinicalSigningCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MedicalInformationReleaseController extends Controller
{
    private function authorizeVisit(Request $request, Kunjungan $visit): void
    {
        abort_unless($request->user()->is_active && in_array($request->user()->role, ['dokter', 'pendaftaran'], true), 403);
        if ($request->user()->role === 'dokter') {
            abort_unless((int) $visit->dokter_id === app(DoctorAccessService::class)->doctorId($request->user()), 404);
        }
    }

    public function show(Request $request, Kunjungan $kunjungan)
    {
        $this->authorizeVisit($request, $kunjungan);
        app(ClinicalAuditRecorder::class)->record($request, 'information_release.view', $kunjungan->pasien_id, $kunjungan->id);
        $doctor = $request->user()->role === 'dokter';

        return Inertia::render('persuratan/pelepasan', [
            'visit' => ['id' => $kunjungan->id, 'number' => $kunjungan->no_kunjungan, 'patient' => $kunjungan->pasien->nama],
            'profileUrl' => route('pelayanan.pasien.show', [$kunjungan->pasien_id, 'bagian' => 'pelepasan']),
            'newRequestId' => (string) Str::uuid(),
            'canAuthorize' => $doctor,
            'documents' => $doctor ? $kunjungan->suratMedis()->orderByDesc('id')->get(['id', 'jenis', 'nomor_surat']) : [],
            'events' => MedicalInformationReleaseEvent::query()->select('medical_information_release_events.*')
                ->selectSub(MedicalInformationReleaseEvent::query()->from('medical_information_release_events as versions')
                    ->selectRaw('MAX(versions.version)')->whereColumn('versions.request_id', 'medical_information_release_events.request_id'), 'latest_version')
                ->where('kunjungan_id', $kunjungan->id)->orderByDesc('id')->paginate(20)
                ->through(fn ($event) => ['id' => $event->id, 'requestId' => $event->request_id, 'version' => $event->version,
                    'isCurrent' => (int) $event->version === (int) $event->latest_version,
                    'state' => $event->state, 'data' => $event->data, 'at' => $event->recorded_at->format('d/m/Y H:i')]),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan)
    {
        $this->authorizeVisit($request, $kunjungan);
        foreach (['requester', 'identity_verification', 'authority', 'recipient', 'purpose', 'reason', 'receipt'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'base_version' => ['required', 'integer', 'min:0'],
            'action' => ['required', Rule::in(['requested', 'authorized', 'denied', 'revoked', 'delivered'])],
            'requester' => ['required_if:action,requested', 'nullable', 'string', 'max:200'],
            'relationship' => ['required_if:action,requested', 'nullable', Rule::in(['patient', 'representative', 'institution'])],
            'identity_verification' => ['required_if:action,requested', 'nullable', 'string', 'min:10', 'max:1000'],
            'authority' => ['required_if:action,requested', 'nullable', 'string', 'min:10', 'max:2000'],
            'recipient' => ['required_if:action,requested', 'nullable', 'string', 'max:200'],
            'purpose' => ['required_if:action,requested', 'nullable', 'string', 'min:10', 'max:1000'],
            'scope' => ['required_if:action,requested', 'nullable', Rule::in(['ringkasan', 'rujukan', 'sakit', 'sehat', 'laboratorium', 'rontgen_gigi', 'salinan_rme', 'lainnya'])],
            'reason' => ['required_unless:action,requested', 'nullable', 'string', 'min:10', 'max:2000'],
            'document_id' => ['nullable', 'integer'],
            'delivery_method' => ['required_if:action,delivered', 'nullable', Rule::in(['hand', 'secure_channel'])],
            'receipt' => ['required_if:action,delivered', 'nullable', 'string', 'min:10', 'max:1000'],
            'recipient_verified' => [Rule::excludeIf($request->input('action') !== 'delivered'), 'required', 'accepted'],
            'signature_password' => ['required_unless:action,requested', 'nullable', new ClinicalSigningCredential],
        ]);
        DB::transaction(function () use ($request, $kunjungan, $data): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit);
            $previous = MedicalInformationReleaseEvent::where('request_id', $data['request_id'])->orderByDesc('version')->lockForUpdate()->first();
            abort_if($previous && (int) $previous->kunjungan_id !== $visit->id, 404);
            if ((int) ($previous?->version ?? 0) !== $data['base_version']) {
                throw ValidationException::withMessages(['base_version' => 'Register telah berubah. Muat ulang sebelum melanjutkan.']);
            }
            $action = $data['action'];
            if (! in_array($action, ['requested', 'delivered'], true)) {
                abort_unless($request->user()->role === 'dokter', 403);
            }
            $allowed = match ($previous?->state) {
                null => ['requested'], 'requested' => ['authorized', 'denied'], 'authorized' => ['delivered', 'revoked'], default => [],
            };
            if (! in_array($action, $allowed, true)) {
                throw ValidationException::withMessages(['action' => 'Perubahan status tidak sesuai tahap register.']);
            }
            $payload = $previous?->data ?? collect($data)->only(['requester', 'relationship', 'identity_verification', 'authority', 'recipient', 'purpose', 'scope'])->all();
            if ($action === 'authorized') {
                $examination = $visit->pemeriksaan;
                abort_unless($examination?->signed_at && app(ClinicalNoteRecorder::class)->verify($examination), 422, 'Catatan final terverifikasi diperlukan sebelum otorisasi.');
                if (($payload['scope'] ?? null) === 'salinan_rme') {
                    $payload['document'] = ['id' => null, 'kind' => 'salinan_rme', 'hash' => $examination->latest_note_hash];
                    $payload['authorized_by'] = $request->user()->id;
                } else {
                    if (empty($data['document_id'])) {
                        throw ValidationException::withMessages(['document_id' => 'Pilih surat medis terverifikasi yang akan dilepaskan.']);
                    }
                    $letter = $visit->suratMedis()->whereKey($data['document_id'])->where('jenis', $payload['scope'])->firstOrFail();
                    $proof = MedicalDocumentVerification::where('surat_medis_id', $letter->id)->lockForUpdate()->firstOrFail();
                    if (app(MedicalDocumentVerificationService::class)->status($proof) !== 'valid') {
                        throw ValidationException::withMessages(['document_id' => 'Dokumen tidak berlaku atau integritasnya perlu diperiksa.']);
                    }
                    $payload['document'] = ['id' => $letter->id, 'hash' => $proof->content_hash];
                    $payload['authorized_by'] = $request->user()->id;
                }
            }
            if ($action === 'delivered') {
                if (($payload['scope'] ?? null) === 'salinan_rme') {
                    $examination = $visit->pemeriksaan;
                    if (! $examination?->signed_at || ! app(ClinicalNoteRecorder::class)->verify($examination) || $examination->latest_note_hash !== ($payload['document']['hash'] ?? null)) {
                        throw ValidationException::withMessages(['action' => 'Catatan rekam medis telah berubah sejak otorisasi. Ajukan permintaan baru.']);
                    }
                } else {
                    $proof = MedicalDocumentVerification::where('surat_medis_id', $payload['document']['id'])->lockForUpdate()->firstOrFail();
                    if ($proof->content_hash !== $payload['document']['hash'] || app(MedicalDocumentVerificationService::class)->status($proof) !== 'valid') {
                        throw ValidationException::withMessages(['action' => 'Dokumen telah berubah atau dibatalkan. Ajukan permintaan baru.']);
                    }
                }
                $payload['delivery'] = ['method' => $data['delivery_method'], 'receipt' => $data['receipt'], 'recipient_verified' => true];
            }
            if ($action !== 'requested') {
                $payload['reason'] = $data['reason'];
            }
            $event = MedicalInformationReleaseEvent::create(['kunjungan_id' => $visit->id, 'request_id' => $data['request_id'],
                'version' => ($previous?->version ?? 0) + 1, 'state' => $action, 'data' => $payload,
                'actor_id' => $request->user()->id, 'recorded_at' => now()]);
            app(ClinicalAuditRecorder::class)->record($request, 'information_release.'.$action, $visit->pasien_id, $visit->id,
                ['release_event_id' => $event->id, 'request_id' => $data['request_id']]);
        });

        return back()->with('success', 'Register pelepasan informasi tersimpan.');
    }
}
