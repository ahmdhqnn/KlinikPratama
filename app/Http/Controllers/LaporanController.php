<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DateRangeFilter;
use App\Exports\KunjunganExport;
use App\Models\Kunjungan;
use App\Models\LabHasil;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\VisitQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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
                'examinationStarted' => $visit->pemeriksaan !== null,
            ]),
        ]);
    }

    public function stok(Request $request): Response
    {
        $filters = $request->validate([
            'jenis' => ['nullable', 'in:obat,bhp'],
            'stok_rendah' => ['nullable', 'boolean'],
        ]);

        $stockCondition = "(SELECT COALESCE(SUM(stok), 0) FROM obat_batch WHERE obat_batch.obat_id = obat.id AND status = 'tersedia' AND expired_at > ? AND stok > 0) <= obat.stok_minimum";
        $obat = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when($request->boolean('stok_rendah'), fn ($query) => $query->whereRaw($stockCondition, [today()->toDateString()]))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $totalItem = Obat::count();
        $stokRendahCount = Obat::whereRaw($stockCondition, [today()->toDateString()])->count();

        return Inertia::render('laporan/stok', [
            'filters' => ['type' => $filters['jenis'] ?? '', 'lowStockOnly' => $request->boolean('stok_rendah')],
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
        $hasilLab = $period->apply(LabHasil::with(['laboratorium', 'kunjungan.pasien', 'petugas']), 'created_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('laporan/laboratorium', [
            'filters' => ['from' => $dari, 'to' => $sampai],
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
