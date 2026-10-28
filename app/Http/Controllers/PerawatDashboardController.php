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

        $clinicVisits = Kunjungan::query()->where('poliklinik_id', $clinicId);
        $todayVisits = (clone $clinicVisits)->whereDate('tanggal', $today);
        $assessmentStatuses = ['menunggu', 'screening'];
        $stats = [
            'menunggu_tindakan' => (clone $clinicVisits)->where('status', 'tindakan')->count(),
            'total_kunjungan' => (clone $clinicVisits)->count(),
            'total_kunjungan_hari_ini' => (clone $todayVisits)->count(),
            'menunggu_ttv' => (clone $clinicVisits)->whereIn('status', $assessmentStatuses)->count(),
            'menunggu_ttv_hari_ini' => (clone $todayVisits)->whereIn('status', $assessmentStatuses)->count(),
            'siap_dokter' => (clone $clinicVisits)->where('status', 'pemeriksaan')->count(),
            'siap_dokter_hari_ini' => (clone $todayVisits)->where('status', 'pemeriksaan')->count(),
            'triase_darurat' => (clone $todayVisits)
                ->whereHas('screening', fn ($query) => $query->where('kesimpulan_triase', 'merah'))
                ->count(),
        ];

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening', 'pemeriksaan'])
            ->whereDate('tanggal', $today)
            ->where('poliklinik_id', $clinicId)
            ->whereIn('status', ['menunggu', 'screening', 'pemeriksaan', 'tindakan'])
            ->oldest('created_at')->oldest('id')
            ->paginate(3)
            ->withQueryString();

        return Inertia::render('perawat/dashboard', [
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()->where('poliklinik_id', $clinicId)), 'href' => route('pendaftaran.kunjungan-per-poli')],
            'stats' => $stats,
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien->nama,
                    'medicalRecordNumber' => $visit->pasien->no_rm,
                    'clinic' => $visit->poliklinik->nama,
                    'status' => $visit->status,
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'actionUrl' => $visit->status === 'tindakan' ? route('pelayanan.tindakan.show', $visit) : (in_array($visit->status, ['menunggu', 'screening'], true)
                        ? route('pelayanan.screening.show', $visit)
                        : route('pelayanan.pasien.rekam-medis', $visit->pasien)),
                    'actionLabel' => match ($visit->status) {
                        'tindakan' => 'Laksanakan tindakan',
                        'menunggu' => 'Mulai pengkajian',
                        'screening' => 'Lanjutkan pengkajian',
                        default => 'Lihat RME',
                    },
                    'actionType' => in_array($visit->status, ['menunggu', 'screening'], true) ? 'screening' : 'detail',
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
        ]);
    }
}
