<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Exports\KunjunganExport;
use App\Models\Kunjungan;
use App\Models\LabHasil;
use App\Models\Obat;
use App\Models\Poliklinik;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    private function period(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data['dari'] ?? now()->startOfMonth()->toDateString();
        $to = $data['sampai'] ?? today()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['sampai' => 'Tanggal akhir harus sesudah atau sama dengan tanggal mulai.']);
        }

        return [$from, $to];
    }

    public function kunjungan(Request $request): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,selesai,batal'],
        ]);
        $dari = $filters['dari'] ?? now()->startOfMonth()->toDateString();
        $sampai = $filters['sampai'] ?? today()->toDateString();

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('tanggal')
            ->paginate(20)
            ->withQueryString();

        $summary = Kunjungan::whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
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
            ->paginate(20)
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
        $filters = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);
        $dari = $filters['dari'] ?? now()->startOfMonth()->toDateString();
        $sampai = $filters['sampai'] ?? today()->toDateString();

        $hasilLab = LabHasil::with(['laboratorium', 'kunjungan.pasien', 'petugas'])
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->latest()
            ->paginate(20)
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
        [$from, $to] = $this->period($request);
        $filters = $request->validate(['poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'], 'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,selesai,batal']]);
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
