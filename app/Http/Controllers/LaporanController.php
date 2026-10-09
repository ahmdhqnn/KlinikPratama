<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Exports\KunjunganExport;
use App\Exports\PendapatanExport;
use App\Exports\UtilisasiExport;
use App\Models\Kunjungan;
use App\Models\LabHasil;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\Models\Tagihan;
use App\UtilisasiQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function utilisasi(Request $request, UtilisasiQuery $query): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render('laporan/utilisasi', [
            'filters' => ['from' => $from, 'to' => $to],
            'report' => $query->report($from, $to),
            'exportUrl' => route('laporan.utilisasi.export', ['dari' => $from, 'sampai' => $to]),
        ]);
    }

    public function exportUtilisasi(Request $request, UtilisasiQuery $query, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $audit->record($request, 'utilization.export');

        return Excel::download(new UtilisasiExport($query->report($from, $to), $from, $to), 'utilisasi-klinik-'.$from.'-'.$to.'.xlsx');
    }

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
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                'patient' => $visit->pasien?->nama ?? '—',
                'clinic' => $visit->poliklinik?->nama ?? '—',
                'doctor' => $visit->dokter?->nama,
                'payer' => $visit->cost_center ?? 'Data historis',
                'status' => $visit->status,
            ]),
        ]);
    }

    public function pendapatan(Request $request): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);
        $dari = $filters['dari'] ?? now()->startOfMonth()->toDateString();
        $sampai = $filters['sampai'] ?? today()->toDateString();

        $tagihan = Tagihan::with(['kunjungan.pasien', 'kunjungan.poliklinik'])
            ->where('status', 'lunas')
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $totalPendapatan = Tagihan::where('status', 'lunas')
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->sum('total');

        $totalDiskon = Tagihan::where('status', 'lunas')
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->sum('diskon');

        return Inertia::render('laporan/pendapatan', [
            'filters' => ['from' => $dari, 'to' => $sampai],
            'exportUrl' => route('laporan.pendapatan.export', ['dari' => $dari, 'sampai' => $sampai]),
            'stats' => ['revenue' => (float) $totalPendapatan, 'discount' => (float) $totalDiskon],
            'invoices' => $this->pagination($tagihan, fn (Tagihan $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->no_tagihan,
                'date' => $invoice->created_at?->format('d/m/Y H:i'),
                'patient' => $invoice->kunjungan?->pasien?->nama ?? '—',
                'clinic' => $invoice->kunjungan?->poliklinik?->nama ?? '—',
                'paymentMethod' => $invoice->metode_bayar,
                'subtotal' => (float) $invoice->subtotal,
                'discount' => (float) $invoice->diskon,
                'total' => (float) $invoice->total,
                'receiptUrl' => route('pelayanan.kasir.kuitansi', $invoice),
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
            ->withSum('batches as batch_value', DB::raw('stok * harga_beli'))
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
                'purchasePrice' => (float) $medicine->harga_beli,
                'assetValue' => (float) $medicine->batch_value,
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
                'visitNumber' => $result->kunjungan?->no_kunjungan ?? '—',
                'patient' => $result->kunjungan?->pasien?->nama ?? '—',
                'test' => $result->laboratorium?->nama ?? '—',
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

    public function exportPendapatan(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new PendapatanExport($request->dari, $request->sampai),
            'laporan-pendapatan-'.date('Y-m-d').'.xlsx'
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
