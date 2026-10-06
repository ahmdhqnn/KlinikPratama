<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use Illuminate\View\View;

class PerawatDashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();

        $stats = [
            'menunggu_ttv' => Kunjungan::whereDate('tanggal', $today)->whereIn('status', ['menunggu', 'screening'])->count(),
            'siap_dokter' => Kunjungan::whereDate('tanggal', $today)->where('status', 'pemeriksaan')->count(),
            'triase_darurat' => Kunjungan::whereDate('tanggal', $today)
                ->whereHas('screening', fn ($query) => $query->where('kesimpulan_triase', 'merah'))
                ->count(),
        ];

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening'])
            ->whereDate('tanggal', $today)
            ->whereIn('status', ['menunggu', 'screening', 'pemeriksaan'])
            ->oldest('created_at')
            ->limit(10)
            ->get();

        return view('perawat.dashboard', compact('stats', 'kunjungan'));
    }
}
