<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Tagihan;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
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
            ->get()
            ->map(fn (Kunjungan $kunjungan): array => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'patient' => $kunjungan->pasien->nama,
                'medicalRecordNumber' => $kunjungan->pasien->no_rm,
                'clinic' => $kunjungan->poliklinik->nama,
                'doctor' => $kunjungan->dokter?->nama,
                'status' => $kunjungan->status,
            ])
            ->values();

        return Inertia::render('dashboard/index', [
            'stats' => [
                'totalPatients' => $totalPasien,
                'visitsToday' => $kunjunganHariIni,
                'visitsThisMonth' => $kunjunganBulanIni,
                'revenueThisMonth' => $pendapatanBulanIni,
            ],
            'visitStatuses' => $statusKunjungan->toArray(),
            'visitsByDay' => $kunjunganPerHari->all(),
            'recentVisits' => $kunjunganTerkini->all(),
        ]);
    }
}
