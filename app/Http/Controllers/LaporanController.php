<?php

namespace App\Http\Controllers;

use App\Exports\KunjunganExport;
use App\Exports\PendapatanExport;
use App\Models\Kunjungan;
use App\Models\LabHasil;
use App\Models\Obat;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function kunjungan(Request $request): View
    {
        $dari = $request->dari ?? now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?? today()->toDateString();

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'tagihan'])
            ->whereBetween('tanggal', [$dari, $sampai])
            ->when($request->poliklinik_id, fn ($q, $p) => $q->where('poliklinik_id', $p))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('tanggal')
            ->paginate(20)
            ->withQueryString();

        $totalKunjungan = Kunjungan::whereBetween('tanggal', [$dari, $sampai])->count();
        $totalBpjs = Kunjungan::whereBetween('tanggal', [$dari, $sampai])->where('jenis_bayar', 'bpjs')->count();
        $totalUmum = Kunjungan::whereBetween('tanggal', [$dari, $sampai])->where('jenis_bayar', 'umum')->count();

        return view('laporan.kunjungan', compact('kunjungan', 'dari', 'sampai', 'totalKunjungan', 'totalBpjs', 'totalUmum'));
    }

    public function pendapatan(Request $request): View
    {
        $dari = $request->dari ?? now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?? today()->toDateString();

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

        return view('laporan.pendapatan', compact('tagihan', 'dari', 'sampai', 'totalPendapatan', 'totalDiskon'));
    }

    public function stok(Request $request): View
    {
        $obat = Obat::query()
            ->when($request->jenis, fn ($q, $j) => $q->where('jenis', $j))
            ->when($request->stok_rendah, fn ($q) => $q->whereColumn('stok', '<=', 'stok_minimum'))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $totalItem = Obat::count();
        $stokRendahCount = Obat::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('laporan.stok', compact('obat', 'totalItem', 'stokRendahCount'));
    }

    public function laboratorium(Request $request): View
    {
        $dari = $request->dari ?? now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?? today()->toDateString();

        $hasilLab = LabHasil::with(['laboratorium', 'kunjungan.pasien', 'petugas'])
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('laporan.laboratorium', compact('hasilLab', 'dari', 'sampai'));
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
}
