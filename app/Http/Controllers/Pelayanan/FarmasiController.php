<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\Exports\FarmasiDispensingExport;
use App\Exports\FarmasiResepLuarExport;
use App\Http\Controllers\Controller;
use App\Models\Farmasi;
use App\Models\FarmasiItem;
use App\Models\FarmasiResepLuar;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ResepObat;
use App\Models\StokMutasi;
use App\PatientAllergyHistory;
use App\PersediaanRecorder;
use App\VisitQueryService;
use App\VisitSchedulingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class FarmasiController extends Controller
{
    public function __construct(private VisitSchedulingService $scheduling) {}

    public function index(Request $request, VisitQueryService $visits): Response
    {
        $filters = $visits->filters($request);
        $pharmacyVisits = fn (Builder $query): Builder => $query
            ->where(fn (Builder $visits) => $visits->where('status', 'farmasi')->orWhereHas('farmasi', fn (Builder $records) => $records->where('status', 'selesai')));
        $kunjungan = $pharmacyVisits($visits->filtered($filters))
            ->with(['pasien', 'poliklinik', 'dokter', 'farmasi', 'resep.resepObat'])
            ->latest('tanggal')->latest('id')
            ->paginate(10)
            ->withQueryString();
        $activeVisits = Kunjungan::query()->where('status', 'farmasi')
            ->whereDoesntHave('farmasi', fn (Builder $records) => $records->where('status', 'selesai'));
        $todayActive = (clone $activeVisits)->whereDate('tanggal', today());
        $todayVisits = (clone $todayActive)->with(['pasien', 'poliklinik', 'dokter', 'farmasi', 'resep.resepObat'])
            ->orderBy('created_at')->orderBy('id')->paginate(10, ['*'], 'today_page')->withQueryString();
        $mapVisit = fn (Kunjungan $visit): array => [
            'id' => $visit->id,
            'prescriptionNumber' => $visit->resep?->no_resep,
            'date' => $visit->tanggal?->format('d/m/Y'),
            'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
            'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama,
            'itemCount' => $visit->resep?->resepObat->count() ?? 0,
            'pharmacyStatus' => $visit->farmasi?->status ?? 'menunggu',
        ];

        return Inertia::render('pelayanan/farmasi/index', [
            'stats' => [
                'today' => $pharmacyVisits(Kunjungan::query()->whereDate('tanggal', today()))->count(),
                'waiting' => (clone $todayActive)->whereDoesntHave('farmasi', fn (Builder $records) => $records->where('status', 'diproses'))->count(),
                'processing' => (clone $todayActive)->whereHas('farmasi', fn (Builder $records) => $records->where('status', 'diproses'))->count(),
                'completed' => Farmasi::query()->where('status', 'selesai')->whereDate('dispensed_at', today())->count(),
            ],
            'todayVisits' => [
                'data' => $todayVisits->getCollection()->map($mapVisit)->values(),
                'currentPage' => $todayVisits->currentPage(), 'lastPage' => $todayVisits->lastPage(),
                'from' => $todayVisits->firstItem(), 'to' => $todayVisits->lastItem(), 'total' => $todayVisits->total(),
                'previousUrl' => $todayVisits->previousPageUrl(), 'nextUrl' => $todayVisits->nextPageUrl(),
            ],
            'visits' => [
                'data' => $kunjungan->getCollection()->map($mapVisit)->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'date' => $filters['tanggal'] ?? '',
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function externalPrescriptions(Request $request, VisitQueryService $visits): Response
    {
        $filters = $visits->filters($request);
        $query = fn (Builder $builder): Builder => $builder
            ->whereHas('pemeriksaan', fn (Builder $exam) => $exam->whereNotNull('signed_at'))
            ->whereHas('resep.resepObat', fn (Builder $items) => $items->where(fn (Builder $item) => $item
                ->where('is_resep_luar', true)->orWhereHas('farmasiResepLuar')));
        $records = $query($visits->filtered($filters))
            ->with(['pasien', 'poliklinik', 'dokter', 'resep.resepObat.farmasiResepLuar'])
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();
        $todayRecords = $query(Kunjungan::query()->whereDate('tanggal', today()))
            ->with(['pasien', 'poliklinik', 'dokter', 'resep.resepObat.farmasiResepLuar'])
            ->orderBy('created_at')->orderBy('id')->paginate(10, ['*'], 'today_page')->withQueryString();
        $mapRecord = fn (Kunjungan $visit): array => [
            'id' => $visit->id,
            'date' => $visit->tanggal?->format('d/m/Y') ?? 'Belum diisi',
            'number' => $visit->resep?->no_resep ?? 'Belum diisi',
            'visitNumber' => $visit->no_kunjungan,
            'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
            'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama ?? 'Belum diisi',
            'itemCount' => $visit->resep?->resepObat->filter(fn (ResepObat $item): bool => $item->is_resep_luar || $item->farmasiResepLuar !== null)->count() ?? 0,
            'printUrl' => route('farmasi.resep-luar.cetak', $visit),
        ];
        $page = fn ($paginator): array => [
            'data' => $paginator->getCollection()->map($mapRecord)->values(),
            'currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            'previousUrl' => $paginator->previousPageUrl(), 'nextUrl' => $paginator->nextPageUrl(),
        ];

        return Inertia::render('farmasi/resep-luar', [
            'stats' => [
                'today' => $query(Kunjungan::query()->whereDate('tanggal', today()))->count(),
                'all' => $query(Kunjungan::query())->count(),
            ],
            'filters' => ['search' => $filters['search'] ?? '', 'tanggal' => $filters['tanggal'] ?? ''],
            'todayRecords' => $page($todayRecords),
            'records' => $page($records),
        ]);
    }

    public function externalReport(Request $request): Response
    {
        $filters = $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari'], 'search' => ['nullable', 'string', 'max:100']]);
        $filtered = [...$filters, 'dari' => $filters['dari'] ?? today()->startOfMonth()->toDateString(), 'sampai' => $filters['sampai'] ?? today()->toDateString()];
        $query = $this->externalReportQuery($filtered);
        $rows = (clone $query)->with(['obat', 'resep.kunjungan.pasien', 'resep.kunjungan.poliklinik', 'farmasiResepLuar.petugas'])
            ->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('farmasi/laporan-resep-luar', [
            'filters' => ['dari' => $filtered['dari'], 'sampai' => $filtered['sampai'], 'search' => $filtered['search'] ?? ''],
            'stats' => [
                'items' => (clone $query)->count(),
                'prescriptions' => (clone $query)->distinct('resep_id')->count('resep_id'),
                'redirected' => (clone $query)->whereHas('farmasiResepLuar')->count(),
            ],
            'rows' => [
                'data' => $rows->getCollection()->map(fn (ResepObat $item): array => [
                    'id' => $item->id,
                    'date' => $item->resep?->kunjungan?->tanggal?->format('d/m/Y') ?? 'Belum diisi',
                    'prescription' => $item->resep?->no_resep ?? 'Belum diisi',
                    'patient' => $item->resep?->kunjungan?->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $item->resep?->kunjungan?->pasien?->no_rm ?? 'Belum diisi',
                    'clinic' => $item->resep?->kunjungan?->poliklinik?->nama ?? 'Belum diisi',
                    'medicine' => $item->nama_obat ?? $item->obat?->nama ?? 'Belum diisi',
                    'quantity' => (float) $item->jumlah, 'unit' => $item->satuan,
                    'source' => $item->farmasiResepLuar ? 'Dialihkan farmasi' : 'Diresepkan dokter',
                    'printUrl' => route('farmasi.resep-luar.cetak', $item->resep->kunjungan).'?asal=laporan',
                ])->values(),
                'currentPage' => $rows->currentPage(), 'lastPage' => $rows->lastPage(),
                'from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'total' => $rows->total(),
                'previousUrl' => $rows->previousPageUrl(), 'nextUrl' => $rows->nextPageUrl(),
            ],
            'exportUrl' => route('farmasi.laporan.resep-luar.export', $request->query()),
        ]);
    }

    public function exportExternalReport(Request $request)
    {
        $filters = $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari'], 'search' => ['nullable', 'string', 'max:100']]);
        $filters['dari'] ??= today()->startOfMonth()->toDateString();
        $filters['sampai'] ??= today()->toDateString();

        return Excel::download(new FarmasiResepLuarExport($this->externalReportQuery($filters)), 'laporan-resep-luar-'.today()->format('Y-m-d').'.xlsx');
    }

    private function externalReportQuery(array $filters): Builder
    {
        return ResepObat::query()
            ->where(fn (Builder $items) => $items->where('is_resep_luar', true)->orWhereHas('farmasiResepLuar'))
            ->whereHas('resep.kunjungan', fn (Builder $visits) => $visits
                ->whereHas('pemeriksaan', fn (Builder $exam) => $exam->whereNotNull('signed_at'))
                ->when($filters['dari'] ?? null, fn (Builder $records, string $date) => $records->whereDate('tanggal', '>=', $date))
                ->when($filters['sampai'] ?? null, fn (Builder $records, string $date) => $records->whereDate('tanggal', '<=', $date)))
            ->when($filters['search'] ?? null, fn (Builder $items, string $search) => $items->where(fn (Builder $matches) => $matches
                ->where('nama_obat', 'like', "%{$search}%")
                ->orWhereHas('resep', fn (Builder $prescriptions) => $prescriptions->where('no_resep', 'like', "%{$search}%")
                    ->orWhereHas('kunjungan.pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))));
    }

    public function report(Request $request): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filtered = [
            ...$filters,
            'dari' => $filters['dari'] ?? today()->startOfMonth()->toDateString(),
            'sampai' => $filters['sampai'] ?? today()->toDateString(),
        ];
        $records = $this->dispensingReportQuery($filtered)
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'items.obat', 'dispensingPharmacist'])
            ->latest('dispensed_at')->paginate(10)->withQueryString();
        $total = $this->dispensingReportQuery($filtered);
        $stats = [
            'total' => (clone $total)->count(),
            'patients' => (clone $total)->distinct('kunjungan_id')->count('kunjungan_id'),
            'items' => FarmasiItem::query()->whereHas('farmasi', fn (Builder $query) => $this->applyDispensingReportFilters($query, $filtered))->count(),
        ];

        return Inertia::render('farmasi/laporan-penyerahan', [
            'filters' => ['dari' => $filtered['dari'], 'sampai' => $filtered['sampai'], 'search' => $filtered['search'] ?? ''],
            'stats' => $stats,
            'rows' => [
                'data' => $records->getCollection()->map(fn (Farmasi $record): array => [
                    'date' => $record->dispensed_at?->format('d/m/Y H:i') ?? 'Belum dicatat',
                    'number' => $record->kunjungan?->no_kunjungan ?? 'Belum diisi',
                    'patient' => $record->kunjungan?->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $record->kunjungan?->pasien?->no_rm ?? 'Belum diisi',
                    'clinic' => $record->kunjungan?->poliklinik?->nama ?? 'Belum diisi',
                    'medicine' => $record->items->map(fn ($item) => $item->obat?->nama)->filter()->unique()->values()->implode(', ') ?: 'Tidak ada item yang diserahkan',
                    'quantity' => $record->items->count(),
                    'pharmacist' => $record->dispensingPharmacist?->name ?? 'Belum dicatat',
                    'detailUrl' => $record->kunjungan ? route('farmasi.pasien.kunjungan', $record->kunjungan).'?asal=laporan' : route('pelayanan.farmasi.index'),
                ])->values(),
                'currentPage' => $records->currentPage(), 'lastPage' => $records->lastPage(), 'from' => $records->firstItem(), 'to' => $records->lastItem(),
                'total' => $records->total(), 'previousUrl' => $records->previousPageUrl(), 'nextUrl' => $records->nextPageUrl(),
            ],
            'exportUrl' => route('farmasi.laporan.export', $request->query()),
        ]);
    }

    public function exportReport(Request $request)
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['dari'] ??= today()->startOfMonth()->toDateString();
        $filters['sampai'] ??= today()->toDateString();

        return Excel::download(new FarmasiDispensingExport($filters), 'laporan-penyerahan-obat-'.today()->format('Y-m-d').'.xlsx');
    }

    private function dispensingReportQuery(array $filters): Builder
    {
        return $this->applyDispensingReportFilters(Farmasi::query(), $filters);
    }

    private function applyDispensingReportFilters(Builder $query, array $filters): Builder
    {
        return $query->where('status', 'selesai')->whereNotNull('dispensed_at')
            ->when($filters['dari'] ?? null, fn (Builder $records, string $date) => $records->whereDate('dispensed_at', '>=', $date))
            ->when($filters['sampai'] ?? null, fn (Builder $records, string $date) => $records->whereDate('dispensed_at', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $records, string $search) => $records->whereHas('kunjungan', fn (Builder $visit) => $visit
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patient) => $patient->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))
                ->orWhereHas('resep.resepObat', fn (Builder $items) => $items->where('nama_obat', 'like', "%{$search}%"))));
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $audit, PatientAllergyHistory $allergies): Response
    {
        abort_unless($kunjungan->status === 'farmasi' || $kunjungan->farmasi()->where('status', 'selesai')->exists(), 404);
        $this->scheduling->ensureServiceDate($kunjungan);
        $kunjungan->load([
            'pasien', 'screening', 'pemeriksaan', 'poliklinik', 'dokter',
            'resep.resepObat.obat.batches',
            'resep.resepObat.farmasiResepLuar',
            'farmasi.items.obat', 'farmasi.items.resepObat',
        ]);

        $audit->record($request, 'pharmacy.view', $kunjungan->pasien_id, $kunjungan->id);
        $movements = StokMutasi::with(['batch', 'actor', 'obat'])->where('referensi_type', 'Farmasi')->where('referensi_id', $kunjungan->farmasi?->id ?? 0)->get();

        return Inertia::render('pelayanan/farmasi/show', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'examinationSigned' => $kunjungan->pemeriksaan?->signed_at !== null,
                'prescriptionNumber' => $kunjungan->resep?->no_resep,
                'patient' => [
                    'id' => $kunjungan->pasien->id,
                    'name' => $kunjungan->pasien->nama,
                    'medicalRecordNumber' => $kunjungan->pasien->no_rm,
                    'allergies' => $allergies->forVisit($kunjungan)['riwayat_alergi'],
                    'screeningAllergies' => $kunjungan->screening?->riwayat_alergi,
                    'examinationAllergies' => $allergies->examination($kunjungan->pemeriksaan),
                ],
                'doctor' => $kunjungan->dokter?->nama,
                'pharmacy' => $kunjungan->farmasi ? [
                    'status' => $kunjungan->farmasi->status,
                    'notes' => $kunjungan->farmasi->catatan,
                    'items' => $kunjungan->farmasi->items->map(fn ($item) => [
                        'prescriptionItemId' => $item->resep_obat_id,
                        'medicineId' => $item->obat_id,
                        'dispensedQuantity' => (float) $item->jumlah_diberikan,
                        'instructions' => $item->aturan_pakai,
                        'notes' => $item->catatan,
                    ])->values(),
                ] : null,
                'allocations' => $movements->map(fn (StokMutasi $movement): array => [
                    'medicine' => $movement->obat?->nama, 'batch' => $movement->batch?->nomor_batch,
                    'expiry' => $movement->batch?->expired_at?->format('d/m/Y'), 'quantity' => (float) $movement->jumlah,
                    'actor' => $movement->actor?->name, 'at' => $movement->created_at?->format('d/m/Y H:i'),
                ]),
                'prescriptionItems' => $kunjungan->resep?->resepObat->map(function (ResepObat $item) use ($kunjungan): array {
                    $pharmacyItem = $kunjungan->farmasi?->items->firstWhere('resep_obat_id', $item->id);

                    return [
                        'id' => $item->id,
                        'medicineId' => $item->obat_id,
                        'name' => $item->nama_obat,
                        'type' => $item->jenis,
                        'requestedQuantity' => (float) $item->jumlah,
                        'dispensedQuantity' => $pharmacyItem?->jumlah_diberikan !== null
                            ? (float) $pharmacyItem->jumlah_diberikan
                            : ($item->is_resep_luar || in_array($kunjungan->farmasi?->status, ['diproses', 'selesai'], true) ? 0 : (float) $item->jumlah),
                        'unit' => $item->satuan,
                        'instructions' => $pharmacyItem?->aturan_pakai ?? $item->aturan_pakai,
                        'external' => $item->is_resep_luar || $item->farmasiResepLuar !== null,
                        'externalByPharmacy' => $item->farmasiResepLuar !== null,
                        'externalActionUrl' => route('pelayanan.farmasi.resep-luar', [$kunjungan, $item]),
                        'externalPrintUrl' => route('farmasi.resep-luar.cetak', $kunjungan).'?asal=penyerahan',
                        'reserved' => $item->stok_dikurangi,
                        'stock' => (float) ($item->obat?->batches->filter(fn ($batch): bool => $batch->status === 'tersedia' && $batch->expired_at?->gt(today()))->sum('stok') ?? 0),
                        'stockUnit' => $item->obat?->satuan_kecil,
                    ];
                })->values() ?? collect(),
            ],
            'today' => today()->format('d/m/Y'),
            'appName' => config('app.name'),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        abort_unless($kunjungan->status === 'farmasi', 422, 'Kunjungan tidak berada pada tahap farmasi.');

        $data = $this->validateDispensingData($request, $kunjungan);

        DB::transaction(function () use ($data, $kunjungan, $request): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'farmasi', 422, 'Kunjungan tidak berada pada tahap farmasi.');
            $this->scheduling->ensureServiceDate($lockedVisit);

            $farmasi = Farmasi::where('kunjungan_id', $kunjungan->id)->lockForUpdate()->first();
            abort_if($farmasi?->status === 'selesai', 422, 'Dispensing sudah selesai dan tidak dapat diubah.');
            $farmasi ??= Farmasi::create([
                'kunjungan_id' => $kunjungan->id,
                'resep_id' => $kunjungan->resep?->id,
                'status' => 'diproses',
            ]);

            $this->replaceDispensingItems($farmasi, $data);

            app(ClinicalAuditRecorder::class)->record($request, 'pharmacy.draft_saved', $kunjungan->pasien_id, $kunjungan->id);
            $farmasi->update([
                'status' => 'diproses',
                'catatan' => $data['catatan'],
            ]);
        });

        return back()->with('success', 'Catatan penyerahan obat berhasil disimpan.');
    }

    public function markExternal(Request $request, Kunjungan $kunjungan, ResepObat $resepObat, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $kunjungan, $resepObat, $audit): void {
            $visit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($visit->status === 'farmasi' && $visit->pemeriksaan?->signed_at !== null, 422);
            $this->scheduling->ensureServiceDate($visit);
            $item = ResepObat::query()->whereKey($resepObat->id)->lockForUpdate()->firstOrFail();
            abort_unless($visit->resep?->id === $item->resep_id && ! $item->is_resep_luar && $item->obat_id !== null, 422);
            abort_if($item->farmasiResepLuar()->exists() || $item->stok_dikurangi, 422);
            abort_if(Farmasi::query()->where('kunjungan_id', $visit->id)->where('status', 'selesai')->exists(), 422);
            abort_if(FarmasiItem::query()->where('resep_obat_id', $item->id)->where('jumlah_diberikan', '>', 0)->exists(), 422);
            Obat::query()->whereKey($item->obat_id)->lockForUpdate()->firstOrFail();
            $available = (float) $item->obat->batches()->usable()->sum('stok');
            if ($available > 0) {
                throw ValidationException::withMessages(['resep_luar' => 'Resep luar tersedia ketika stok layak pakai obat ini kosong.']);
            }

            FarmasiResepLuar::create(['resep_obat_id' => $item->id, 'petugas_id' => $request->user()->id]);
            $audit->record($request, 'pharmacy.external_prescription', $visit->pasien_id, $visit->id, ['prescription_item_id' => $item->id]);
        });

        return back()->with('success', 'Obat dicatat untuk ditebus di luar klinik. Resep luar siap dicetak.');
    }

    public function selesai(Request $request, Kunjungan $kunjungan, PersediaanRecorder $inventory, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->exists('items') ? $this->validateDispensingData($request, $kunjungan) : null;

        $result = DB::transaction(function () use ($kunjungan, $request, $inventory, $audit, $data): string {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $farmasi = Farmasi::where('kunjungan_id', $kunjungan->id)->lockForUpdate()->first();

            if (! $farmasi && $data === null) {
                return 'missing';
            }

            if ($farmasi?->status === 'selesai') {
                return 'already';
            }

            abort_unless($lockedVisit->status === 'farmasi', 422, 'Kunjungan tidak berada pada tahap farmasi.');
            $this->scheduling->ensureServiceDate($lockedVisit);

            $lockedVisit->loadMissing('resep');
            $farmasi ??= Farmasi::create([
                'kunjungan_id' => $lockedVisit->id,
                'resep_id' => $lockedVisit->resep?->id,
                'status' => 'diproses',
            ]);

            if ($data !== null) {
                $this->replaceDispensingItems($farmasi, $data);
                $farmasi->update(['status' => 'diproses', 'catatan' => $data['catatan']]);
            }

            $farmasi->load(['items.obat', 'items.resepObat.farmasiResepLuar']);
            $lockedVisit->loadMissing('resep.resepObat.farmasiResepLuar');

            foreach ($farmasi->items as $item) {
                if (floor((float) $item->jumlah_diberikan) !== (float) $item->jumlah_diberikan) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah obat dari stok klinik harus berupa bilangan bulat.',
                    ]);
                }
            }

            foreach ($farmasi->items as $item) {
                if ($item->resep_obat_id || ! $item->obat_id) {
                    continue;
                }

                $matchingItems = $lockedVisit->resep?->resepObat->where('obat_id', $item->obat_id) ?? collect();
                if ($matchingItems->count() > 1) {
                    throw ValidationException::withMessages([
                        'items' => 'Item resep tidak jelas untuk obat '.$item->obat?->nama.'. Pilih item resep secara langsung.',
                    ]);
                }

                if ($matchingItems->count() === 1) {
                    $matchingItem = $matchingItems->first();
                    $item->update(['resep_obat_id' => $matchingItem->id]);
                    $item->setRelation('resepObat', $matchingItem);
                }
            }

            foreach ($lockedVisit->resep?->resepObat ?? collect() as $prescriptionItem) {
                $dispensed = (float) $farmasi->items->where('resep_obat_id', $prescriptionItem->id)->sum('jumlah_diberikan');
                if (! $prescriptionItem->is_resep_luar && ! $prescriptionItem->farmasiResepLuar && $dispensed < (float) $prescriptionItem->jumlah && blank($farmasi->catatan)) {
                    throw ValidationException::withMessages(['catatan' => 'Catat alasan dan tindak lanjut untuk resep yang belum diserahkan seluruhnya.']);
                }
            }

            $processedPrescriptionItemIds = [];
            foreach ($farmasi->items as $item) {
                $prescriptionItem = $item->resepObat;
                if (! $prescriptionItem
                    || (int) $prescriptionItem->resep_id !== (int) $lockedVisit->resep?->id
                    || $prescriptionItem->is_resep_luar || $prescriptionItem->farmasiResepLuar
                    || (int) $prescriptionItem->obat_id !== (int) $item->obat_id
                    || (float) $item->jumlah_diberikan > (float) $prescriptionItem->jumlah
                    || in_array($prescriptionItem->id, $processedPrescriptionItemIds, true)) {
                    throw ValidationException::withMessages([
                        'items' => 'Data dispensing tidak sesuai dengan resep kunjungan. Periksa kembali item obat.',
                    ]);
                }

                $processedPrescriptionItemIds[] = $prescriptionItem->id;
            }

            if ($lockedVisit->resep?->resepObat->contains('stok_dikurangi', true)) {
                throw ValidationException::withMessages(['items' => 'Reservasi lama harus direkonsiliasi sebelum penyerahan obat.']);
            }
            foreach ($farmasi->items->sortBy('obat_id') as $item) {
                $inventory->issue((int) $item->obat_id, (float) $item->jumlah_diberikan, $request->user(), $lockedVisit, 'Farmasi', $farmasi->id, $item->id);
            }

            $audit->record($request, 'pharmacy.dispensed', $lockedVisit->pasien_id, $lockedVisit->id);
            $farmasi->update(['status' => 'selesai', 'dispensed_by' => $request->user()->id, 'dispensed_at' => now()]);
            $lockedVisit->resep?->update(['status' => 'selesai']);
            $lockedVisit->update(['status' => 'selesai']);

            return 'complete';
        });

        if ($result === 'missing') {
            return back()->with('error', 'Data farmasi tidak ditemukan.');
        }

        if ($result === 'already') {
            return redirect()->route('pelayanan.farmasi.index')
                ->with('success', 'Dispensing kunjungan ini sudah selesai.');
        }

        return redirect()->route('pelayanan.farmasi.index')
            ->with('success', 'Obat telah diserahkan dan kunjungan selesai.');
    }

    /**
     * @return array{items: array<int, array{resep_obat_id: int|null, obat_id: int|null, jumlah_diberikan: int, aturan_pakai: string|null, catatan: string|null}>, catatan: string|null}
     */
    private function validateDispensingData(Request $request, Kunjungan $kunjungan): array
    {
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.resep_obat_id' => ['nullable', 'integer', 'distinct'],
            'items.*.obat_id' => ['nullable', 'integer', 'exists:obat,id'],
            'items.*.jumlah_diberikan' => ['required', 'integer', 'min:0'],
            'items.*.aturan_pakai' => ['nullable', 'string'],
            'items.*.catatan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        foreach ($data['items'] as $index => $item) {
            $data['items'][$index]['aturan_pakai'] = filled($item['aturan_pakai'] ?? null)
                ? trim($item['aturan_pakai'])
                : null;
            $data['items'][$index]['catatan'] = filled($item['catatan'] ?? null)
                ? trim($item['catatan'])
                : null;
        }
        $data['catatan'] = filled($data['catatan'] ?? null)
            ? trim($data['catatan'])
            : null;

        $kunjungan->loadMissing('resep.resepObat.farmasiResepLuar');
        $prescriptionItems = $kunjungan->resep?->resepObat ?? collect();
        $submittedPrescriptionItemIds = [];

        foreach ($data['items'] as $index => $item) {
            $resepObat = ! empty($item['resep_obat_id'])
                ? $prescriptionItems->firstWhere('id', (int) $item['resep_obat_id'])
                : null;

            if (! $resepObat && empty($item['resep_obat_id']) && ! empty($item['obat_id'])) {
                $matchingItems = $prescriptionItems->where('obat_id', (int) $item['obat_id']);
                if ($matchingItems->count() > 1) {
                    throw ValidationException::withMessages([
                        "items.$index.resep_obat_id" => 'Pilih item resep untuk obat ini secara langsung.',
                    ]);
                }

                $resepObat = $matchingItems->first();
                if ($resepObat) {
                    $data['items'][$index]['resep_obat_id'] = $resepObat->id;
                }
            }

            if (! empty($item['resep_obat_id']) && ! $resepObat) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Item resep tidak termasuk dalam kunjungan ini.',
                ]);
            }

            if (! $resepObat && (float) $item['jumlah_diberikan'] > 0) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Obat yang diberikan harus berasal dari resep kunjungan ini.',
                ]);
            }

            if ($resepObat && in_array($resepObat->id, $submittedPrescriptionItemIds, true)) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Item resep tidak boleh dicatat dua kali.',
                ]);
            }

            if ($resepObat) {
                $submittedPrescriptionItemIds[] = $resepObat->id;
            }

            if (($resepObat?->is_resep_luar || $resepObat?->farmasiResepLuar) && (float) $item['jumlah_diberikan'] > 0) {
                throw ValidationException::withMessages([
                    "items.$index.jumlah_diberikan" => 'Resep luar harus ditebus di apotek luar klinik.',
                ]);
            }

            if ($resepObat && (float) $item['jumlah_diberikan'] > (float) $resepObat->jumlah) {
                throw ValidationException::withMessages([
                    "items.$index.jumlah_diberikan" => 'Jumlah diberikan tidak boleh melebihi jumlah resep.',
                ]);
            }

            if ($resepObat && (float) $item['jumlah_diberikan'] > 0 && $resepObat->obat_id !== (int) ($item['obat_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    "items.$index.obat_id" => 'Obat yang diberikan harus sesuai dengan item resep.',
                ]);
            }

            if ($item['jumlah_diberikan'] > 0) {
                $obat = ! empty($item['obat_id']) ? Obat::find($item['obat_id']) : null;
                $usable = $obat ? (float) $obat->batches()->usable()->sum('stok') : 0;

                if (! $obat || ! $obat->is_active || $usable < $item['jumlah_diberikan']) {
                    $obatName = $obat?->nama ?? 'Obat tidak ditemukan';

                    throw ValidationException::withMessages([
                        'items' => "Stok obat {$obatName} tidak cukup. Stok tersedia: {$usable}, diminta: {$item['jumlah_diberikan']}",
                    ]);
                }
            }
        }

        return $data;
    }

    /** @param array{items: array<int, array{resep_obat_id: int|null, obat_id: int|null, jumlah_diberikan: int, aturan_pakai: string|null, catatan: string|null}>, catatan: string|null} $data */
    private function replaceDispensingItems(Farmasi $farmasi, array $data): void
    {
        $farmasi->items()->delete();

        foreach ($data['items'] as $item) {
            if ((float) $item['jumlah_diberikan'] <= 0 || empty($item['obat_id'])) {
                continue;
            }

            $farmasi->items()->create([
                'resep_obat_id' => $item['resep_obat_id'] ?? null,
                'obat_id' => $item['obat_id'],
                'jumlah_diberikan' => $item['jumlah_diberikan'],
                'aturan_pakai' => $item['aturan_pakai'],
                'catatan' => $item['catatan'],
            ]);
        }
    }
}
