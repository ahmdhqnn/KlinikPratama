<?php

namespace App\Http\Controllers;

use App\Exports\KunjunganExport;
use App\Exports\PendapatanExport;
use App\Models\Kunjungan;
use App\Models\LabHasil;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\Models\Tagihan;
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
        $filters = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,kasir,selesai,batal'],
        ]);
        $dari = $filters['dari'] ?? now()->startOfMonth()->toDateString();
        $sampai = $filters['sampai'] ?? today()->toDateString();

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'tagihan'])
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('tanggal')
            ->paginate(20)
            ->withQueryString();

        $totalKunjungan = Kunjungan::whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)->count();
        $totalBpjs = Kunjungan::whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)->where('jenis_bayar', 'bpjs')->count();
        $totalUmum = Kunjungan::whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)->where('jenis_bayar', 'umum')->count();

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
            'exportUrl' => route('laporan.kunjungan.export', ['dari' => $dari, 'sampai' => $sampai]),
            'stats' => ['total' => $totalKunjungan, 'bpjs' => $totalBpjs, 'general' => $totalUmum],
            'visits' => $this->pagination($kunjungan, fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'date' => $visit->tanggal?->format('d/m/Y'),
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                'patient' => $visit->pasien?->nama ?? '—',
                'clinic' => $visit->poliklinik?->nama ?? '—',
                'doctor' => $visit->dokter?->nama,
                'payer' => $visit->jenis_bayar,
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

        $obat = Obat::query()
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when($request->boolean('stok_rendah'), fn ($query) => $query->whereColumn('stok', '<=', 'stok_minimum'))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $totalItem = Obat::count();
        $stokRendahCount = Obat::whereColumn('stok', '<=', 'stok_minimum')->count();

        return Inertia::render('laporan/stok', [
            'filters' => ['type' => $filters['jenis'] ?? '', 'lowStockOnly' => $request->boolean('stok_rendah')],
            'stats' => ['totalItems' => $totalItem, 'lowStockItems' => $stokRendahCount],
            'medicines' => $this->pagination($obat, fn (Obat $medicine): array => [
                'id' => $medicine->id,
                'code' => $medicine->kode,
                'name' => $medicine->nama,
                'type' => $medicine->jenis,
                'stock' => (float) $medicine->stok,
                'unit' => $medicine->satuan_kecil,
                'minimumStock' => (float) $medicine->stok_minimum,
                'purchasePrice' => (float) $medicine->harga_beli,
                'assetValue' => (float) $medicine->stok * (float) $medicine->harga_beli,
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

    public function exportKunjungan(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new KunjunganExport($request->dari, $request->sampai),
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
