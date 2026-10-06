<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Tagihan;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = today();
        $thisMonth = now()->startOfMonth();

        $totalPasien = Pasien::count();
        $kunjunganHariIni = Kunjungan::whereDate('tanggal', $today)->count();
        $kunjunganBulanIni = Kunjungan::where('tanggal', '>=', $thisMonth)->count();

        $pendapatanBulanIni = Tagihan::where('status', 'lunas')
            ->where('created_at', '>=', $thisMonth)
            ->sum('total');

        $statusKunjungan = Kunjungan::whereDate('tanggal', $today)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $kunjunganPerHari = Kunjungan::where('tanggal', '>=', now()->subDays(7))
            ->selectRaw('DATE(tanggal) as tgl, COUNT(*) as jumlah')
            ->groupBy('tgl')
            ->orderBy('tgl')
            ->get()
            ->map(fn ($item) => [
                'tanggal' => $item->tgl,
                'jumlah' => $item->jumlah,
            ]);

        $kunjunganTerkini = Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->whereDate('tanggal', $today)
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'totalPasien',
            'kunjunganHariIni',
            'kunjunganBulanIni',
            'pendapatanBulanIni',
            'statusKunjungan',
            'kunjunganPerHari',
            'kunjunganTerkini',
        ));
    }
}
