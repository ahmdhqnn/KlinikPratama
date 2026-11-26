<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DocumentVerificationQr;
use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\ProcedureSignatureSession;
use App\Models\TindakanKunjungan;
use App\ProcedureConsentService;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class ProcedureSignatureController extends Controller
{
    public function show(Request $request, ProcedureSignatureSession $signing, DocumentVerificationQr $qr): \Inertia\Response
    {
        $procedure = $signing->procedure;
        $visit = $procedure->kunjungan;
        Gate::authorize('viewClinical', $visit);
        $doctor = $request->user()->id === $signing->doctor_id;
        $canPair = $doctor && $signing->credentials && ! $signing->expires_at->isPast() && ! $signing->cancelled_at && ! $signing->signed_at;
        $url = $canPair ? route('procedure-signature.patient', $signing->credentials['token']) : null;

        return Inertia::render('pelayanan/pemeriksaan/procedure-signature', [
            'id' => $signing->id, 'procedure' => $signing->snapshot['plan']['name'],
            'decision' => $signing->snapshot['decision'], 'signer' => $signing->snapshot['signer_name'],
            'status' => match (true) {
                $signing->cancelled_at !== null => 'cancelled',
                $signing->consent_id !== null => 'complete',
                $signing->signed_at !== null => 'signed',
                $signing->expires_at->isPast() => 'expired',
                default => 'waiting',
            },
            'patientUrl' => $url, 'qr' => $url ? $qr->dataUrl($url) : null,
            'code' => $canPair ? $signing->credentials['code'] : null,
            'canWitness' => $request->user()->role === 'perawat' && ! $doctor,
            'canCancel' => $doctor,
            'backUrl' => route('procedure-consent.show', [$visit, $procedure]),
            'workflowUrl' => $request->user()->role === 'perawat'
                ? route('perawat.dashboard').'#saksi-persetujuan'
                : route('pelayanan.pemeriksaan.show', ['kunjungan' => $visit, 'tab' => 'tindakan']),
            'evidenceUrl' => $signing->consent_id ? route('procedure-consent.evidence', [$visit, $procedure, $signing->consent_id]) : null,
        ]);
    }

    public function patient(Request $request, string $token): Response
    {
        $signing = $this->patientSession($request, $token);
        $paired = $request->session()->get('procedure_signature.paired') === $signing->id;
        if ($paired) {
            $this->ensureCurrent($signing, $signing->procedure, $signing->procedure->kunjungan);
        }

        return $this->patientView([
            'snapshot' => $paired ? $signing->snapshot : null, 'token' => $token, 'submitted' => false,
        ]);
    }

    public function pair(Request $request, string $token, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $signing = $this->patientSession($request, $token);
        $data = $request->validate(['pair_code' => ['required', 'digits:6']]);
        if (! hash_equals($signing->credentials['code'], (string) $data['pair_code'])) {
            $locked = DB::transaction(function () use ($request, $signing, $audit): bool {
                [$row, , $visit] = $this->lockSession($signing);
                $attempts = (int) $row->failed_attempts + 1;
                if ($attempts >= 5) {
                    $row->forceFill(['failed_attempts' => $attempts, 'cancelled_at' => now(), 'credentials' => null])->save();
                    $audit->record($request, 'procedure_signature.pair_locked', $visit->pasien_id, $visit->id, ['session_id' => $row->id, 'outcome' => 'denied']);

                    return true;
                }
                $row->forceFill(['failed_attempts' => $attempts])->save();
                $audit->record($request, 'procedure_signature.pair_failed', $visit->pasien_id, $visit->id, ['session_id' => $row->id, 'outcome' => 'denied']);

                return false;
            });
            abort_if($locked, 410, 'Sesi tanda tangan dibatalkan karena terlalu banyak percobaan kode tidak sesuai.');
            throw ValidationException::withMessages(['pair_code' => 'Kode pendamping tidak cocok.']);
        }
        $request->session()->put('procedure_signature.paired', $signing->id);

        return redirect()->route('procedure-signature.patient', $token);
    }

    public function sign(Request $request, string $token, ClinicalAuditRecorder $audit): Response
    {
        $signing = $this->patientSession($request, $token);
        abort_unless($request->session()->get('procedure_signature.paired') === $signing->id, 403);
        $payload = $request->validate(['confirmed' => ['accepted'], 'signature' => ['required', 'json', 'max:65000']]);
        $strokes = validator(['strokes' => json_decode($payload['signature'], true)], [
            'strokes' => ['required', 'array', 'list', 'max:40'],
            'strokes.*' => ['required', 'array', 'list', 'min:2', 'max:250'],
            'strokes.*.*' => ['required', 'array:x,y'],
            'strokes.*.*.x' => ['required', 'integer', 'between:0,599'],
            'strokes.*.*.y' => ['required', 'integer', 'between:0,199'],
        ])->validate()['strokes'];
        $points = collect($strokes)->collapse()->unique(fn (array $point): string => $point['x'].':'.$point['y']);
        if ($points->count() < 4) {
            throw ValidationException::withMessages(['signature' => 'Bubuhkan tanda tangan terlebih dahulu.']);
        }

        DB::transaction(function () use ($request, $audit, $signing, $strokes): void {
            [$row, $procedure, $visit] = $this->lockSession($signing);
            abort_if($row->expires_at->isPast() || $row->signed_at || $row->cancelled_at, 410);
            $this->ensureCurrent($row, $procedure, $visit);
            $row->forceFill(['signature' => $strokes, 'signed_at' => now(), 'credentials' => null])->save();
            $audit->record($request, 'procedure_signature.captured', $visit->pasien_id, $visit->id, ['session_id' => $row->id]);
        });
        $request->session()->forget('procedure_signature.paired');

        return $this->patientView(['snapshot' => null, 'token' => null, 'submitted' => true]);
    }

    public function witness(Request $request, ProcedureSignatureSession $signing, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $request->validate(['witnessed' => ['accepted']]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $signing, $audit, &$path): void {
                [$row, $procedure, $visit] = $this->lockSession($signing);
                Gate::authorize('viewClinical', $visit);
                abort_unless($request->user()->role === 'perawat' && $request->user()->id !== $row->doctor_id, 403);
                abort_unless($row->signed_at && ! $row->cancelled_at && ! $row->consent_id, 409);
                abort_unless($row->signed_at->isToday(), 409, 'Konfirmasi saksi harus dicatat pada hari yang sama.');
                $this->ensureCurrent($row, $procedure, $visit);
                $data = $row->snapshot;
                $image = imagecreatetruecolor(600, 200);
                imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
                $ink = imagecolorallocate($image, 20, 20, 20);
                imagesetthickness($image, 3);
                foreach ($row->signature as $stroke) {
                    for ($i = 1; $i < count($stroke); $i++) {
                        imageline($image, $stroke[$i - 1]['x'], $stroke[$i - 1]['y'], $stroke[$i]['x'], $stroke[$i]['y'], $ink);
                    }
                }
                ob_start();
                imagepng($image);
                $signatureImage = 'data:image/png;base64,'.base64_encode(ob_get_clean());
                $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
                $pdf->loadHtml(view('procedure-signature-pdf', [
                    'snapshot' => $data, 'signedAt' => $row->signed_at, 'issuedAt' => now(),
                    'witness' => $request->user()->name, 'signatureImage' => $signatureImage,
                ])->render(), 'UTF-8');
                $pdf->setPaper('A4');
                $pdf->render();
                $bytes = $pdf->output();
                $archive = 'consents/procedures/'.$visit->id.'/'.$row->id.'.pdf';
                abort_if(Storage::disk('local')->exists($archive), 409, 'Arsip PDF sudah ada dan perlu ditinjau.');
                $path = $archive;
                abort_unless(Storage::disk('local')->put($path, $bytes), 503, 'PDF bukti belum dapat disimpan.');

                $record = InformedConsent::create([
                    'kunjungan_id' => $visit->id, 'tindakan_id' => $procedure->tindakan_id, 'tindakan_kunjungan_id' => $procedure->id,
                    'supersedes_id' => $data['current_id'] ?? null, 'request_id' => $row->id, 'request_hash' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
                    'jenis' => $data['decision'], 'decision' => $data['decision'], 'text_version' => $data['text_version'],
                    'procedure_snapshot' => [
                        ...$data['plan'], 'consent_template' => $data['consent_template'],
                        'signature_session' => $row->id, 'patient_signed_at' => $row->signed_at->toIso8601String(),
                        'witness_user_id' => $request->user()->id, 'doctor_confirmed_at' => $row->created_at->toIso8601String(),
                    ],
                    'explanation' => $data['explanation'], 'plan_hash' => $data['plan_hash'], 'reason' => $data['reason'],
                    'nama_penandatangan' => $data['signer_name'], 'hubungan_pasien' => $data['signer_relationship'],
                    'representative_verification' => $data['representative_verification'] ?? null,
                    'witness_name' => $request->user()->name, 'recorded_by' => $row->doctor_id, 'recorded_at' => now(), 'tanggal' => today(),
                    'evidence_path' => $path, 'evidence_name' => 'persetujuan-tindakan.pdf', 'evidence_sha256' => hash('sha256', $bytes),
                ]);
                $row->forceFill(['witness_id' => $request->user()->id, 'consent_id' => $record->id, 'signature' => null])->save();
                $audit->record($request, 'procedure_signature.witnessed', $visit->pasien_id, $visit->id, [
                    'session_id' => $row->id, 'consent_id' => $record->id, 'evidence_sha256' => $record->evidence_sha256,
                ]);
            });
        } catch (Throwable $error) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('success', 'Saksi dikonfirmasi dan PDF bukti diarsipkan.');
    }

    public function cancel(Request $request, ProcedureSignatureSession $signing, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($request, $signing, $data, $audit): void {
            [$row, , $visit] = $this->lockSession($signing);
            Gate::authorize('viewClinical', $visit);
            abort_unless($request->user()->role === 'dokter' && $request->user()->id === $row->doctor_id, 403);
            abort_if($row->consent_id || $row->cancelled_at, 409);
            $row->forceFill(['cancelled_at' => now(), 'credentials' => null])->save();
            $audit->record($request, 'procedure_signature.cancelled', $visit->pasien_id, $visit->id, ['session_id' => $row->id, 'reason' => $data['reason']]);
        });

        return back()->with('success', 'Sesi QR dibatalkan.');
    }

    private function patientSession(Request $request, string $token): ProcedureSignatureSession
    {
        abort_unless($request->isSecure() || app()->environment('local', 'testing'), 403);
        $row = ProcedureSignatureSession::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($row->expires_at->isPast() || $row->signed_at || $row->cancelled_at, 410);

        return $row;
    }

    private function patientView(array $data): Response
    {
        return response()->view('procedure-signature-patient', $data, 200, [
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function lockSession(ProcedureSignatureSession $signing): array
    {
        $visit = Kunjungan::lockForUpdate()->findOrFail($signing->procedure->kunjungan_id);
        $procedure = $visit->tindakanKunjungan()->withoutGlobalScope('active_clinical_entries')->lockForUpdate()->findOrFail($signing->procedure_id);
        $row = ProcedureSignatureSession::lockForUpdate()->findOrFail($signing->id);

        return [$row, $procedure, $visit];
    }

    private function ensureCurrent(ProcedureSignatureSession $row, TindakanKunjungan $procedure, Kunjungan $visit): void
    {
        $service = app(ProcedureConsentService::class);
        $data = $row->snapshot;
        $template = $service->publishedTemplate($visit, $procedure);
        abort_if(in_array($visit->status, ['batal', 'selesai'], true) || $procedure->performed_at || $procedure->execution_status === 'completed'
            || $procedure->voided_at
            || ! hash_equals($service->planHash($procedure), $data['plan_hash'])
            || $service->latest($procedure)?->id !== (isset($data['current_id']) ? (int) $data['current_id'] : null)
            || $template?->revision() !== ($data['template_revision'] ?? null)
            || $visit->dokter_id !== ($data['assigned_doctor_id'] ?? null)
            || $visit->pasien->nama !== $data['patient'] || $visit->pasien->no_rm !== $data['medical_record_number'],
            409, 'Rencana, identitas pasien, atau keputusan berubah. Dokter perlu membuka sesi baru.');
    }
}
