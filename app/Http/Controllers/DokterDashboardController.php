<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\DateRangeFilter;
use App\DoctorAccessService;
use App\DoctorActivityReportQuery;
use App\DoctorExaminationReport;
use App\Exports\DoctorActivityExport;
use App\Exports\PemeriksaanExport;
use App\JadwalPraktikResolver;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\VisitOverviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
            ->whereIn('status', ['tindakan', 'farmasi', 'kasir', 'selesai'])->count();
        $jadwalHariIni = $this->schedule->doctorSchedule($dokterId, $today);
        $totalKunjungan = $this->doctorVisits()->count();

        $kunjunganTerkini = (clone $base)->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan.clinicalNoteVersions'])
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
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'actionUrl' => $visit->status === 'pemeriksaan'
                        ? route('pelayanan.pemeriksaan.show', $visit)
                        : route('pelayanan.pasien.rekam-medis', $visit->pasien),
                    'recordUrl' => route('pelayanan.pasien.rekam-medis', $visit->pasien),
                    'actionLabel' => $visit->status === 'pemeriksaan'
                        ? ($visit->screening?->submitted_at === null ? 'Menunggu pengkajian perawat' : ($visit->pemeriksaan ? 'Lanjutkan pemeriksaan' : 'Mulai pemeriksaan'))
                        : 'Lihat RME',
                    'actionType' => $visit->status === 'pemeriksaan' ? 'examination' : 'detail',
                    'actionDisabled' => $visit->status === 'pemeriksaan' && ! $visit->canOpenExamination(),
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

    public function appointments(Request $request, ClinicalNoteRecorder $notes): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $isReport = $request->routeIs('dokter.laporan-pemeriksaan');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $base = $isReport ? (new DoctorExaminationReport)->query($this->dokterId(), $period, $search) : $period->apply($this->doctorVisits());
        $todayVisits = $this->doctorVisits()->whereDate('tanggal', today());
        $kunjungan = (clone $base)
            ->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan.diagnosa', 'pemeriksaan.clinicalNoteVersions'])
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('dokter/appointments', [
            'isReport' => $isReport,
            'exportUrl' => $isReport ? route('dokter.laporan-pemeriksaan.export', ['dari' => $period->from, 'sampai' => $period->to, 'search' => $search]) : null,
            'stats' => $isReport ? [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->whereHas('pemeriksaan', fn ($query) => $query->whereNull('signed_at'))->count(),
                'final' => (clone $base)->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))->count(),
                'patients' => (clone $base)->distinct()->count('pasien_id'),
            ] : [
                'total' => $this->doctorVisits()->count(),
                'today' => (clone $todayVisits)->count(),
                'waiting' => (clone $todayVisits)->whereIn('status', ['menunggu', 'screening'])->count(),
                'completed' => (clone $todayVisits)->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))->count(),
            ],
            'filters' => [
                'search' => $search,
                'dari' => $period->from ?? '',
                'sampai' => $period->to ?? '',
            ],
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'date' => $visit->tanggal->format('d/m/Y'),
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien->nama,
                    'medicalRecordNumber' => $visit->pasien->no_rm,
                    'clinic' => $visit->poliklinik->nama,
                    'status' => $visit->status,
                    'diagnoses' => $visit->pemeriksaan?->diagnosa->map(fn ($diagnosis) => $diagnosis->kode_icd10.' - '.$diagnosis->nama_diagnosa)->implode('; ') ?? '',
                    'finalizedAt' => $visit->pemeriksaan?->signed_at?->format('d/m/Y H:i'),
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'examinationUrl' => route('pelayanan.pemeriksaan.show', $visit),
                    'canExamine' => $visit->canOpenExamination(),
                    'recordUrl' => route('pelayanan.pasien.rekam-medis', $visit->pasien_id),
                    'letterUrl' => $this->letterUrl($visit, $notes),
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
        $doctorId = $this->dokterId();
        $period = DateRangeFilter::fromRequest($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $audit->record($request, 'examination.report_export', metadata: ['doctor_id' => $doctorId, 'from' => $period->from, 'to' => $period->to]);

        return Excel::download(new PemeriksaanExport($doctorId, $period, $filters['search'] ?? ''), 'laporan-pemeriksaan-'.today()->format('Y-m-d').'.xlsx');
    }

    public function reports(Request $request, ClinicalNoteRecorder $notes): Response
    {
        $request->merge(['jenis' => $request->route('jenis')]);
        $filters = $request->validate([
            'jenis' => ['required', 'in:tindakan,obat'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $doctorId = $this->dokterId();
        $type = $filters['jenis'];
        $period = DateRangeFilter::fromRequest($request);
        $search = trim($filters['search'] ?? '');
        $reportQueries = new DoctorActivityReportQuery;
        $base = $type === 'tindakan'
            ? $reportQueries->treatments($doctorId, $period, $search)
            : $reportQueries->medicines($doctorId, $period, $search);
        $rows = $base->paginate(10)->withQueryString();
        $data = $rows->getCollection()->map(function ($item) use ($type, $notes): array {
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
                'detailUrl' => $visit ? route('pelayanan.pemeriksaan.show', $visit) : route('dokter.kunjungan'),
                'letterUrl' => $this->letterUrl($visit, $notes),
                'recordUrl' => $visit ? route('pelayanan.pasien.rekam-medis', $visit->pasien_id) : null,
            ];
        });

        return Inertia::render('dokter/reports', [
            'type' => $type, 'filters' => ['dari' => $period->from ?? '', 'sampai' => $period->to ?? '', 'search' => $search],
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
            'exportUrl' => route('dokter.laporan.export', ['jenis' => $type, 'dari' => $period->from, 'sampai' => $period->to, 'search' => $search]),
            'rows' => [...$this->pagination($rows), 'data' => $data->values()],
        ]);
    }

    public function exportActivityReport(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $request->merge(['jenis' => $request->route('jenis')]);
        $filters = $request->validate([
            'jenis' => ['required', 'in:tindakan,obat'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $doctorId = $this->dokterId();
        $period = DateRangeFilter::fromRequest($request);
        $search = trim($filters['search'] ?? '');
        $type = $filters['jenis'];
        $audit->record($request, 'doctor.'.$type.'.report_export', metadata: [
            'doctor_id' => $doctorId,
            'from' => $period->from,
            'to' => $period->to,
        ]);

        return Excel::download(
            new DoctorActivityExport($type, $doctorId, $period, $search),
            'laporan-'.$type.'-'.today()->format('Y-m-d').'.xlsx',
        );
    }

    public function patients(Request $request, ClinicalNoteRecorder $notes): Response
    {
        $pasien = Pasien::query()
            ->whereHas('kunjungan', fn (Builder $query) => $query->where('dokter_id', $this->dokterId()))
            ->withCount(['kunjungan as jumlah_kunjungan' => fn (Builder $query) => $query->where('dokter_id', $this->dokterId())])
            ->with(['kunjungan' => fn ($query) => $query->where('dokter_id', $this->dokterId())
                ->whereIn('status', ['tindakan', 'farmasi', 'kasir', 'selesai'])
                ->whereHas('pemeriksaan', fn (Builder $examinations) => $examinations->whereNotNull('signed_at'))
                ->with('pemeriksaan.clinicalNoteVersions')->latest('tanggal')->latest('id')->limit(1)])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $nested) use ($request): void {
                $search = $request->string('search')->toString();
                $nested->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            }))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dokter/patients', [
            'search' => $request->string('search')->toString(),
            'patients' => [
                'data' => $pasien->getCollection()->map(fn (Pasien $patient): array => [
                    'id' => $patient->id,
                    'medicalRecordNumber' => $patient->no_rm,
                    'name' => $patient->nama,
                    'gender' => $patient->jenis_kelamin,
                    'visitCount' => $patient->jumlah_kunjungan,
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

    public function stock(): Response
    {
        $obat = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')->where('is_active', true)->where('jenis', 'obat')
            ->orderBy('nama')->paginate(10);

        return Inertia::render('dokter/stock', [
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
        $dari = $period->from ?? '';
        $sampai = $period->to ?? '';
        $diagnosa = Diagnosa::query()
            ->select('kode_icd10', 'nama_diagnosa')
            ->selectRaw('COUNT(*) as jumlah')
            ->whereHas('pemeriksaan.kunjungan', fn (Builder $query) => $query
                ->where('dokter_id', $this->dokterId())
                ->when($dari, fn (Builder $visits) => $visits->whereDate('tanggal', '>=', $dari))
                ->when($sampai, fn (Builder $visits) => $visits->whereDate('tanggal', '<=', $sampai)))
            ->groupBy('kode_icd10', 'nama_diagnosa')
            ->orderByDesc('jumlah')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dokter/top-diagnoses', [
            'filters' => ['dari' => $dari, 'sampai' => $sampai],
            'diagnoses' => [
                'data' => $diagnosa->getCollection()->map(fn (Diagnosa $diagnosis): array => [
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
        if (! $visit || ! in_array($visit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true)
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
