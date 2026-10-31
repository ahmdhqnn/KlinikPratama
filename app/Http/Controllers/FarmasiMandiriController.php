<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Exports\FarmasiMandiriExport;
use App\Models\Farmasi;
use App\Models\FarmasiMandiri;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\ResepObat;
use App\PersediaanRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class FarmasiMandiriController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $records = $this->reportQuery($filters)->with(['pasien', 'obat', 'petugas'])
            ->latest('diserahkan_pada')->paginate(10)->withQueryString();
        $todayRecords = FarmasiMandiri::query()->whereDate('diserahkan_pada', today())
            ->with(['pasien', 'obat', 'petugas'])->latest('diserahkan_pada')
            ->paginate(10, ['*'], 'today_page')->withQueryString();
        $mapRecord = fn (FarmasiMandiri $record): array => [
            'number' => $record->nomor,
            'date' => $record->diserahkan_pada->format('d/m/Y H:i'),
            'patient' => $record->pasien->nama,
            'medicalRecordNumber' => $record->pasien->no_rm,
            'medicine' => $record->obat->nama,
            'quantity' => $record->jumlah,
            'unit' => $record->obat->satuan_kecil,
            'pharmacist' => $record->petugas->name,
            'printUrl' => route('farmasi.pemberian-obat.cetak', $record),
        ];
        $page = fn ($paginator): array => [
            'data' => $paginator->getCollection()->map($mapRecord)->values(),
            'currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            'previousUrl' => $paginator->previousPageUrl(), 'nextUrl' => $paginator->nextPageUrl(),
        ];

        return Inertia::render('farmasi/pemberian-obat/index', [
            'stats' => [
                'today' => FarmasiMandiri::query()->whereDate('diserahkan_pada', today())->count(),
                'patients' => FarmasiMandiri::query()->whereDate('diserahkan_pada', today())->distinct('pasien_id')->count('pasien_id'),
                'all' => FarmasiMandiri::query()->count(),
            ],
            'search' => $filters['search'] ?? '',
            'todayRecords' => $page($todayRecords),
            'records' => $page($records),
        ]);
    }

    public function create(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $patients = Pasien::query()
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")))
            ->when($search, fn (Builder $query) => $query->orderBy('nama'), fn (Builder $query) => $query->latest('created_at')->latest('id'))
            ->limit(10)->get(['id', 'nama', 'no_rm', 'riwayat_alergi']);
        $approvedMedicines = Obat::query()->where('is_active', true)->where('jenis', 'obat')->where('boleh_diberikan_langsung', true);
        $medicines = (clone $approvedMedicines)
            ->withSum(['batches as usable_stock' => fn (Builder $query) => $query->usable()], 'stok')
            ->orderBy('nama')->get();

        return Inertia::render('farmasi/pemberian-obat/form', [
            'search' => $search,
            'patients' => $patients->map(fn (Pasien $patient): array => ['id' => $patient->id, 'name' => $patient->nama, 'medicalRecordNumber' => $patient->no_rm, 'allergies' => $patient->riwayat_alergi]),
            'medicines' => $medicines->map(fn (Obat $medicine): array => ['id' => $medicine->id, 'name' => $medicine->nama, 'unit' => $medicine->satuan_kecil, 'stock' => (float) $medicine->usable_stock]),
        ]);
    }

    public function store(Request $request, PersediaanRecorder $inventory, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'pasien_id' => ['required', 'integer', 'exists:pasien,id'], 'obat_id' => ['required', 'integer', 'exists:obat,id'],
            'jumlah' => ['required', 'integer', 'min:1'], 'aturan_pakai' => ['required', 'string', 'max:255'],
            'dasar_pemberian' => ['required', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $record = DB::transaction(function () use ($data, $request, $inventory, $audit): FarmasiMandiri {
            $patient = Pasien::query()->whereKey($data['pasien_id'])->lockForUpdate()->firstOrFail();
            $medicine = Obat::query()->whereKey($data['obat_id'])->where('is_active', true)->where('jenis', 'obat')
                ->where('boleh_diberikan_langsung', true)->first();
            if (! $medicine) {
                throw ValidationException::withMessages(['obat_id' => 'Obat belum disetujui untuk diberikan tanpa pemeriksaan.']);
            }
            $record = FarmasiMandiri::create([
                ...$data, 'nomor' => 'FMD-'.today()->format('Ymd').'-'.Str::ulid(),
                'petugas_id' => $request->user()->id, 'diserahkan_pada' => now(),
            ]);
            $inventory->issueWithoutVisit($medicine->id, (int) $data['jumlah'], $request->user(), $patient, 'FarmasiMandiri', $record->id);
            $audit->record($request, 'pharmacy.direct_dispensed', $patient->id, metadata: ['record_id' => $record->id, 'medicine_id' => $medicine->id]);

            return $record;
        });

        return redirect()->route('farmasi.pemberian-obat.cetak', $record)->with('success', 'Pemberian obat berhasil dicatat dan stok telah diperbarui.');
    }

    public function print(FarmasiMandiri $farmasiMandiri): Response
    {
        $farmasiMandiri->load(['pasien', 'obat', 'petugas']);

        return Inertia::render('farmasi/pemberian-obat/cetak', ['record' => [
            'number' => $farmasiMandiri->nomor, 'date' => $farmasiMandiri->diserahkan_pada->format('d/m/Y H:i'),
            'patient' => $farmasiMandiri->pasien->nama, 'medicalRecordNumber' => $farmasiMandiri->pasien->no_rm,
            'medicine' => $farmasiMandiri->obat->nama, 'quantity' => $farmasiMandiri->jumlah, 'unit' => $farmasiMandiri->obat->satuan_kecil,
            'instruction' => $farmasiMandiri->aturan_pakai, 'basis' => $farmasiMandiri->dasar_pemberian, 'notes' => $farmasiMandiri->catatan, 'pharmacist' => $farmasiMandiri->petugas->name,
        ], 'appName' => config('app.name')]);
    }

    public function patients(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $visits = Kunjungan::query()->whereHas('farmasi', fn (Builder $query) => $query->where('status', 'selesai'))
            ->with(['pasien', 'poliklinik', 'farmasi'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('pasien', fn (Builder $patients) => $patients
                ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();
        $directRecords = FarmasiMandiri::query()->with(['pasien', 'obat'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('pasien', fn (Builder $patients) => $patients
                ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))
            ->latest('diserahkan_pada')->limit(10)->get();

        return Inertia::render('farmasi/pasien', [
            'search' => $filters['search'] ?? '',
            'stats' => [
                'prescriptionsToday' => Farmasi::query()->where('status', 'selesai')->whereDate('dispensed_at', today())->count(),
                'directToday' => FarmasiMandiri::query()->whereDate('diserahkan_pada', today())->count(),
                'allPrescriptions' => Farmasi::query()->where('status', 'selesai')->count(),
            ],
            'directRecords' => $directRecords->map(fn (FarmasiMandiri $record): array => [
                'number' => $record->nomor, 'date' => $record->diserahkan_pada->format('d/m/Y H:i'),
                'patient' => $record->pasien->nama, 'medicalRecordNumber' => $record->pasien->no_rm,
                'medicine' => $record->obat->nama, 'quantity' => $record->jumlah, 'unit' => $record->obat->satuan_kecil,
            ])->values(),
            'visits' => [
                'data' => $visits->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id, 'date' => $visit->tanggal?->format('d/m/Y'), 'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? 'Belum diisi', 'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi', 'dispensedAt' => $visit->farmasi?->dispensed_at?->format('d/m/Y H:i'),
                    'detailUrl' => route('farmasi.pasien.kunjungan', $visit),
                ])->values(),
                'currentPage' => $visits->currentPage(), 'lastPage' => $visits->lastPage(), 'from' => $visits->firstItem(), 'to' => $visits->lastItem(),
                'total' => $visits->total(), 'previousUrl' => $visits->previousPageUrl(), 'nextUrl' => $visits->nextPageUrl(),
            ],
        ]);
    }

    public function patientVisit(Request $request, Kunjungan $kunjungan): Response
    {
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'resep.resepObat.farmasiResepLuar',
            'farmasi.items', 'farmasi.dispensingPharmacist',
        ]);
        abort_unless($kunjungan->farmasi?->status === 'selesai', 404);

        return Inertia::render('farmasi/pasien-kunjungan', [
            'backUrl' => match ($request->query('asal')) {
                'laporan' => route('farmasi.laporan'),
                'penyerahan' => route('pelayanan.farmasi.index'),
                default => route('farmasi.pasien'),
            },
            'backLabel' => match ($request->query('asal')) {
                'laporan' => 'Kembali ke laporan obat',
                'penyerahan' => 'Kembali ke penyerahan obat',
                default => 'Kembali ke pasien',
            },
            'visit' => [
                'number' => $kunjungan->no_kunjungan,
                'date' => $kunjungan->tanggal?->format('d/m/Y'),
                'patient' => $kunjungan->pasien?->nama,
                'medicalRecordNumber' => $kunjungan->pasien?->no_rm,
                'clinic' => $kunjungan->poliklinik?->nama,
                'doctor' => $kunjungan->dokter?->nama,
                'prescriptionNumber' => $kunjungan->resep?->no_resep,
                'completedAt' => $kunjungan->farmasi->dispensed_at?->format('d/m/Y H:i'),
                'pharmacist' => $kunjungan->farmasi->dispensingPharmacist?->name,
                'notes' => $kunjungan->farmasi->catatan,
                'externalPrintUrl' => route('farmasi.resep-luar.cetak', $kunjungan).'?asal=pasien',
                'items' => $kunjungan->resep?->resepObat->map(function (ResepObat $item) use ($kunjungan): array {
                    $dispensed = $kunjungan->farmasi->items->firstWhere('resep_obat_id', $item->id);

                    return [
                        'name' => $item->nama_obat,
                        'requested' => (float) $item->jumlah,
                        'dispensed' => (float) ($dispensed?->jumlah_diberikan ?? 0),
                        'unit' => $item->satuan,
                        'instructions' => $dispensed?->aturan_pakai ?? $item->aturan_pakai,
                        'external' => $item->is_resep_luar || $item->farmasiResepLuar !== null,
                    ];
                })->values() ?? [],
            ],
        ]);
    }

    public function report(Request $request): Response
    {
        $filters = $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari'], 'search' => ['nullable', 'string', 'max:100']]);
        $from = $filters['dari'] ?? today()->startOfMonth()->toDateString();
        $to = $filters['sampai'] ?? today()->toDateString();
        $filtered = [...$filters, 'dari' => $filters['dari'] ?? $from, 'sampai' => $filters['sampai'] ?? $to];
        $records = $this->reportQuery($filtered)->with(['pasien', 'obat', 'petugas'])->latest('diserahkan_pada')->paginate(10)->withQueryString();
        $stats = $this->reportQuery($filtered);

        return Inertia::render('farmasi/laporan', [
            'filters' => ['dari' => $filters['dari'] ?? '', 'sampai' => $filters['sampai'] ?? '', 'search' => $filters['search'] ?? ''],
            'stats' => ['total' => (clone $stats)->count(), 'patients' => (clone $stats)->distinct('pasien_id')->count('pasien_id'), 'items' => (clone $stats)->distinct('obat_id')->count('obat_id')],
            'rows' => [
                'data' => $records->getCollection()->map(fn (FarmasiMandiri $record): array => [
                    'date' => $record->diserahkan_pada->format('d/m/Y H:i'), 'number' => $record->nomor,
                    'patient' => $record->pasien->nama, 'medicalRecordNumber' => $record->pasien->no_rm,
                    'medicine' => $record->obat->nama, 'quantity' => $record->jumlah, 'unit' => $record->obat->satuan_kecil,
                    'instruction' => $record->aturan_pakai, 'pharmacist' => $record->petugas->name,
                    'printUrl' => route('farmasi.pemberian-obat.cetak', $record),
                ])->values(),
                'currentPage' => $records->currentPage(), 'lastPage' => $records->lastPage(), 'from' => $records->firstItem(), 'to' => $records->lastItem(),
                'total' => $records->total(), 'previousUrl' => $records->previousPageUrl(), 'nextUrl' => $records->nextPageUrl(),
            ],
            'exportUrl' => route('farmasi.laporan.pemberian-langsung.export', $request->query()),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari'], 'search' => ['nullable', 'string', 'max:100']]);
        $filters['dari'] ??= today()->startOfMonth()->toDateString();
        $filters['sampai'] ??= today()->toDateString();

        return Excel::download(new FarmasiMandiriExport($filters), 'laporan-pemberian-obat-'.today()->format('Y-m-d').'.xlsx');
    }

    private function reportQuery(array $filters): Builder
    {
        return FarmasiMandiri::query()
            ->when($filters['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '>=', $date))
            ->when($filters['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))
                ->orWhereHas('obat', fn (Builder $medicines) => $medicines->where('nama', 'like', "%{$search}%"))));
    }
}
