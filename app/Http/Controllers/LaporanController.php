<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DateRangeFilter;
use App\Exports\KunjunganExport;
use App\Exports\OperationalReportExport;
use App\Models\Kunjungan;
use App\Models\LabHasil;
use App\Models\Obat;
use App\Models\ObatBatch;
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
        $filters = array_intersect_key($visits->filters($request), array_flip(['poliklinik_id', 'status']));
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
            'filters' => ['type' => $filters['jenis'] ?? '', 'lowStockOnly' => $request->boolean('stok_rendah')],
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
            ]),
        ]);
    }

    public function laboratorium(Request $request): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $dari = $period->from ?? '';
        $sampai = $period->to ?? '';
        $hasilLab = $this->laboratoryQuery($period)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('laporan/laboratorium', [
            'filters' => ['from' => $dari, 'to' => $sampai],
            'exportUrl' => route('laporan.laboratorium.export', ['dari' => $dari, 'sampai' => $sampai]),
            'results' => $this->pagination($hasilLab, fn (LabHasil $result): array => [
                'id' => $result->id,
                'date' => $result->created_at?->format('d/m/Y H:i'),
                'visitNumber' => $result->kunjungan?->no_kunjungan ?? 'Belum diisi',
                'patient' => $result->kunjungan?->pasien?->nama ?? 'Belum diisi',
                'test' => $result->laboratorium?->nama ?? 'Belum diisi',
                'staff' => $result->petugas?->nama,
                'status' => $result->status,
            ]),
        ]);
    }

    public function exportKunjungan(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $period = DateRangeFilter::fromRequest($request);
        $from = $period->from;
        $to = $period->to;
        $filters = array_intersect_key((new VisitQueryService)->filters($request), array_flip(['poliklinik_id', 'status']));
        $audit->record($request, 'visit_report.export', metadata: ['from' => $from, 'to' => $to, ...$filters]);

        return Excel::download(
            new KunjunganExport($from, $to, $filters['poliklinik_id'] ?? null, $filters['status'] ?? null),
            'laporan-kunjungan-'.date('Y-m-d').'.xlsx'
        );
    }

    public function exportStok(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $this->stockFilters($request);
        $audit->record($request, 'inventory_report.export', metadata: $filters);

        return Excel::download(new OperationalReportExport(
            $this->stockQuery($filters),
            ['Kode', 'Nama item', 'Jenis', 'Satuan', 'Stok fisik', 'Stok layak pakai', 'Stok minimum', 'Status'],
            fn (Obat $item): array => [
                $item->kode, $item->nama, strtoupper($item->jenis), $item->satuan_kecil,
                (float) $item->stok, (float) $item->usable_stock, (float) $item->stok_minimum,
                (float) $item->usable_stock <= 0 ? 'Habis' : ((float) $item->usable_stock <= (float) $item->stok_minimum ? 'Kritis' : 'Aman'),
            ],
        ), 'laporan-stok-'.today()->toDateString().'.xlsx');
    }

    public function exportLaboratorium(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $period = DateRangeFilter::fromRequest($request);
        $audit->record($request, 'laboratory_report.export', metadata: ['from' => $period->from, 'to' => $period->to]);

        return Excel::download(new OperationalReportExport(
            $this->laboratoryQuery($period)->with('detail.indikator'),
            ['No. Kunjungan', 'Tanggal', 'Pasien', 'Pemeriksaan', 'Petugas', 'Status', 'Indikator', 'Hasil', 'Satuan', 'Keterangan'],
            function (LabHasil $result): array {
                $identity = [
                    $result->kunjungan?->no_kunjungan, $result->created_at?->format('d/m/Y H:i'),
                    $result->kunjungan?->pasien?->nama, $result->laboratorium?->nama,
                    $result->petugas?->nama, $result->status,
                ];

                return $result->detail->isEmpty()
                    ? [[...$identity, null, null, null, null]]
                    : $result->detail->map(fn ($detail): array => [...$identity, $detail->indikator?->nama, $detail->nilai, $detail->indikator?->satuan, $detail->keterangan])->all();
            },
        ), 'laporan-laboratorium-'.today()->toDateString().'.xlsx');
    }

    private function stockFilters(Request $request): array
    {
        return $request->validate([
            'jenis' => ['nullable', 'in:obat,bhp'],
            'stok_rendah' => ['nullable', 'boolean'],
        ]);
    }

    private function stockQuery(array $filters): Builder
    {
        return Obat::withSum(['batches as usable_stock' => fn (Builder $query) => $query->usable()], 'stok')
            ->when($filters['jenis'] ?? null, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->when($filters['stok_rendah'] ?? false, fn (Builder $query) => $query->where(
                ObatBatch::query()->usable()
                    ->whereColumn('obat_batch.obat_id', 'obat.id')->selectRaw('COALESCE(SUM(stok), 0)'),
                '<=',
                DB::raw('obat.stok_minimum'),
            ))
            ->orderBy('nama')->orderBy('id');
    }

    private function laboratoryQuery(DateRangeFilter $period): Builder
    {
        return $period->apply(LabHasil::with(['laboratorium', 'kunjungan.pasien', 'petugas']), 'created_at')
            ->latest()->latest('id');
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
