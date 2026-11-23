<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DoctorAccessService;
use App\MedicalDocumentVerificationService;
use App\Models\MedicalDocumentVerification;
use App\Models\SuratMedis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class MedicalDocumentVerificationController extends Controller
{
    public function show(string $token, MedicalDocumentVerificationService $service, ClinicalAuditRecorder $audit): Response
    {
        $verification = MedicalDocumentVerification::with('letter')->where('token_hash', hash('sha256', $token))->firstOrFail();
        $audit->record(request(), 'medical_document.verify', metadata: ['document_id' => $verification->surat_medis_id]);

        // Public proof exposes no patient, clinical, letter number, signature or actor data.
        Inertia::flushShared();

        return Inertia::render('persuratan/verifikasi', [
            'status' => $service->status($verification), 'issuedAt' => $verification->issued_at->format('d/m/Y H:i'),
        ])->toResponse(request())->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function void(Request $request, SuratMedis $suratMedis, ClinicalAuditRecorder $audit): RedirectResponse
    {
        abort_unless($request->user()->role === 'dokter', 403);
        Gate::authorize('viewClinical', $suratMedis->kunjungan);
        abort_unless((int) $suratMedis->kunjungan->dokter_id === app(DoctorAccessService::class)->doctorId($request->user()), 403);
        if (is_string($request->input('reason'))) {
            $request->merge(['reason' => trim($request->input('reason'))]);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000'], 'signature_password' => ['required', 'current_password']]);
        DB::transaction(function () use ($request, $suratMedis, $audit, $data): void {
            $verification = MedicalDocumentVerification::where('surat_medis_id', $suratMedis->id)->lockForUpdate()->firstOrFail();
            if (DB::table('medical_document_verification_events')->where('verification_id', $verification->id)->where('action', 'void')->exists()) {
                return;
            }
            DB::table('medical_document_verification_events')->insert([
                'verification_id' => $verification->id, 'action' => 'void', 'reason' => $data['reason'],
                'actor_id' => $request->user()->id, 'recorded_at' => now(),
            ]);
            $audit->record($request, 'medical_letter.void', $suratMedis->kunjungan->pasien_id, $suratMedis->kunjungan_id, ['document_id' => $suratMedis->id]);
        });

        return back()->with('success', 'Dokumen dibatalkan. Arsip dan riwayat penerbitan tetap tersimpan.');
    }
}
