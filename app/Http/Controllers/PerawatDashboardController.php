<?php

namespace App\Http\Controllers;

use App\JadwalPraktikResolver;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\NurseQueueCounts;
use App\PerawatClinicResolver;
use App\VisitOverviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerawatDashboardController extends Controller
{
    public function __invoke(Request $request, PerawatClinicResolver $perawatClinicResolver, JadwalPraktikResolver $schedule, NurseQueueCounts $queues): Response
    {
        $today = today();
        $nurse = $perawatClinicResolver->nakes($request->user());
        $clinicId = (int) $nurse->poliklinik_id;

        $schedules = $schedule->doctors($clinicId, $today)
            ->flatMap(fn (Nakes $doctor) => $schedule->doctorSchedule($doctor->id, $today)
                ->filter(fn (array $entry): bool => $entry['id'] === $clinicId)
                ->map(fn (array $entry): array => [...$entry, 'doctor' => $doctor->nama, 'doctorId' => $doctor->id]))
            ->sortBy(['start', 'doctor'])->values();

        $clinicVisits = Kunjungan::query()->where('poliklinik_id', $clinicId);
        $todayVisits = (clone $clinicVisits)->whereDate('tanggal', $today);
        $queueCounts = $queues->forClinic($clinicId);
        $assessmentStatuses = ['menunggu', 'screening'];
        $stats = [
            'menunggu_tindakan' => $queueCounts['treatments'],
            'total_kunjungan' => (clone $clinicVisits)->count(),
            'total_kunjungan_hari_ini' => (clone $todayVisits)->count(),
            'menunggu_ttv' => $queueCounts['assessment'],
            'menunggu_ttv_hari_ini' => (clone $todayVisits)->whereIn('status', $assessmentStatuses)
                ->whereDoesntHave('screening', fn ($query) => $query->whereNotNull('submitted_at'))->count(),
            'siap_dokter' => (clone $clinicVisits)->where('status', 'pemeriksaan')->count(),
            'siap_dokter_hari_ini' => (clone $todayVisits)->where('status', 'pemeriksaan')->count(),
            'triase_darurat' => (clone $todayVisits)
                ->whereNotIn('status', ['selesai', 'batal'])
                ->whereHas('screening', fn ($query) => $query->where('kesimpulan_triase', 'merah'))
                ->count(),
        ];

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening'])
            ->whereDate('tanggal', $today)
            ->where('poliklinik_id', $clinicId)
            ->where(fn ($query) => $query
                ->where(fn ($assessment) => $assessment->whereIn('status', $assessmentStatuses)
                    ->whereDoesntHave('screening', fn ($screening) => $screening->whereNotNull('submitted_at')))
                ->orWhere(fn ($treatment) => $treatment->where('status', 'tindakan')
                    ->whereHas('pemeriksaan', fn ($examination) => $examination->whereNotNull('signed_at'))
                    ->whereHas('tindakanKunjungan', fn ($procedure) => $procedure
                        ->where('execution_role', 'perawat')->where('execution_status', 'pending'))))
            ->oldest('created_at')->oldest('id')
            ->paginate(3)
            ->withQueryString();

        return Inertia::render('perawat/dashboard', [
            'clinicName' => $nurse->poliklinik->nama,
            'schedules' => $schedules,
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
                    'treatmentStarted' => $visit->tindakan_started_at !== null,
                    'triage' => $visit->screening?->kesimpulan_triase,
                    'priority' => $visit->screening?->prioritas_layanan,
                    'actionUrl' => $visit->status === 'tindakan'
                        ? route('pelayanan.tindakan.show', $visit).'#pelaksanaan-tindakan'
                        : route('pelayanan.screening.show', $visit),
                    'actionLabel' => match ($visit->status) {
                        'tindakan' => $visit->tindakan_started_at ? 'Lanjutkan tindakan' : 'Mulai tindakan',
                        'menunggu' => 'Mulai pengkajian',
                        'screening' => 'Lanjutkan pengkajian',
                        default => 'Lanjutkan pengkajian',
                    },
                    'actionType' => $visit->status === 'tindakan' ? 'procedure' : 'screening',
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
