<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use Inertia\Inertia;
use Inertia\Response;

class PerawatDashboardController extends Controller
{
    public function __invoke(): Response
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

        return Inertia::render('perawat/dashboard', [
            'stats' => $stats,
            'visits' => $kunjungan->map(fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'patient' => $visit->pasien->nama,
                'medicalRecordNumber' => $visit->pasien->no_rm,
                'clinic' => $visit->poliklinik->nama,
                'status' => $visit->status,
                'triage' => $visit->screening?->kesimpulan_triase,
                'priority' => $visit->screening?->prioritas_layanan,
            ])->values(),
        ]);
    }
}
