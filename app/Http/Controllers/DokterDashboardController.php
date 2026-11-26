<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\DateRangeFilter;
use App\DoctorAccessService;
use App\DoctorActivityReportQuery;
use App\DoctorExaminationReport;
use App\EffectiveDiagnosisReport;
use App\Exports\DoctorActivityExport;
use App\Exports\PemeriksaanExport;
use App\JadwalPraktikResolver;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\VisitOverviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DokterDashboardController extends Controller
{
    public function __construct(private JadwalPraktikResolver $schedule, private DoctorAccessService $doctorAccess) {}

    public function index(ClinicalNoteRecorder $notes): Response
    {
        $dokterId = $this->dokterId();
        $today = today();
        $base = $this->doctorAccess->visibleVisits(auth()->user(), $today->toDateString());

        $kunjunganHariIni = (clone $base)->whereDate('tanggal', $today)->count();
        $menungguPemeriksaan = (clone $base)->whereDate('tanggal', $today)
            ->where('status', 'pemeriksaan')->count();
        $selesaiHariIni = (clone $base)->whereDate('tanggal', $today)
            ->whereIn('status', Kunjungan::POST_EXAMINATION_STATUSES)->count();
        $jadwalHariIni = $this->schedule->doctorSchedule($dokterId, $today);
        $totalKunjungan = $this->doctorVisits()->count();

        $kunjunganTerkini = (clone $base)->with([
            'pasien' => fn ($query) => $query->withExists(['kunjungan as doctor_can_access_record' => fn ($visits) => $visits->where('dokter_id', $dokterId)]),
            'poliklinik', 'screening', 'pemeriksaan.clinicalNoteVersions',
        ])
            ->whereDate('tanggal', $today)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(3)
            ->withQueryString();

        return Inertia::render('dokter/dashboard', [
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()->where('dokter_id', $dokterId)), 'href' => route('dokter.janji-kunjungan')],
            'stats' => [
                'visitsToday' => $kunjunganHariIni,
                'totalVisits' => $totalKunjungan,
                'waitingExaminations' => $menungguPemeriksaan,
                'completedToday' => $selesaiHariIni,
            ],
            'schedule' => $jadwalHariIni,
            'recentVisits' => [
                'data' => $kunjunganTerkini->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien->nama,
                    'medicalRecordNumber' => $visit->pasien->no_rm,
                    'clinic' => $visit->poliklinik->nama,
                    'status' => $visit->status,
                    'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                    'pendingReservation' => $visit->isPendingReservation(),
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'actionUrl' => route('pelayanan.pemeriksaan.show', $visit),
                    'recordUrl' => $visit->pasien->doctor_can_access_record ? route('pelayanan.pasien.rekam-medis', $visit->pasien) : null,
                    'actionLabel' => match (true) {
                        ! $visit->canOpenExamination() => 'Pemeriksaan dokter',
                        $visit->status !== 'pemeriksaan' => 'Tinjau pemeriksaan',
                        $visit->pemeriksaan?->started_at !== null => 'Lanjutkan pemeriksaan',
                        default => 'Mulai pemeriksaan',
                    },
                    'actionType' => 'examination',
                    'actionDisabled' => ! $visit->canOpenExamination(),
                    'actionDisabledReason' => match (true) {
                        $visit->status === 'batal' => 'Kunjungan telah dibatalkan.',
                        $visit->isPendingReservation() => 'Reservasi masih menunggu konfirmasi pendaftaran.',
                        default => 'Pemeriksaan dapat dimulai setelah pengkajian awal dikirim.',
                    },
                    'letterUrl' => $this->letterUrl($visit, $notes),
                    'showLetterAction' => true,
                ])->values(),
                'currentPage' => $kunjunganTerkini->currentPage(),
                'lastPage' => $kunjunganTerkini->lastPage(),
                'from' => $kunjunganTerkini->firstItem(),
                'to' => $kunjunganTerkini->lastItem(),
                'total' => $kunjunganTerkini->total(),
                'previousUrl' => $kunjunganTerkini->previousPageUrl(),
                'nextUrl' => $kunjunganTerkini->nextPageUrl(),
            ],
        ]);
    }

    public function appointments(Request $request, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $isAdmin = $request->user()->isAdmin();
        $isReport = $request->routeIs('dokter.laporan-pemeriksaan', 'admin.laporan.pemeriksaan');
        $reportRoute = $isAdmin ? 'admin.laporan.pemeriksaan' : 'dokter.laporan-pemeriksaan';
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => $isReport ? ['nullable', 'in:draf,final'] : ['nullable', 'in:'.implode(',', Kunjungan::STATUSES)],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? '';
        $clinicId = $isAdmin && isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null;
        $base = $isReport
            ? (new DoctorExaminationReport)->query($isAdmin ? null : $this->dokterId(), $period, $search, $status ?: null, $clinicId)
            : $period->apply($this->doctorVisits())
                ->when($period->to === null && $period->from === null,
                    fn (Builder $query) => $query->where('tanggal', '<', today()->addDays(8)->toDateString()))
                ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
                ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                    ->where('no_kunjungan', 'like', "%{$search}%")
                    ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))));
        $todayVisits = $isReport ? null : $this->doctorVisits()->whereDate('tanggal', today());
        if ($isAdmin) {
            $audit->record($request, 'admin.report.examination.view', metadata: ['from' => $period->from, 'to' => $period->to]);
        }
        $kunjungan = (clone $base)
            ->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan.diagnosa', 'pemeriksaan.clinicalNoteVersions'])
            ->when($isAdmin, fn (Builder $query) => $query->with('pemeriksaan.dokter'))
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('dokter/appointments', [
            'isReport' => $isReport,
            'isAdmin' => $isAdmin,
            'pageUrl' => route($isReport ? $reportRoute : 'dokter.kunjungan'),
            'exportUrl' => $isReport ? route($reportRoute.'.export', ['dari' => $period->from, 'sampai' => $period->to, 'search' => $search, 'status' => $status, 'poliklinik_id' => $clinicId]) : null,
            'clinics' => $isAdmin ? Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])->map(fn (Poliklinik $c): array => ['id' => $c->id, 'name' => $c->nama])->values() : [],
            'stats' => $isReport ? [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->whereHas('pemeriksaan', fn ($query) => $query->whereNull('signed_at'))->count(),
                'final' => (clone $base)->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))->count(),
                'patients' => (clone $base)->distinct()->count('pasien_id'),
            ] : [
                'total' => $this->doctorVisits()->count(),
                'today' => (clone $todayVisits)->count(),
                'waiting' => (clone $todayVisits)->whereIn('status', ['menunggu', 'screening'])->where('kontrol_pending', false)->count(),
                'completed' => (clone $todayVisits)->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))->count(),
            ],
            'filters' => [
                'search' => $search,
                'dari' => $period->from ?? '',
                'sampai' => $period->to ?? '',
                'status' => $status,
                'poliklinik_id' => $clinicId ? (string) $clinicId : '',
            ],
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'date' => $visit->tanggal->format('d/m/Y'),
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? 'Pasien tidak tersedia',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                    'clinic' => $visit->poliklinik?->nama ?? 'Poliklinik tidak tersedia',
                    'status' => $visit->status,
                    'doctor' => $isAdmin ? $visit->pemeriksaan?->dokter?->nama : null,
                    'diagnoses' => $visit->pemeriksaan?->diagnosa->map(fn ($diagnosis) => $diagnosis->kode_icd10.' - '.$diagnosis->nama_diagnosa)->implode('; ') ?? '',
                    'finalizedAt' => $visit->pemeriksaan?->signed_at?->format('d/m/Y H:i'),
                    'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                    'pendingReservation' => $visit->isPendingReservation(),
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'examinationUrl' => $visit->pasien && $visit->poliklinik ? route('pelayanan.pemeriksaan.show', $visit) : null,
                    'canExamine' => $visit->canOpenExamination(),
                    'recordUrl' => $visit->pasien ? route('pelayanan.pasien.rekam-medis', $visit->pasien_id) : null,
                    'letterUrl' => $isAdmin || ! $visit->pasien || ! $visit->poliklinik ? null : $this->letterUrl($visit, $notes),
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
        ]);
    }

    public function exportExaminations(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $isAdmin = $request->user()->isAdmin();
        $doctorId = $isAdmin ? null : $this->dokterId();
        $period = DateRangeFilter::fromRequest($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draf,final'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $clinicId = $isAdmin && isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null;
        $audit->record($request, 'examination.report_export', metadata: ['doctor_id' => $doctorId, 'from' => $period->from, 'to' => $period->to]);

        return Excel::download(
            new PemeriksaanExport($doctorId, $period, $filters['search'] ?? '', $filters['status'] ?? null, $clinicId),
            'laporan-pemeriksaan-'.today()->format('Y-m-d').'.xlsx'
        );
    }

    public function reports(Request $request, ClinicalNoteRecorder $notes, ClinicalAuditRecorder $audit): Response
    {
        $type = $request->route('jenis');
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => $type === 'tindakan' ? ['nullable', 'in:pending,completed'] : ['nullable', 'in:menunggu,diproses,selesai,batal'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $isAdmin = $request->user()->isAdmin();
        $doctorId = $isAdmin ? null : $this->dokterId();
        $period = DateRangeFilter::fromRequest($request);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? '';
        $clinicId = $isAdmin && isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null;
        if ($isAdmin) {
            $audit->record($request, 'admin.report.doctor.view', metadata: ['type' => $type, 'from' => $period->from, 'to' => $period->to]);
        }
        $reportQueries = new DoctorActivityReportQuery;
        $base = $type === 'tindakan'
            ? $reportQueries->treatments($doctorId, $period, $search, $status ?: null, $clinicId)
            : $reportQueries->medicines($doctorId, $period, $search, $status ?: null, $clinicId);
        $rows = $base->paginate(10)->withQueryString();
        $data = $rows->getCollection()->map(function ($item) use ($type, $notes, $isAdmin): array {
            $visit = $type === 'tindakan' ? $item->kunjungan : $item->resep?->kunjungan;
            $name = $type === 'tindakan'
                ? ($item->nama_tindakan ?: $item->nama_tindakan_manual ?: $item->tindakan?->nama ?: 'Tindakan tidak tersedia')
                : ($item->nama_obat ?: $item->obat?->nama ?: 'Obat tidak tersedia');

            return [
                'date' => $visit?->tanggal?->format('d/m/Y') ?? '—',
                'number' => $visit?->no_kunjungan ?? '—',
                'patient' => $visit?->pasien?->nama ?? 'Pasien tidak tersedia',
                'medicalRecordNumber' => $visit?->pasien?->no_rm ?? '—',
                'clinic' => $visit?->poliklinik?->nama ?? '—',
                'name' => $name,
                'quantity' => $item->jumlah,
                'instruction' => $type === 'obat' ? $item->aturan_pakai : null,
                'external' => $type === 'obat' ? $item->is_resep_luar : false,
                'statusLabel' => $type === 'tindakan'
                    ? ($item->execution_status === 'completed' ? 'Selesai' : ($item->execution_role === 'perawat' ? 'Menunggu tindakan perawat' : 'Belum difinalisasi'))
                    : match ($item->resep?->status) {
                        'selesai' => 'Disiapkan farmasi',
                        'diproses' => 'Sedang disiapkan',
                        'batal' => 'Resep dibatalkan',
                        default => 'Menunggu farmasi',
                    },
                'executionRole' => $type === 'tindakan' ? $item->execution_role : null,
                'detailUrl' => $visit ? route('pelayanan.pemeriksaan.show', $visit) : null,
                'letterUrl' => $isAdmin ? null : $this->letterUrl($visit, $notes),
                'recordUrl' => $visit ? route('pelayanan.pasien.rekam-medis', $visit->pasien_id) : null,
            ];
        });

        return Inertia::render('dokter/reports', [
            'type' => $type,
            'filters' => [
                'dari' => $period->from ?? '',
                'sampai' => $period->to ?? '',
                'search' => $search,
                'status' => $status,
                'poliklinik_id' => $clinicId ? (string) $clinicId : '',
            ],
            'clinics' => $isAdmin ? Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])->map(fn (Poliklinik $c): array => ['id' => $c->id, 'name' => $c->nama])->values() : [],
            'stats' => $type === 'tindakan' ? [
                'total' => $rows->total(),
                'secondary' => (clone $base)->where('execution_status', 'pending')->count(),
                'tertiary' => (clone $base)->where('execution_status', 'completed')->count(),
                'related' => (clone $base)->distinct()->count('kunjungan_id'),
            ] : [
                'total' => $rows->total(),
                'secondary' => (clone $base)->where('is_resep_luar', false)->count(),
                'tertiary' => (clone $base)->where('is_resep_luar', true)->count(),
                'related' => (clone $base)->distinct()->count('resep_id'),
            ],
            'exportUrl' => route($isAdmin ? 'admin.laporan.dokter.export' : 'dokter.laporan.export', ['jenis' => $type, 'dari' => $period->from, 'sampai' => $period->to, 'search' => $search, 'status' => $status, 'poliklinik_id' => $clinicId]),
            'baseUrl' => route($isAdmin ? 'admin.laporan.dokter' : 'dokter.laporan', ['jenis' => $type]),
            'isAdmin' => $isAdmin,
            'rows' => [...$this->pagination($rows), 'data' => $data->values()],
        ]);
    }

    public function exportActivityReport(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $type = $request->route('jenis');
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => $type === 'tindakan' ? ['nullable', 'in:pending,completed'] : ['nullable', 'in:menunggu,diproses,selesai,batal'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $isAdmin = $request->user()->isAdmin();
        $doctorId = $isAdmin ? null : $this->dokterId();
        $period = DateRangeFilter::fromRequest($request);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? null;
        $clinicId = $isAdmin && isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null;
        $audit->record($request, 'doctor.'.$type.'.report_export', metadata: [
            'doctor_id' => $doctorId,
            'from' => $period->from,
            'to' => $period->to,
        ]);

        return Excel::download(
            new DoctorActivityExport($type, $doctorId, $period, $search, $status, $clinicId),
            'laporan-'.$type.'-'.today()->format('Y-m-d').'.xlsx',
        );
    }

    public function patients(Request $request, ClinicalNoteRecorder $notes): Response
    {
        $gender = in_array($request->string('jenis_kelamin')->toString(), ['L', 'P'], true)
            ? $request->string('jenis_kelamin')->toString()
            : '';
        $basePatients = Pasien::query()
            ->whereHas('kunjungan', fn (Builder $query) => $query->where('dokter_id', $this->dokterId()));

        $pasien = (clone $basePatients)
            ->withCount(['kunjungan as jumlah_kunjungan' => fn (Builder $query) => $query->where('dokter_id', $this->dokterId())])
            ->with(['kunjungan' => fn ($query) => $query->where('dokter_id', $this->dokterId())
                ->whereIn('status', Kunjungan::POST_EXAMINATION_STATUSES)
                ->whereHas('pemeriksaan', fn (Builder $examinations) => $examinations->whereNotNull('signed_at'))
                ->with('pemeriksaan.clinicalNoteVersions')->latest('tanggal')->latest('id')->limit(1)])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $nested) use ($request): void {
                $search = $request->string('search')->toString();
                $nested->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            }))
            ->when($gender !== '', fn (Builder $query) => $query->where('jenis_kelamin', $gender))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dokter/patients', [
            'search' => $request->string('search')->toString(),
            'gender' => $gender,
            'stats' => [
                'totalPatients' => (clone $basePatients)->count(),
                'totalVisits' => Kunjungan::where('dokter_id', $this->dokterId())->count(),
                'male' => (clone $basePatients)->where('jenis_kelamin', 'L')->count(),
                'female' => (clone $basePatients)->where('jenis_kelamin', 'P')->count(),
            ],
            'patients' => [
                'data' => $pasien->getCollection()->map(fn (Pasien $patient): array => [
                    'id' => $patient->id,
                    'medicalRecordNumber' => $patient->no_rm,
                    'name' => $patient->nama,
                    'gender' => $patient->jenis_kelamin,
                    'visitCount' => $patient->jumlah_kunjungan,
                    'profileUrl' => route('pelayanan.pasien.show', $patient),
                    'recordUrl' => route('pelayanan.pasien.rekam-medis', $patient),
                    'letterUrl' => $patient->kunjungan->first() ? $this->letterUrl($patient->kunjungan->first(), $notes) : null,
                ])->values(),
                'currentPage' => $pasien->currentPage(),
                'lastPage' => $pasien->lastPage(),
                'from' => $pasien->firstItem(),
                'to' => $pasien->lastItem(),
                'total' => $pasien->total(),
                'previousUrl' => $pasien->previousPageUrl(),
                'nextUrl' => $pasien->nextPageUrl(),
            ],
        ]);
    }

    public function stock(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kondisi' => ['nullable', 'in:rendah,habis'],
        ]);
        $doctor = Nakes::with('poliklinik:id,depo_obat_id')->find($this->dokterId());
        $depoId = $doctor?->poliklinik?->depo_obat_id;
        $baseMedicines = Obat::query()->where('is_active', true)->where('jenis', 'obat');
        $outOfStockSql = 'COALESCE((SELECT SUM(stok) FROM obat_batch WHERE obat_batch.obat_id = obat.id AND status = \'aktif\' AND stok > 0 AND (expired_at IS NULL OR expired_at >= ?)'
            .($depoId !== null ? ' AND depo_id = '.(int) $depoId : '')
            .'), 0) <= 0';
        $totalMedicines = (clone $baseMedicines)->count();
        $lowStockCount = (clone $baseMedicines)->lowUsableStock($depoId)->count();
        $outOfStockCount = (clone $baseMedicines)->whereRaw($outOfStockSql, [today()->toDateString()])->count();

        $obat = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()->forDepo($depoId)], 'stok')
            ->where('is_active', true)
            ->where('jenis', 'obat')
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where(fn ($sub) => $sub
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")
                ->orWhere('kandungan_aktif', 'like', "%{$search}%")))
            ->when(($filters['kondisi'] ?? null) === 'rendah', fn ($q) => $q->lowUsableStock($depoId))
            ->when(($filters['kondisi'] ?? null) === 'habis', fn ($q) => $q->whereRaw($outOfStockSql, [today()->toDateString()]))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dokter/stock', [
            'stats' => [
                'total' => $totalMedicines,
                'available' => max(0, $totalMedicines - $outOfStockCount),
                'lowStock' => $lowStockCount,
                'outOfStock' => $outOfStockCount,
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'kondisi' => $filters['kondisi'] ?? '',
            ],
            'medicines' => [
                'data' => $obat->getCollection()->map(fn (Obat $medicine): array => [
                    'id' => $medicine->id,
                    'code' => $medicine->kode,
                    'name' => $medicine->nama,
                    'unit' => $medicine->satuan_kecil,
                    'stock' => (float) $medicine->usable_stock,
                    'minimumStock' => (float) $medicine->stok_minimum,
                    'isLow' => (float) $medicine->usable_stock <= (float) $medicine->stok_minimum,
                ])->values(),
                'currentPage' => $obat->currentPage(),
                'lastPage' => $obat->lastPage(),
                'from' => $obat->firstItem(),
                'to' => $obat->lastItem(),
                'total' => $obat->total(),
                'previousUrl' => $obat->previousPageUrl(),
                'nextUrl' => $obat->nextPageUrl(),
            ],
        ]);
    }

    public function topDiagnoses(Request $request): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $search = trim($request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');
        $dari = $period->from ?? '';
        $sampai = $period->to ?? '';
        $visits = DB::table('kunjungan')->where('dokter_id', $this->dokterId())
            ->when($dari, fn ($query) => $query->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($query) => $query->whereDate('tanggal', '<=', $sampai));
        $diagnosa = app(EffectiveDiagnosisReport::class)->query($visits)
            ->select('kode_icd10', 'nama_diagnosa')
            ->selectRaw('COUNT(*) as jumlah')
            ->join('pemeriksaan', 'pemeriksaan.id', '=', 'diagnosa.pemeriksaan_id')
            ->join('kunjungan', 'kunjungan.id', '=', 'pemeriksaan.kunjungan_id')
            ->where('kunjungan.dokter_id', $this->dokterId())
            ->when($dari, fn ($query) => $query->whereDate('kunjungan.tanggal', '>=', $dari))
            ->when($sampai, fn ($query) => $query->whereDate('kunjungan.tanggal', '<=', $sampai))
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested
                ->where('diagnosa.kode_icd10', 'like', "%{$search}%")
                ->orWhere('diagnosa.nama_diagnosa', 'like', "%{$search}%")))
            ->groupBy('kode_icd10', 'nama_diagnosa')
            ->orderByDesc('jumlah')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dokter/top-diagnoses', [
            'filters' => ['dari' => $dari, 'sampai' => $sampai, 'search' => $search],
            'diagnoses' => [
                'data' => $diagnosa->getCollection()->map(fn ($diagnosis): array => [
                    'code' => $diagnosis->kode_icd10,
                    'name' => $diagnosis->nama_diagnosa,
                    'count' => (int) $diagnosis->jumlah,
                ])->values(),
                'currentPage' => $diagnosa->currentPage(),
                'lastPage' => $diagnosa->lastPage(),
                'from' => $diagnosa->firstItem(),
                'to' => $diagnosa->lastItem(),
                'total' => $diagnosa->total(),
                'previousUrl' => $diagnosa->previousPageUrl(),
                'nextUrl' => $diagnosa->nextPageUrl(),
            ],
        ]);
    }

    private function doctorVisits(): Builder
    {
        return Kunjungan::query()->where('dokter_id', $this->dokterId());
    }

    private function dokterId(): int
    {
        return $this->doctorAccess->doctorId(auth()->user());
    }

    private function letterUrl(?Kunjungan $visit, ClinicalNoteRecorder $notes): ?string
    {
        $examination = $visit?->pemeriksaan;
        if (! $visit || ! in_array($visit->status, Kunjungan::POST_EXAMINATION_STATUSES, true)
            || ! $examination?->signed_at || ! $notes->verify($examination)) {
            return null;
        }

        return route('persuratan.surat.create', $visit);
    }

    /** @return array{currentPage: int, lastPage: int, from: int|null, to: int|null, total: int, previousUrl: string|null, nextUrl: string|null} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            'previousUrl' => $paginator->previousPageUrl(), 'nextUrl' => $paginator->nextPageUrl(),
        ];
    }
}
