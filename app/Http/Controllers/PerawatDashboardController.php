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
            'menunggu_ttv_hari_ini' => (clone $todayVisits)->whereIn('status', $assessmentStatuses)->where('kontrol_pending', false)
                ->whereDoesntHave('screening', fn ($query) => $query->whereNotNull('submitted_at'))->count(),
            'siap_dokter' => (clone $clinicVisits)->where('status', 'pemeriksaan')->count(),
            'siap_dokter_hari_ini' => (clone $todayVisits)->where('status', 'pemeriksaan')->count(),
            'triase_darurat' => (clone $todayVisits)
                ->whereNotIn('status', ['selesai', 'batal'])
                ->whereHas('screening', fn ($query) => $query->where('kesimpulan_triase', 'merah'))
                ->count(),
        ];

        $kunjungan = (clone $todayVisits)
            ->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan:id,kunjungan_id,started_at,signed_at'])
            ->withExists(['tindakanKunjungan as has_pending_nurse_treatment' => fn ($query) => $query
                ->where('execution_role', 'perawat')->where('execution_status', 'pending')])
            ->latest('created_at')->latest('id')
            ->paginate(3)
            ->withQueryString();

        return Inertia::render('perawat/dashboard', [
            'clinicName' => $nurse->poliklinik->nama,
            'schedules' => $schedules,
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()->where('poliklinik_id', $clinicId)), 'href' => route('pendaftaran.kunjungan-per-poli')],
            'stats' => $stats,
            'visits' => [
                'data' => $kunjungan->getCollection()->map(function (Kunjungan $visit) use ($assessmentStatuses): array {
                    $canAssess = in_array($visit->status, $assessmentStatuses, true)
                        && $visit->screening?->submitted_at === null && $visit->canUpdateScreening();
                    $canTreat = $visit->status === 'tindakan'
                        && $visit->pemeriksaan?->signed_at !== null && $visit->has_pending_nurse_treatment;

                    return [
                        'id' => $visit->id,
                        'number' => $visit->no_kunjungan,
                        'patient' => $visit->pasien->nama,
                        'medicalRecordNumber' => $visit->pasien->no_rm,
                        'clinic' => $visit->poliklinik->nama,
                        'status' => $visit->status,
                        'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                        'pendingReservation' => $visit->isPendingReservation(),
                        'treatmentStarted' => $visit->tindakan_started_at !== null,
                        'triage' => $visit->screening?->kesimpulan_triase,
                        'priority' => $visit->screening?->prioritas_layanan,
                        'detailUrl' => route('pelayanan.kunjungan.show', $visit),
                        'recordUrl' => route('pelayanan.pasien.rekam-medis', [$visit->pasien, 'kunjungan_id' => $visit->id]),
                        'actionUrl' => match (true) {
                            $canTreat => route('pelayanan.tindakan.show', $visit).'#pelaksanaan-tindakan',
                            $canAssess => route('pelayanan.screening.show', $visit),
                            default => route('pelayanan.kunjungan.show', $visit),
                        },
                        'actionLabel' => match (true) {
                            $canTreat => $visit->tindakan_started_at ? 'Lanjutkan tindakan' : 'Mulai tindakan',
                            $canAssess => $visit->status === 'screening' ? 'Lanjutkan pengkajian' : 'Mulai pengkajian',
                            default => 'Detail kunjungan',
                        },
                        'actionType' => match (true) {
                            $canTreat => 'procedure',
                            $canAssess => 'screening',
                            default => 'detail',
                        },
                    ];
                })->values(),
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
