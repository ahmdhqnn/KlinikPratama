<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\DateRangeFilter;
use App\Exports\NursingProcedureExport;
use App\GeneralConsentService;
use App\Http\Controllers\Controller;
use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\TindakanKunjungan;
use App\NursingProcedureReportQuery;
use App\PerawatClinicResolver;
use App\ProcedureConsentService;
use App\VisitCareWorkflow;
use App\VisitSchedulingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TindakanPerawatController extends Controller
{
    public function report(Request $request, PerawatClinicResolver $clinics, ClinicalAuditRecorder $audit): Response
    {
        $filters = $this->reportFilters($request);
        $isAdmin = $request->user()->isAdmin();
        $nurse = $isAdmin ? null : $clinics->nakes($request->user());
        $clinicId = $nurse?->poliklinik_id;
        $query = (new NursingProcedureReportQuery)->query($filters, $clinicId, $nurse?->user_id);
        $audit->record($request, 'nursing_procedure.report_view', metadata: ['clinic_id' => $clinicId, 'filters' => $filters]);
        $procedures = (clone $query)->paginate(15)->withQueryString();
        $baseUrl = route($isAdmin ? 'admin.laporan.tindakan-perawat' : 'perawat.laporan-tindakan');

        return Inertia::render('pelayanan/tindakan/report', [
            'title' => 'Laporan tindakan perawat',
            'personal' => ! $isAdmin,
            'clinicName' => $nurse?->poliklinik?->nama ?? 'Semua poli aktif',
            'baseUrl' => $baseUrl,
            'filters' => $filters,
            'stats' => $this->reportStats(clone $query),
            'procedures' => $this->pageData($procedures->through(fn (TindakanKunjungan $procedure): array => $this->mapReportProcedure($procedure))),
            'exportUrl' => route($isAdmin ? 'admin.laporan.tindakan-perawat.export' : 'perawat.laporan-tindakan.export', $filters),
            'printUrl' => route($isAdmin ? 'admin.laporan.tindakan-perawat.cetak' : 'perawat.laporan-tindakan.cetak', $filters),
            'printMode' => false,
        ]);
    }

    public function printReport(Request $request, PerawatClinicResolver $clinics, ClinicalAuditRecorder $audit): Response
    {
        $filters = $this->reportFilters($request);
        $isAdmin = $request->user()->isAdmin();
        $nurse = $isAdmin ? null : $clinics->nakes($request->user());
        $clinicId = $nurse?->poliklinik_id;
        $query = (new NursingProcedureReportQuery)->query($filters, $clinicId, $nurse?->user_id);
        $audit->record($request, 'nursing_procedure.report_print', metadata: ['clinic_id' => $clinicId, 'filters' => $filters]);
        $procedures = (clone $query)->limit(1000)->get()->map(fn (TindakanKunjungan $procedure): array => $this->mapReportProcedure($procedure))->all();
        $baseUrl = route($isAdmin ? 'admin.laporan.tindakan-perawat' : 'perawat.laporan-tindakan');

        return Inertia::render('pelayanan/tindakan/report', [
            'title' => 'Laporan tindakan perawat',
            'personal' => ! $isAdmin,
            'clinicName' => $nurse?->poliklinik?->nama ?? 'Semua poli aktif',
            'baseUrl' => $baseUrl,
            'filters' => $filters,
            'stats' => $this->reportStats((new NursingProcedureReportQuery)->query($filters, $clinicId, $nurse?->user_id)),
            'procedures' => ['data' => $procedures, 'total' => count($procedures)],
            'exportUrl' => null,
            'printUrl' => null,
            'printMode' => true,
        ]);
    }

    public function exportReport(Request $request, PerawatClinicResolver $clinics, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $this->reportFilters($request);
        $clinicId = $request->user()->isAdmin() ? null : $clinics->clinicId($request->user());
        $audit->record($request, 'nursing_procedure.report_export', metadata: ['clinic_id' => $clinicId, 'filters' => $filters]);

        return Excel::download(new NursingProcedureExport($filters, $clinicId, $request->user()->isAdmin() ? null : $request->user()->id), 'laporan-tindakan-perawat-'.today()->format('Y-m-d').'.xlsx');
    }

    public function index(Request $request, PerawatClinicResolver $clinics): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:pending,completed'],
        ]);
        $clinicId = $request->user()->isAdmin() ? null : $clinics->clinicId($request->user());
        $nurseTreatments = fn ($query) => $query->where('execution_role', 'perawat');
        $pendingTreatments = fn ($query) => $query->where('execution_role', 'perawat')->where('execution_status', 'pending');
        $completedTreatments = fn ($query) => $query->where('execution_role', 'perawat')->where('execution_status', 'completed');
        $doctorInProgress = fn ($query) => $query->where('status', 'pemeriksaan')
            ->whereHas('pemeriksaan', fn ($examination) => $examination->whereNotNull('started_at')->whereNull('signed_at'));
        $allClinicVisits = Kunjungan::query()->when($clinicId !== null, fn ($query) => $query->where('poliklinik_id', $clinicId))
            ->whereHas('poliklinik', fn ($query) => $query->where('is_active', true)->whereIn('jenis', ['umum', 'gigi']));
        $clinicVisits = (clone $allClinicVisits)
            ->where(fn ($query) => $query->where($doctorInProgress)
                ->orWhere(fn ($ready) => $ready->whereHas('tindakanKunjungan', $nurseTreatments)
                    ->whereHas('pemeriksaan', fn ($examination) => $examination->whereNotNull('signed_at'))));
        $withDetails = fn ($query) => $query->with(['pasien', 'poliklinik', 'dokter'])
            ->withCount([
                'tindakanKunjungan as pending_count' => $pendingTreatments,
                'tindakanKunjungan as completed_count' => $completedTreatments,
            ]);
        $allVisits = $withDetails((clone $clinicVisits)
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($matches) => $matches
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn ($patients) => $patients->where(fn ($identity) => $identity
                    ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))))
            ->when(($filters['status'] ?? null) === 'pending', fn ($query) => $query->where(fn ($waiting) => $waiting->where($doctorInProgress)->orWhereHas('tindakanKunjungan', $pendingTreatments)))
            ->when(($filters['status'] ?? null) === 'completed', fn ($query) => $query->whereHas('tindakanKunjungan', $completedTreatments)->whereDoesntHave('tindakanKunjungan', $pendingTreatments)))
            ->orderByDesc('tanggal')->latest('created_at')->latest('id')->paginate(10)->withQueryString();
        $todayVisits = $withDetails((clone $clinicVisits)->whereDate('tanggal', today())
            ->where('status', 'tindakan')->whereHas('tindakanKunjungan', $pendingTreatments))
            ->orderBy('created_at')->orderBy('id')->paginate(10, ['*'], 'today_page')->withQueryString();
        $todaySummary = (clone $clinicVisits)->whereDate('tanggal', today());
        $mapVisit = fn (Kunjungan $visit): array => [
            'id' => $visit->id, 'number' => $visit->no_kunjungan, 'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi', 'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan', 'date' => $visit->tanggal->format('d/m/Y'),
            'status' => $visit->status, 'examinationStarted' => $visit->status === 'pemeriksaan',
            'treatmentStarted' => $visit->tindakan_started_at !== null,
            'pendingCount' => $visit->status === 'pemeriksaan' ? 0 : $visit->pending_count,
            'completedCount' => $visit->status === 'pemeriksaan' ? 0 : $visit->completed_count,
            'url' => $visit->status === 'pemeriksaan' ? null : route('pelayanan.tindakan.show', $visit),
        ];

        return Inertia::render('pelayanan/tindakan/index', [
            'stats' => [
                'todayTotal' => (clone $allClinicVisits)->whereDate('tanggal', today())->where('status', '!=', 'batal')->count(),
                'todayPending' => (clone $todaySummary)->where('status', 'tindakan')->whereHas('tindakanKunjungan', $pendingTreatments)->count(),
                'todayCompleted' => (clone $todaySummary)->whereHas('tindakanKunjungan', $completedTreatments)->whereDoesntHave('tindakanKunjungan', $pendingTreatments)->count(),
                'allPending' => (clone $clinicVisits)->where('status', 'tindakan')->whereHas('tindakanKunjungan', $pendingTreatments)->count(),
            ],
            'filters' => ['search' => $filters['search'] ?? '', 'tanggal' => $filters['tanggal'] ?? '', 'status' => $filters['status'] ?? ''],
            'todayVisits' => $this->pageData($todayVisits->through($mapVisit)),
            'visits' => $this->pageData($allVisits->through($mapVisit)),
        ]);
    }

    public function show(Request $request, Kunjungan $kunjungan, PerawatClinicResolver $clinics, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit, ProcedureConsentService $consent, GeneralConsentService $generalConsent): Response
    {
        $this->authorizeVisit($request, $kunjungan, $clinics);
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'screening', 'pemeriksaan.signedBy',
            'tindakanKunjungan.performedBy', 'tindakanKunjungan.tindakan', 'tindakanKunjungan.informedConsents.recordedBy',
        ]);
        abort_unless($kunjungan->tindakanKunjungan->contains('execution_role', 'perawat'), 404);
        abort_unless($kunjungan->pemeriksaan?->signed_at && $notes->verify($kunjungan->pemeriksaan), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
        $payload = $kunjungan->pemeriksaan->clinicalNoteVersions()->latest('version')->firstOrFail()->payload;
        $audit->record($request, 'nursing_procedure.view', $kunjungan->pasien_id, $kunjungan->id);
        $isNurse = $request->user()->role === 'perawat';
        $isEmergencyVisit = $kunjungan->screening?->prioritas_layanan === 'darurat';

        return Inertia::render('pelayanan/tindakan/show', [
            'visit' => [
                'id' => $kunjungan->id, 'number' => $kunjungan->no_kunjungan, 'patient' => $kunjungan->pasien->nama,
                'medicalRecordNumber' => $kunjungan->pasien->no_rm, 'clinic' => $kunjungan->poliklinik->nama,
                'doctor' => $kunjungan->dokter?->nama, 'status' => $kunjungan->status,
                'treatmentStartedAt' => $kunjungan->tindakan_started_at?->format('d/m/Y H:i'),
                'startUrl' => route('pelayanan.tindakan.start', $kunjungan),
                'allergies' => $payload['riwayat_alergi'] ?? $kunjungan->pasien->riwayat_alergi,
                'education' => $payload['edukasi'] ?? null, 'nonPharmacological' => $payload['non_farmakologis'] ?? null,
                'diagnoses' => collect($payload['diagnoses'] ?? [])->pluck('name')->all(),
                'hasMedication' => $kunjungan->resep?->resepObat()->where('is_resep_luar', false)->exists() ?? false,
                'recordUrl' => route('pelayanan.pasien.rekam-medis', $kunjungan->pasien_id),
                'adminAssistance' => $request->user()->isAdmin(),
                'isEmergencyVisit' => $isEmergencyVisit,
                'delegation' => [
                    'doctor' => $kunjungan->dokter?->nama ?? $kunjungan->pemeriksaan->signedBy?->name ?? 'Dokter penanggung jawab',
                    'signedAt' => $kunjungan->pemeriksaan->signed_at?->format('d/m/Y H:i'),
                    'verified' => true,
                ],
                'generalConsent' => $generalConsent->summary($kunjungan),
                'treatments' => $kunjungan->tindakanKunjungan->where('execution_role', 'perawat')->map(function (TindakanKunjungan $item) use ($kunjungan, $consent, $isNurse): array {
                    $item->setRelation('kunjungan', $kunjungan);

                    return [
                        'id' => $item->id, 'name' => $item->nama_tindakan ?? $item->nama_tindakan_manual ?? $item->tindakan?->nama,
                        'code' => $item->kode_icd9cm, 'quantity' => $item->jumlah, 'toothFdi' => $item->tooth_fdi,
                        'instructions' => $item->catatan, 'status' => $item->execution_status,
                        'result' => $item->execution_result, 'performedBy' => $item->performedBy?->name,
                        'performedAt' => $item->performed_at?->format('d/m/Y H:i'),
                        'consent' => $consent->summary($item),
                        'completeUrl' => route('pelayanan.tindakan.complete', [$kunjungan, $item]),
                        'refuseUrl' => $isNurse ? route('pelayanan.tindakan.refuse', [$kunjungan, $item]) : null,
                        'emergencyUrl' => $isNurse ? route('pelayanan.tindakan.emergency', [$kunjungan, $item]) : null,
                    ];
                })->values(),
            ],
        ]);
    }

    public function start(Request $request, Kunjungan $kunjungan, PerawatClinicResolver $clinics, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit, ProcedureConsentService $consent): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, $clinics);

        $data = $this->assistanceData($request);

        DB::transaction(function () use ($request, $kunjungan, $clinics, $notes, $audit, $consent, $data): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit, $clinics);
            abort_unless($visit->status === 'tindakan', 422, 'Kunjungan tidak berada pada tahap tindakan perawat.');
            $examination = $visit->pemeriksaan()->first();
            abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
            $pendingProcedures = $visit->tindakanKunjungan()->with(['tindakan', 'informedConsents'])->where('execution_role', 'perawat')->where('execution_status', 'pending')->orderBy('id')->lockForUpdate()->get();
            abort_unless($pendingProcedures->isNotEmpty(), 422, 'Tidak ada tindakan perawat yang menunggu pelaksanaan.');

            $performableCount = 0;
            $firstBlockedException = null;
            foreach ($pendingProcedures as $procedure) {
                $procedure->setRelation('kunjungan', $visit);
                try {
                    $consent->ensureCanPerform($procedure);
                    $performableCount++;
                } catch (ValidationException $exception) {
                    $latestDecision = $consent->latest($procedure)?->decision;
                    if (! in_array($latestDecision, ['refused', 'withdrawn'], true)) {
                        throw $exception;
                    }
                    $firstBlockedException ??= $exception;
                }
            }
            if ($performableCount === 0 && $firstBlockedException !== null) {
                throw $firstBlockedException;
            }

            if ($visit->tindakan_started_at === null) {
                $now = now();
                $visit->update(['tindakan_started_at' => $now]);
                $visit->tindakanKunjungan()
                    ->where('execution_role', 'perawat')
                    ->where('execution_status', 'pending')
                    ->whereNull('started_at')
                    ->update(['started_at' => $now]);
                $audit->record($request, 'nursing_procedure.started', $visit->pasien_id, $visit->id, $data ? ['assistance_reason' => $data['assistance_reason'], 'clinic_id' => $visit->poliklinik_id] : []);
            }
        });

        return redirect()->route('pelayanan.tindakan.show', $kunjungan)->with('success', 'Tindakan perawat dimulai.');
    }

    public function recordRefusal(Request $request, Kunjungan $kunjungan, TindakanKunjungan $tindakanKunjungan, PerawatClinicResolver $clinics, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit, ProcedureConsentService $consent): RedirectResponse
    {
        abort_unless($request->user()->role === 'perawat', 403);
        $this->authorizeVisit($request, $kunjungan, $clinics);
        abort_unless($tindakanKunjungan->kunjungan_id === $kunjungan->id, 404);

        foreach (['signer_name', 'representative_verification', 'reason'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['refused', 'withdrawn'])],
            'signer_name' => ['required', 'string', 'min:2', 'max:150'],
            'signer_relationship' => ['required', Rule::in(array_keys(ProcedureConsentService::RELATIONSHIP_LABELS))],
            'representative_verification' => [Rule::requiredIf($request->input('signer_relationship') !== 'patient'), 'nullable', 'string', 'min:10', 'max:1000'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'confirmed' => ['required', 'accepted'],
        ], [
            'reason.min' => 'Catat kronologi penolakan atau pencabutan persetujuan minimal 20 karakter.',
            'confirmed.accepted' => 'Konfirmasi bahwa penolakan/pencabutan disampaikan pasien atau wakil dan dikoordinasikan kepada dokter.',
        ]);

        DB::transaction(function () use ($request, $kunjungan, $tindakanKunjungan, $clinics, $notes, $audit, $consent, $data): void {
            $visit = Kunjungan::with(['dokter', 'poliklinik', 'pasien'])->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit, $clinics);
            abort_unless($visit->status === 'tindakan', 422, 'Kunjungan tidak berada pada tahap tindakan perawat.');
            $examination = $visit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
            $procedure = $visit->tindakanKunjungan()->with(['tindakan', 'informedConsents'])->whereKey($tindakanKunjungan->id)->lockForUpdate()->firstOrFail();
            $procedure->setRelation('kunjungan', $visit);
            abort_unless($procedure->execution_role === 'perawat', 422, 'Tindakan ini tidak ditugaskan kepada perawat.');
            abort_if($procedure->execution_status === 'completed' || $procedure->performed_at !== null, 409, 'Tindakan sudah dilaksanakan.');
            if ($consent->hasPendingSignature($procedure)) {
                throw ValidationException::withMessages(['procedure_consent' => 'Selesaikan atau batalkan sesi QR yang masih terbuka sebelum mencatat penolakan.']);
            }

            $latest = $consent->latest($procedure);
            if ($data['decision'] === 'withdrawn' && ! in_array($latest?->decision, ['accepted', 'oral'], true)) {
                throw ValidationException::withMessages(['decision' => 'Tidak ada persetujuan aktif yang dapat dicabut; gunakan status penolakan tindakan.']);
            }

            $requestId = (string) Str::uuid();
            $planHash = $consent->planHash($procedure);
            $record = InformedConsent::create([
                'kunjungan_id' => $visit->id,
                'tindakan_id' => $procedure->tindakan_id,
                'tindakan_kunjungan_id' => $procedure->id,
                'supersedes_id' => $latest?->id,
                'request_id' => $requestId,
                'request_hash' => hash('sha256', json_encode([...$data, 'procedure_id' => $procedure->id, 'plan_hash' => $planHash], JSON_THROW_ON_ERROR)),
                'jenis' => $data['decision'],
                'decision' => $data['decision'],
                'text_version' => ProcedureConsentService::VERSION,
                'procedure_snapshot' => [
                    ...$consent->plan($procedure),
                    'recorded_by_role' => 'perawat',
                    'recorded_at_stage' => 'tindakan_perawat',
                    'doctor_delegator' => $visit->dokter?->nama,
                ],
                'plan_hash' => $planHash,
                'nama_penandatangan' => $data['signer_name'],
                'hubungan_pasien' => $data['signer_relationship'],
                'representative_verification' => $data['signer_relationship'] === 'patient' ? null : ($data['representative_verification'] ?? null),
                'witness_name' => $request->user()->name,
                'reason' => $data['reason'],
                'recorded_by' => $request->user()->id,
                'recorded_at' => now(),
                'tanggal' => today(),
            ]);

            $audit->record($request, $data['decision'] === 'withdrawn' ? 'nursing_procedure.consent_withdrawn' : 'nursing_procedure.consent_refused', $visit->pasien_id, $visit->id, [
                'procedure_id' => $procedure->id,
                'consent_id' => $record->id,
                'old_values' => ['decision' => $latest?->decision],
                'new_values' => ['decision' => $record->decision],
            ]);
        });

        return redirect()->route('pelayanan.tindakan.show', $kunjungan)
            ->with('warning', 'Penolakan / pencabutan persetujuan tercatat. Pelaksanaan tindakan dikunci dan wajib dikoordinasikan kepada dokter penanggung jawab.');
    }

    public function recordEmergency(Request $request, Kunjungan $kunjungan, TindakanKunjungan $tindakanKunjungan, PerawatClinicResolver $clinics, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit, ProcedureConsentService $consent): RedirectResponse
    {
        abort_unless($request->user()->role === 'perawat', 403);
        $this->authorizeVisit($request, $kunjungan, $clinics);
        abort_unless($tindakanKunjungan->kunjungan_id === $kunjungan->id, 404);

        if (is_string($request->input('reason'))) {
            $request->merge(['reason' => trim($request->input('reason'))]);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'emergency_without_available_signer' => ['required', 'accepted'],
        ], [
            'reason.min' => 'Catat dasar gawat darurat dan rencana kelengkapan penjelasan menyusul minimal 20 karakter.',
            'emergency_without_available_signer.accepted' => 'Konfirmasi kondisi gawat darurat dan ketiadaan pihak penanda tangan sebelum melanjutkan.',
        ]);

        DB::transaction(function () use ($request, $kunjungan, $tindakanKunjungan, $clinics, $notes, $audit, $consent, $data): void {
            $visit = Kunjungan::with(['screening', 'dokter', 'poliklinik', 'pasien'])->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit, $clinics);
            abort_unless($visit->status === 'tindakan', 422, 'Kunjungan tidak berada pada tahap tindakan perawat.');
            if ($visit->screening?->prioritas_layanan !== 'darurat') {
                throw ValidationException::withMessages(['procedure_consent' => 'Mode Darurat / Penjelasan Menyusul hanya tersedia untuk kunjungan dengan triase darurat terdokumentasi.']);
            }
            $examination = $visit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
            $procedure = $visit->tindakanKunjungan()->with(['tindakan', 'informedConsents'])->whereKey($tindakanKunjungan->id)->lockForUpdate()->firstOrFail();
            $procedure->setRelation('kunjungan', $visit);
            abort_unless($procedure->execution_role === 'perawat', 422, 'Tindakan ini tidak ditugaskan kepada perawat.');
            abort_if($procedure->execution_status === 'completed' || $procedure->performed_at !== null, 409, 'Tindakan sudah dilaksanakan.');
            if ($consent->hasPendingSignature($procedure)) {
                throw ValidationException::withMessages(['procedure_consent' => 'Selesaikan atau batalkan sesi QR yang masih terbuka sebelum mengaktifkan mode darurat.']);
            }

            $latest = $consent->latest($procedure);
            if (in_array($latest?->decision, ['refused', 'withdrawn'], true)) {
                throw ValidationException::withMessages(['procedure_consent' => 'Penolakan atau pencabutan persetujuan yang tercatat tidak dapat dilewati dengan mode darurat.']);
            }

            $requestId = (string) Str::uuid();
            $planHash = $consent->planHash($procedure);
            $record = InformedConsent::create([
                'kunjungan_id' => $visit->id,
                'tindakan_id' => $procedure->tindakan_id,
                'tindakan_kunjungan_id' => $procedure->id,
                'supersedes_id' => $latest?->id,
                'request_id' => $requestId,
                'request_hash' => hash('sha256', json_encode([...$data, 'procedure_id' => $procedure->id, 'plan_hash' => $planHash], JSON_THROW_ON_ERROR)),
                'jenis' => 'emergency',
                'decision' => 'emergency',
                'text_version' => ProcedureConsentService::VERSION,
                'procedure_snapshot' => [
                    ...$consent->plan($procedure),
                    'emergency_without_available_signer' => true,
                    'followup_documentation_required' => true,
                    'recorded_by_role' => 'perawat',
                    'recorded_at_stage' => 'tindakan_perawat',
                    'doctor_delegator' => $visit->dokter?->nama,
                ],
                'plan_hash' => $planHash,
                'reason' => $data['reason'],
                'recorded_by' => $request->user()->id,
                'recorded_at' => now(),
                'tanggal' => today(),
            ]);

            $audit->record($request, 'nursing_procedure.emergency_override', $visit->pasien_id, $visit->id, [
                'procedure_id' => $procedure->id,
                'consent_id' => $record->id,
                'old_values' => ['decision' => $latest?->decision],
                'new_values' => ['decision' => 'emergency', 'followup_documentation_required' => true],
            ]);
        });

        return redirect()->route('pelayanan.tindakan.show', $kunjungan)
            ->with('warning', 'Mode Darurat / Penjelasan Menyusul aktif. Laksanakan tindakan penyelamatan sesuai SPO dan lengkapi penjelasan serta dokumentasi lanjutan setelah kondisi stabil.');
    }

    public function complete(Request $request, Kunjungan $kunjungan, TindakanKunjungan $tindakanKunjungan, PerawatClinicResolver $clinics, VisitCareWorkflow $workflow, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit, ProcedureConsentService $consent): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, $clinics);
        abort_unless($tindakanKunjungan->kunjungan_id === $kunjungan->id, 404);
        if (is_string($request->input('execution_result'))) {
            $request->merge(['execution_result' => trim($request->input('execution_result'))]);
        }
        $data = [...$this->assistanceData($request), ...$request->validate(['execution_result' => ['required', 'string', 'min:3', 'max:5000']])];

        DB::transaction(function () use ($request, $kunjungan, $tindakanKunjungan, $clinics, $workflow, $notes, $audit, $consent, $data): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($request, $visit, $clinics);
            $procedure = $visit->tindakanKunjungan()->whereKey($tindakanKunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($procedure->execution_role === 'perawat', 422, 'Tindakan ini tidak ditugaskan kepada perawat.');
            if ($procedure->execution_status === 'completed') {
                return;
            }
            abort_unless($visit->status === 'tindakan' && $visit->tindakan_started_at !== null && $procedure->execution_status === 'pending', 422, 'Mulai tindakan perawat terlebih dahulu.');
            $examination = $visit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Catatan dokter belum ditandatangani atau integritasnya tidak valid.');
            $consent->ensureCanPerform($procedure);
            $workflow->consumeSupplies($procedure, $visit, $request->user());
            $procedure->update([
                'execution_status' => 'completed', 'performed_by' => $request->user()->id,
                'performed_by_nakes_id' => $request->user()->nakes?->id,
                'started_at' => $procedure->started_at ?? $visit->tindakan_started_at ?? now(),
                'performed_at' => now(), 'execution_result' => trim($data['execution_result']),
            ]);
            $workflow->advance($visit);
            $audit->record($request, 'nursing_procedure.complete', $visit->pasien_id, $visit->id, ['procedure_id' => $procedure->id, 'next_status' => $visit->status, ...($request->user()->isAdmin() ? ['assistance_reason' => $data['assistance_reason'], 'clinic_id' => $visit->poliklinik_id] : [])]);
        });

        return redirect()->route('pelayanan.tindakan.show', $kunjungan)->with('success', 'Pelaksanaan tindakan tersimpan. Status kunjungan telah diperbarui.');
    }

    /** @return array{assistance_reason?: string} */
    private function assistanceData(Request $request): array
    {
        if (! $request->user()->isAdmin()) {
            return [];
        }
        if (is_string($request->input('assistance_reason'))) {
            $request->merge(['assistance_reason' => trim($request->input('assistance_reason'))]);
        }

        return $request->validate(['assistance_reason' => ['required', 'string', 'min:10', 'max:1000']]);
    }

    private function authorizeVisit(Request $request, Kunjungan $visit, PerawatClinicResolver $clinics): void
    {
        if ($request->user()->isAdmin()) {
            abort_unless($visit->poliklinik?->is_active && in_array($visit->poliklinik->jenis, ['umum', 'gigi'], true), 403);
            if (! $request->isMethod('GET')) {
                app(VisitSchedulingService::class)->ensureServiceDate($visit);
            }

            return;
        }
        abort_unless($visit->poliklinik_id === $clinics->clinicId($request->user()), 403, 'Kunjungan bukan tanggung jawab poli Anda.');
    }

    /**
     * @return array{dari: ?string, sampai: ?string, search: ?string, status: ?string}
     */
    private function reportFilters(Request $request): array
    {
        $period = DateRangeFilter::fromRequest($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,completed'],
        ]);

        return [
            'dari' => $period->from,
            'sampai' => $period->to,
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
        ];
    }

    /** @return array{total: int, visits: int, pending: int, completed: int} */
    private function reportStats(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'visits' => (clone $query)->distinct('kunjungan_id')->count('kunjungan_id'),
            'pending' => (clone $query)->where('execution_status', 'pending')->count(),
            'completed' => (clone $query)->where('execution_status', 'completed')->count(),
        ];
    }

    /** @return array<string, int|string|null> */
    private function mapReportProcedure(TindakanKunjungan $procedure): array
    {
        $visit = $procedure->kunjungan;

        return [
            'id' => $procedure->id,
            'date' => $visit?->tanggal?->format('d/m/Y') ?? 'Belum diisi',
            'visitNumber' => $visit?->no_kunjungan ?? 'Belum diisi',
            'patient' => $visit?->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit?->pasien?->no_rm ?? 'Belum diisi',
            'clinic' => $visit?->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit?->dokter?->nama ?? 'Belum dicatat',
            'name' => $procedure->nama_tindakan ?? $procedure->nama_tindakan_manual ?? $procedure->tindakan?->nama ?? 'Tindakan',
            'code' => $procedure->kode_icd9cm,
            'quantity' => $procedure->jumlah,
            'tooth' => $procedure->tooth_fdi,
            'instructions' => $procedure->catatan,
            'status' => $procedure->execution_status,
            'result' => $procedure->execution_result,
            'performedBy' => $procedure->performedBy?->name,
            'performedAt' => $procedure->performed_at?->format('d/m/Y H:i'),
            'visitUrl' => $visit ? route('pelayanan.tindakan.show', $visit) : null,
        ];
    }
}
