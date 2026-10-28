<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\Http\Controllers\Controller;
use App\Models\Kunjungan;
use App\Models\TindakanKunjungan;
use App\PerawatClinicResolver;
use App\VisitCareWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TindakanPerawatController extends Controller
{
    public function index(Request $request, PerawatClinicResolver $clinics): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:pending,completed'],
        ]);
        $clinicId = $clinics->clinicId($request->user());
        $nurseTreatments = fn ($query) => $query->where('execution_role', 'perawat');
        $pendingTreatments = fn ($query) => $query->where('execution_role', 'perawat')->where('execution_status', 'pending');
        $clinicVisits = Kunjungan::query()->where('poliklinik_id', $clinicId)
            ->whereHas('tindakanKunjungan', $nurseTreatments)
            ->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'));
        $withDetails = fn ($query) => $query->with(['pasien', 'poliklinik', 'dokter'])
            ->withCount([
                'tindakanKunjungan as pending_count' => $pendingTreatments,
                'tindakanKunjungan as completed_count' => fn ($treatments) => $treatments->where('execution_role', 'perawat')->where('execution_status', 'completed'),
            ]);
        $allVisits = $withDetails((clone $clinicVisits)
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($matches) => $matches
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn ($patients) => $patients->where(fn ($identity) => $identity
                    ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))))
            ->when(($filters['status'] ?? null) === 'pending', fn ($query) => $query->whereHas('tindakanKunjungan', $pendingTreatments))
            ->when(($filters['status'] ?? null) === 'completed', fn ($query) => $query->whereDoesntHave('tindakanKunjungan', $pendingTreatments)))
            ->orderByDesc('tanggal')->latest('created_at')->latest('id')->paginate(10)->withQueryString();
        $todayVisits = $withDetails((clone $clinicVisits)->whereDate('tanggal', today()))
            ->orderBy('created_at')->orderBy('id')->paginate(10, ['*'], 'today_page')->withQueryString();
        $todaySummary = (clone $clinicVisits)->whereDate('tanggal', today());
        $mapVisit = fn (Kunjungan $visit): array => [
            'id' => $visit->id, 'number' => $visit->no_kunjungan, 'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi', 'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan', 'date' => $visit->tanggal->format('d/m/Y'),
            'status' => $visit->status, 'pendingCount' => $visit->pending_count, 'completedCount' => $visit->completed_count,
            'url' => route('pelayanan.tindakan.show', $visit),
        ];

        return Inertia::render('pelayanan/tindakan/index', [
            'stats' => [
                'todayTotal' => (clone $todaySummary)->count(),
                'todayPending' => (clone $todaySummary)->whereHas('tindakanKunjungan', $pendingTreatments)->count(),
                'todayCompleted' => (clone $todaySummary)->whereDoesntHave('tindakanKunjungan', $pendingTreatments)->count(),
                'allPending' => (clone $clinicVisits)->whereHas('tindakanKunjungan', $pendingTreatments)->count(),
            ],
            'filters' => ['search' => $filters['search'] ?? '', 'tanggal' => $filters['tanggal'] ?? '', 'status' => $filters['status'] ?? ''],
            'todayVisits' => $this->pageData($todayVisits->through($mapVisit)),
            'visits' => $this->pageData($allVisits->through($mapVisit)),
        ]);
    }

    public function show(Request $request, Kunjungan $kunjungan, PerawatClinicResolver $clinics, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit): Response
    {
        $this->authorizeVisit($request, $kunjungan, $clinics);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'pemeriksaan', 'tindakanKunjungan.performedBy', 'tindakanKunjungan.tindakan']);
        abort_unless($kunjungan->pemeriksaan?->signed_at && $notes->verify($kunjungan->pemeriksaan), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
        $payload = $kunjungan->pemeriksaan->clinicalNoteVersions()->latest('version')->firstOrFail()->payload;
        $audit->record($request, 'nursing_procedure.view', $kunjungan->pasien_id, $kunjungan->id);

        return Inertia::render('pelayanan/tindakan/show', [
            'visit' => [
                'id' => $kunjungan->id, 'number' => $kunjungan->no_kunjungan, 'patient' => $kunjungan->pasien->nama,
                'medicalRecordNumber' => $kunjungan->pasien->no_rm, 'clinic' => $kunjungan->poliklinik->nama,
                'doctor' => $kunjungan->dokter?->nama, 'status' => $kunjungan->status,
                'allergies' => $payload['riwayat_alergi'] ?? $kunjungan->pasien->riwayat_alergi,
                'education' => $payload['edukasi'] ?? null, 'nonPharmacological' => $payload['non_farmakologis'] ?? null,
                'diagnoses' => collect($payload['diagnoses'] ?? [])->pluck('name')->all(),
                'hasMedication' => $kunjungan->resep?->resepObat()->where('is_resep_luar', false)->exists() ?? false,
                'recordUrl' => route('pelayanan.pasien.rekam-medis', $kunjungan->pasien_id),
                'treatments' => $kunjungan->tindakanKunjungan->where('execution_role', 'perawat')->map(fn (TindakanKunjungan $item): array => [
                    'id' => $item->id, 'name' => $item->nama_tindakan ?? $item->nama_tindakan_manual ?? $item->tindakan?->nama,
                    'code' => $item->kode_icd9cm, 'quantity' => $item->jumlah, 'toothFdi' => $item->tooth_fdi,
                    'instructions' => $item->catatan, 'status' => $item->execution_status,
                    'result' => $item->execution_result, 'performedBy' => $item->performedBy?->name,
                    'performedAt' => $item->performed_at?->format('d/m/Y H:i'),
                    'completeUrl' => route('pelayanan.tindakan.complete', [$kunjungan, $item]),
                ])->values(),
            ],
        ]);
    }

    public function complete(Request $request, Kunjungan $kunjungan, TindakanKunjungan $tindakanKunjungan, PerawatClinicResolver $clinics, VisitCareWorkflow $workflow, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, $clinics);
        abort_unless($tindakanKunjungan->kunjungan_id === $kunjungan->id, 404);
        $data = $request->validate(['execution_result' => ['required', 'string', 'min:3', 'max:5000']]);

        DB::transaction(function () use ($request, $kunjungan, $tindakanKunjungan, $clinics, $workflow, $notes, $audit, $data): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit, $clinics);
            $procedure = $visit->tindakanKunjungan()->whereKey($tindakanKunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($procedure->execution_role === 'perawat', 422, 'Tindakan ini tidak ditugaskan kepada perawat.');
            if ($procedure->execution_status === 'completed') {
                return;
            }
            abort_unless($visit->status === 'tindakan' && $procedure->execution_status === 'pending', 422, 'Kunjungan tidak berada pada tahap tindakan perawat.');
            $examination = $visit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
            $workflow->consumeSupplies($procedure, $visit, $request->user());
            $procedure->update([
                'execution_status' => 'completed', 'performed_by' => $request->user()->id,
                'performed_at' => now(), 'execution_result' => trim($data['execution_result']),
            ]);
            $workflow->advance($visit);
            $audit->record($request, 'nursing_procedure.complete', $visit->pasien_id, $visit->id, ['procedure_id' => $procedure->id, 'next_status' => $visit->status]);
        });

        return redirect()->route('pelayanan.tindakan.show', $kunjungan)->with('success', 'Pelaksanaan tindakan tersimpan. Status kunjungan telah diperbarui.');
    }

    private function authorizeVisit(Request $request, Kunjungan $visit, PerawatClinicResolver $clinics): void
    {
        abort_unless($visit->poliklinik_id === $clinics->clinicId($request->user()), 403, 'Kunjungan bukan tanggung jawab poli Anda.');
    }
}
