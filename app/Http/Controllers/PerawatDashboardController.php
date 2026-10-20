<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use App\PerawatClinicResolver;
use App\VisitOverviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerawatDashboardController extends Controller
{
    public function __invoke(Request $request, PerawatClinicResolver $perawatClinicResolver): Response
    {
        $today = today();
        $clinicId = $perawatClinicResolver->clinicId($request->user());

        $stats = [
            'menunggu_ttv' => Kunjungan::where('poliklinik_id', $clinicId)->whereDate('tanggal', $today)->whereIn('status', ['menunggu', 'screening'])->count(),
            'siap_dokter' => Kunjungan::where('poliklinik_id', $clinicId)->whereDate('tanggal', $today)->where('status', 'pemeriksaan')->count(),
            'triase_darurat' => Kunjungan::whereDate('tanggal', $today)
                ->where('poliklinik_id', $clinicId)
                ->whereHas('screening', fn ($query) => $query->where('kesimpulan_triase', 'merah'))
                ->count(),
        ];

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening'])
            ->whereDate('tanggal', $today)
            ->where('poliklinik_id', $clinicId)
            ->whereIn('status', ['menunggu', 'screening', 'pemeriksaan'])
            ->oldest('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('perawat/dashboard', [
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()->where('poliklinik_id', $clinicId)), 'href' => route('pendaftaran.kunjungan-per-poli')],
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
                'actionUrl' => in_array($visit->status, ['menunggu', 'screening'], true)
                    ? route('pelayanan.screening.show', $visit)
                    : route('pelayanan.pasien.rekam-medis', $visit->pasien),
                'actionLabel' => match ($visit->status) {
                    'menunggu' => 'Skrining',
                    'screening' => 'Lanjutkan skrining',
                    default => 'Lihat RME',
                },
                'actionType' => in_array($visit->status, ['menunggu', 'screening'], true) ? 'screening' : 'detail',
            ])->values(),
        ]);
    }
}
