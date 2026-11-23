<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\EffectiveClinicalNote;
use App\Models\Kunjungan;
use App\Models\PharmacyClinicalReview;
use App\PharmacyClinicalReviewService;
use App\PharmacyStaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PharmacyClinicalReviewController extends Controller
{
    public function show(Request $request, Kunjungan $kunjungan, PharmacyClinicalReviewService $service)
    {
        abort_unless($request->user()->role === 'farmasi', 403);
        abort_unless($kunjungan->status === 'farmasi' || $kunjungan->farmasi?->status === 'selesai', 404);
        $credential = app(PharmacyStaffAccess::class)->current($request->user());
        $effective = app(EffectiveClinicalNote::class)->resolve($kunjungan->pemeriksaan);
        $sourceHash = $service->hash($kunjungan);
        $latestReview = $service->latest($kunjungan);
        app(ClinicalAuditRecorder::class)->record($request, 'pharmacy.review_view', $kunjungan->pasien_id, $kunjungan->id);

        return Inertia::render('pelayanan/farmasi/review', [
            'visit' => ['id' => $kunjungan->id, 'number' => $kunjungan->no_kunjungan, 'patient' => $kunjungan->pasien->nama],
            'qualification' => $credential?->qualification ?? 'unverified', 'sourceHash' => $sourceHash,
            'correctionNotice' => ($effective['payload']['correction_context']['downstream_review_required'] ?? false)
                && (! $latestReview || ! hash_equals($latestReview->source_hash, $sourceHash))
                ? ['version' => $effective['version'], 'afterDispensing' => $kunjungan->farmasi?->status === 'selesai'] : null,
            'canReview' => $credential?->qualification === 'apoteker',
            'alreadyDispensed' => $kunjungan->farmasi?->status === 'selesai',
            'indications' => collect($effective['payload']['diagnoses'] ?? [])->map(fn (array $diagnosis) => [
                'code' => $diagnosis['code'] ?? null, 'name' => $diagnosis['name'] ?? null,
            ])->values(),
            'currentId' => $service->latest($kunjungan)?->id,
            'history' => PharmacyClinicalReview::where('kunjungan_id', $kunjungan->id)->latest('id')->paginate(10)
                ->through(fn ($review) => ['id' => $review->id, 'decision' => $review->decision, 'data' => $review->data, 'at' => $review->recorded_at->format('d/m/Y H:i')]),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan, PharmacyClinicalReviewService $service)
    {
        abort_unless($request->user()->role === 'farmasi', 403);
        $data = $request->validate([
            'source_hash' => ['required', 'string', 'size:64'], 'current_id' => ['nullable', 'integer'],
            'decision' => ['required', Rule::in(['approved', 'hold', 'follow_up'])],
            'administrative_review' => ['required', 'accepted'], 'pharmaceutical_review' => ['required', 'accepted'], 'clinical_review' => ['required', 'accepted'],
            'notes' => ['required', 'string', 'min:10', 'max:3000'],
            'clarification' => ['required', Rule::in(['not_needed', 'pending', 'resolved'])],
            'clarification_reference' => ['required_unless:clarification,not_needed', 'nullable', 'string', 'min:10', 'max:2000'],
            'signature_password' => ['required', 'current_password'],
        ]);
        DB::transaction(function () use ($request, $kunjungan, $service, $data): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $dispensed = $visit->farmasi?->status === 'selesai';
            abort_unless($visit->status === 'farmasi' || $dispensed, 422);
            if (($dispensed && $data['decision'] !== 'follow_up') || (! $dispensed && $data['decision'] === 'follow_up')) {
                throw ValidationException::withMessages(['decision' => 'Gunakan tindak lanjut pasca-penyerahan untuk layanan yang sudah selesai.']);
            }
            abort_unless($visit->pemeriksaan?->signed_at && app(ClinicalNoteRecorder::class)->verify($visit->pemeriksaan), 422, 'Catatan dokter final terverifikasi diperlukan sebelum review resep.');
            $credential = app(PharmacyStaffAccess::class)->current($request->user());
            abort_unless($credential?->qualification === 'apoteker', 403, 'Review klinis memerlukan apoteker terverifikasi.');
            if ((int) ($service->latest($visit)?->id ?? 0) !== (int) ($data['current_id'] ?? 0) || ! hash_equals($service->hash($visit), $data['source_hash'])) {
                throw ValidationException::withMessages(['source_hash' => 'Resep, pengkajian atau rencana penyerahan berubah. Muat ulang dan periksa kembali.']);
            }
            if ($data['decision'] === 'approved' && $data['clarification'] === 'pending') {
                throw ValidationException::withMessages(['decision' => 'Selesaikan klarifikasi dokter sebelum menyetujui resep.']);
            }
            if (mb_strlen(trim($data['notes'])) < 10) {
                throw ValidationException::withMessages(['notes' => 'Catat hasil review secara memadai.']);
            }
            $review = PharmacyClinicalReview::create(['kunjungan_id' => $visit->id, 'credential_id' => $credential->id,
                'actor_id' => $request->user()->id, 'source_hash' => $data['source_hash'], 'decision' => $data['decision'],
                'data' => ['administrative_review' => true, 'pharmaceutical_review' => true, 'clinical_review' => true,
                    'notes' => trim($data['notes']), 'clarification' => $data['clarification'], 'clarification_reference' => $data['clarification_reference'] ?? null,
                    'source' => $service->source($visit)], 'recorded_at' => now()]);
            app(ClinicalAuditRecorder::class)->record($request, 'pharmacy.review_recorded', $visit->pasien_id, $visit->id,
                ['review_id' => $review->id, 'decision' => $review->decision]);
        });

        return back()->with('success', 'Review farmasi tersimpan.');
    }
}
