<?php

namespace App\Http\Controllers;

use App\DateRangeFilter;
use App\DoctorAccessService;
use App\JadwalPraktikResolver;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\VisitOverviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokterDashboardController extends Controller
{
    public function __construct(private JadwalPraktikResolver $schedule, private DoctorAccessService $doctorAccess) {}

    public function index(): Response
    {
        $dokterId = $this->dokterId();
        $today = today();
        $base = $this->doctorAccess->visibleVisits(auth()->user(), $today->toDateString());

        $kunjunganHariIni = (clone $base)->whereDate('tanggal', $today)->count();
        $menungguPemeriksaan = (clone $base)->whereDate('tanggal', $today)
            ->where('status', 'pemeriksaan')->count();
        $selesaiHariIni = (clone $base)->whereDate('tanggal', $today)
            ->whereIn('status', ['farmasi', 'kasir', 'selesai'])->count();
        $jadwalHariIni = $this->schedule->doctorSchedule($dokterId, $today);

        $kunjunganTerkini = (clone $base)->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan'])
            ->whereDate('tanggal', $today)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dokter/dashboard', [
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()->where('dokter_id', $dokterId)), 'href' => route('dokter.janji-kunjungan')],
            'stats' => [
                'visitsToday' => $kunjunganHariIni,
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
                    'actionLabel' => $visit->status === 'pemeriksaan'
                        ? ($visit->pemeriksaan ? 'Lanjutkan pemeriksaan' : 'Mulai pemeriksaan')
                        : 'Lihat RME',
                    'actionType' => $visit->status === 'pemeriksaan' ? 'examination' : 'detail',
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

    public function appointments(Request $request): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $kunjungan = $period->apply($this->doctorVisits())
            ->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan'])
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('dokter/appointments', [
            'filters' => [
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
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'examinationUrl' => route('pelayanan.pemeriksaan.show', $visit),
                    'recordUrl' => route('pelayanan.pasien.rekam-medis', $visit->pasien_id),
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

    public function patients(Request $request): Response
    {
        $pasien = Pasien::query()
            ->whereHas('kunjungan', fn (Builder $query) => $query->where('dokter_id', $this->dokterId()))
            ->withCount(['kunjungan as jumlah_kunjungan' => fn (Builder $query) => $query->where('dokter_id', $this->dokterId())])
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
}
