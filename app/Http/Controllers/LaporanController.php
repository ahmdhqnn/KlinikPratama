<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DateRangeFilter;
use App\Exports\KunjunganExport;
use App\Exports\OperationalReportExport;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\VisitQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function kunjungan(Request $request): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $visits = new VisitQueryService;
        $filters = array_intersect_key($visits->filters($request), array_flip(['poliklinik_id', 'status', 'search']));
        $dari = $period->from ?? '';
        $sampai = $period->to ?? '';
        $summary = $visits->filtered($filters, period: $period);
        $kunjungan = (clone $summary)->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();

        $totalKunjungan = (clone $summary)->count();
        $totalCompleted = (clone $summary)->where('status', 'selesai')->count();
        $totalVerified = (clone $summary)->whereNotNull('verified_at')->count();

        return Inertia::render('laporan/kunjungan', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'from' => $dari,
                'to' => $sampai,
                'clinicId' => isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : '',
                'status' => $filters['status'] ?? '',
            ],
            'clinics' => Poliklinik::where('is_active', true)->orderBy('nama')->get()->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'exportUrl' => route('laporan.kunjungan.export', [...$filters, 'dari' => $dari, 'sampai' => $sampai]),
            'stats' => ['total' => $totalKunjungan, 'completed' => $totalCompleted, 'verified' => $totalVerified],
            'visits' => $this->pagination($kunjungan, fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'date' => $visit->tanggal?->format('d/m/Y'),
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                'patient' => $visit->pasien?->nama ?? 'Belum diisi',
                'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                'doctor' => $visit->dokter?->nama,
                'unit' => $visit->unit_kerja ?? 'Belum dicatat',
                'status' => $visit->status,
                'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
            ]),
        ]);
    }

    public function stok(Request $request): Response
    {
        $filters = $this->stockFilters($request);
        $items = $this->stockQuery($filters);
        $obat = (clone $items)->paginate(10)->withQueryString();
        $totalItem = (clone $items)->count();
        $stokRendahCount = $this->stockQuery([...$filters, 'stok_rendah' => true])->count();

        return Inertia::render('laporan/stok', [
            'filters' => ['search' => $filters['search'] ?? '', 'type' => $filters['jenis'] ?? '', 'lowStockOnly' => $request->boolean('stok_rendah'), 'alertsOnly' => $request->boolean('perhatian')],
            'exportUrl' => route('laporan.stok.export', $filters),
            'stats' => ['totalItems' => $totalItem, 'lowStockItems' => $stokRendahCount],
            'medicines' => $this->pagination($obat, fn (Obat $medicine): array => [
                'id' => $medicine->id,
                'code' => $medicine->kode,
                'name' => $medicine->nama,
                'type' => $medicine->jenis,
                'stock' => (float) $medicine->stok,
                'usable' => (float) $medicine->usable_stock,
                'unit' => $medicine->satuan_kecil,
                'minimumStock' => (float) $medicine->stok_minimum,
                'nextExpiry' => $medicine->next_expiry,
            ]),
        ]);
    }

    public function exportKunjungan(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $period = DateRangeFilter::fromRequest($request);
        $from = $period->from;
        $to = $period->to;
        $filters = array_intersect_key((new VisitQueryService)->filters($request), array_flip(['poliklinik_id', 'status', 'search']));
        $audit->record($request, 'visit_report.export', metadata: ['from' => $from, 'to' => $to, ...$filters]);

        return Excel::download(
            new KunjunganExport($from, $to, $filters['poliklinik_id'] ?? null, $filters['status'] ?? null, $filters['search'] ?? null),
            'laporan-kunjungan-'.date('Y-m-d').'.xlsx'
        );
    }

    public function exportStok(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $this->stockFilters($request);
        $audit->record($request, 'inventory_report.export', metadata: $filters);

        return Excel::download(new OperationalReportExport(
            $this->stockQuery($filters),
            ['Kode', 'Nama item', 'Jenis', 'Satuan', 'Stok fisik', 'Stok layak pakai', 'Stok minimum', 'Status', 'Kedaluwarsa terdekat'],
            fn (Obat $item): array => [
                $item->kode, $item->nama, strtoupper($item->jenis), $item->satuan_kecil,
                (float) $item->stok, (float) $item->usable_stock, (float) $item->stok_minimum,
                (float) $item->usable_stock <= 0 ? 'Habis' : ((float) $item->usable_stock <= (float) $item->stok_minimum ? 'Kritis' : 'Aman'),
                $item->next_expiry,
            ],
        ), 'laporan-stok-'.today()->toDateString().'.xlsx');
    }

    private function stockFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:obat,bhp'],
            'perhatian' => ['nullable', 'boolean'],
            'stok_rendah' => ['nullable', 'boolean'],
        ]);
    }

    private function stockQuery(array $filters): Builder
    {
        return Obat::withSum(['batches as usable_stock' => fn (Builder $query) => $query->usable()], 'stok')
            ->withMin(['batches as next_expiry' => fn (Builder $query) => $query->usable()], 'expired_at')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $items) => $items->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->when($filters['stok_rendah'] ?? false, fn (Builder $query) => $query->lowUsableStock())
            ->when($filters['perhatian'] ?? false, fn (Builder $query) => $query->withStockAlert())
            ->orderBy('nama')->orderBy('id');
    }

    private function pagination(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => $paginator->getCollection()->map($map)->values(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'previousUrl' => $paginator->previousPageUrl(),
            'nextUrl' => $paginator->nextPageUrl(),
        ];
    }
}
