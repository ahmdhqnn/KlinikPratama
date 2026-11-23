<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\DoctorAccessService;
use App\Models\ClinicalNoteVersion;
use App\Models\ClinicalTerminology;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosisCodingCorrectionController extends Controller
{
    private function authorizeVisit(Request $request, Kunjungan $visit): void
    {
        abort_unless($request->user()->role === 'dokter', 403);
        Gate::authorize('viewClinical', $visit);
        abort_unless((int) $visit->dokter_id === app(DoctorAccessService::class)->doctorId($request->user()), 403);
        abort_unless(in_array($visit->status, Kunjungan::POST_EXAMINATION_STATUSES, true), 422, 'Kunjungan belum dapat dikoreksi.');
    }

    private function latest(Pemeriksaan $examination, ClinicalNoteRecorder $notes): ClinicalNoteVersion
    {
        abort_unless($examination->signed_at && $notes->verify($examination), 422, 'Versi final belum tersedia atau integritas catatan tidak valid.');

        return $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit): Response
    {
        $this->authorizeVisit($request, $kunjungan);
        $version = $this->latest($kunjungan->pemeriksaan()->firstOrFail(), $notes);
        $audit->record($request, 'clinical_coding.correction_view', $kunjungan->pasien_id, $kunjungan->id);

        return Inertia::render('pelayanan/pemeriksaan/diagnosis-correction', [
            'visit' => ['id' => $kunjungan->id, 'patient' => $kunjungan->pasien->nama, 'number' => $kunjungan->no_kunjungan],
            'diagnoses' => collect($version->payload['diagnoses'] ?? [])->values()->map(fn (array $item, int $index): array => [
                'key' => $index, 'code' => $item['code'] ?? null, 'name' => $item['name'],
                'type' => $item['type'], 'tooth' => $item['tooth_fdi'] ?? null,
                'status' => $item['coding_status'] ?? 'legacy',
            ])->all(),
            'history' => $version->payload['coding_reconciliations'] ?? [],
            'baseHash' => $version->content_hash, 'requestId' => (string) Str::uuid(),
            'storeUrl' => route('pelayanan.pemeriksaan.diagnosa.correction.store', $kunjungan),
            'searchUrl' => route('pelayanan.icd10.search'),
            'backUrl' => route('pelayanan.pemeriksaan.show', $kunjungan),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan);
        if (is_string($request->input('reason'))) {
            $request->merge(['reason' => trim($request->input('reason'))]);
        }
        $data = $request->validate([
            'base_hash' => ['required', 'string', 'size:64'], 'request_id' => ['required', 'uuid'],
            'diagnosis_key' => ['required', 'integer', 'min:0'], 'terminology_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'review_clinical_impact' => ['required', 'accepted'], 'signature_password' => ['required', 'current_password'],
        ]);
        $requestHash = hash('sha256', json_encode(collect($data)->except('signature_password')->all(), JSON_THROW_ON_ERROR));
        DB::transaction(function () use ($request, $kunjungan, $notes, $audit, $data, $requestHash): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit);
            $examination = $visit->pemeriksaan()->lockForUpdate()->firstOrFail();
            $version = $this->latest($examination, $notes);
            $payload = $version->payload;
            foreach ($payload['coding_reconciliations'] ?? [] as $previous) {
                if ($previous['request_id'] === $data['request_id']) {
                    abort_unless($previous['actor_id'] === $request->user()->id && hash_equals($previous['request_hash'], $requestHash), 409, 'ID permintaan sudah dipakai untuk koreksi berbeda.');

                    return;
                }
            }
            if (! hash_equals($version->content_hash, $data['base_hash'])) {
                throw ValidationException::withMessages(['base_hash' => 'Catatan sudah berubah. Muat ulang sebelum mengoreksi diagnosis.']);
            }
            $diagnoses = array_values($payload['diagnoses'] ?? []);
            abort_unless(array_key_exists($data['diagnosis_key'], $diagnoses), 404);
            $term = ClinicalTerminology::query()->whereKey($data['terminology_id'])->where('code_system', 'icd10_who')
                ->where('code_type', 'diagnosis')->where('is_active', true)->lockForUpdate()->first();
            if (! $term || blank($term->release)) {
                throw ValidationException::withMessages(['terminology_id' => 'Pilih diagnosis dari katalog ICD-10 WHO aktif dengan versi rilis yang tercatat.']);
            }
            $old = $diagnoses[$data['diagnosis_key']];
            if (($old['code'] ?? null) === $term->code && ($old['code_system'] ?? null) === 'icd10_who'
                && ($old['code_release'] ?? null) === $term->release && $old['name'] === $term->display && ($old['coding_status'] ?? null) === 'verified') {
                throw ValidationException::withMessages(['terminology_id' => 'Diagnosis sudah memakai kode dan versi terverifikasi yang sama.']);
            }
            $diagnoses[$data['diagnosis_key']] = [...$old,
                'code' => $term->code, 'code_system' => 'icd10_who', 'code_release' => $term->release,
                'name' => $term->display, 'coding_status' => 'verified', 'coding_unresolved_reason' => null,
                'coding_verified_by' => $request->user()->id, 'coding_verified_at' => now()->toIso8601String(),
            ];
            $payload['diagnoses'] = $diagnoses;
            $payload['coding_reconciliations'][] = [
                'request_id' => $data['request_id'], 'request_hash' => $requestHash,
                'actor_id' => $request->user()->id, 'at' => now()->toIso8601String(),
                'diagnosis_key' => $data['diagnosis_key'], 'old' => $old,
                'new' => $diagnoses[$data['diagnosis_key']], 'reason' => trim($data['reason']),
                'base_hash' => $version->content_hash, 'clinical_impact_reviewed' => true,
            ];
            $verified = collect($diagnoses)->every(fn (array $item): bool => ($item['coding_status'] ?? 'legacy') === 'verified')
                && collect($payload['treatments'] ?? [])->every(fn (array $item): bool => in_array($item['coding_status'] ?? 'legacy', ['verified', 'not_applicable'], true));
            $payload['coding'] = [...($payload['coding'] ?? []), 'status' => $verified ? 'verified' : 'unresolved'];
            if ($verified) {
                $payload['coding']['unresolved_reason'] = null;
                $payload['coding']['review_due_at'] = null;
            }
            $newVersion = $notes->recordAddendum($examination, $payload, $request->user()->id, trim($data['reason']));
            $examination->forceFill([
                'coding_status' => $payload['coding']['status'],
                'coding_unresolved_reason' => $payload['coding']['unresolved_reason'] ?? null,
                'coding_review_due_at' => $payload['coding']['review_due_at'] ?? null,
            ])->save();
            $audit->record($request, 'clinical_coding.corrected', $visit->pasien_id, $visit->id, [
                'clinical_note_version_id' => $newVersion->id, 'diagnosis_key' => $data['diagnosis_key'],
                'previous_hash' => $version->content_hash, 'content_hash' => $newVersion->content_hash,
            ]);
        });

        return back()->with('success', 'Koreksi coding tersimpan sebagai addendum. Catatan asli tetap tersedia.');
    }
}
