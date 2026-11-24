<?php

namespace App\Http\Controllers;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Kepesertaan;
use App\Models\Obat;
use App\Models\SuratMedis;
use App\VisitOverviewService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $from = now()->startOfMonth()->toDateString();
        $to = today()->toDateString();
        $daily = Kunjungan::whereDate('tanggal', '>=', today()->subDays(6)->toDateString())
            ->whereDate('tanggal', '<=', $to)
            ->selectRaw('DATE(tanggal) as date, COUNT(*) as count')->groupBy('date')->pluck('count', 'date');
        $clinicDaily = Kunjungan::query()->join('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
            ->whereBetween('kunjungan.tanggal', [today()->subDays(6)->toDateString(), $to])
            ->whereIn('poliklinik.jenis', ['umum', 'gigi'])
            ->where('kunjungan.status', '!=', 'batal')->where('kontrol_pending', false)
            ->selectRaw('DATE(kunjungan.tanggal) as date, poliklinik.jenis, COUNT(*) as total')
            ->groupByRaw('DATE(kunjungan.tanggal), poliklinik.jenis')->get()->groupBy('date');
        $days = collect(range(6, 0))->map(fn (int $offset): array => [
            'tanggal' => today()->subDays($offset)->toDateString(),
            'poli' => $clinicDaily->get(today()->subDays($offset)->toDateString(), collect())->pluck('total', 'jenis')->map(fn ($total) => (int) $total)->all(),
            'jumlah' => (int) ($daily[today()->subDays($offset)->toDateString()] ?? 0),
        ]);

        $isAdmin = auth()->user()->isAdmin();
        $todayVisits = Kunjungan::whereDate('tanggal', $to);

        $adminStats = [];
        if ($isAdmin) {
            $controls = (clone $todayVisits)->whereNotNull('kontrol_asal_id')->where('status', '!=', 'batal');
            $month = Kunjungan::whereBetween('tanggal', [$from, $to])->where('status', '!=', 'batal')->where('kontrol_pending', false);
            $adminStats = [
                'waitingToday' => (clone $todayVisits)->where('kontrol_pending', false)
                    ->where(fn ($query) => $query->whereIn('status', ['menunggu', 'screening'])
                        ->orWhere(fn ($query) => $query->where('status', 'pemeriksaan')
                            ->whereDoesntHave('pemeriksaan', fn ($exam) => $exam->whereNotNull('started_at'))))->count(),
                'completedToday' => (clone $todayVisits)->where('status', 'selesai')->count(),
                'controlsConfirmed' => (clone $controls)->where('kontrol_pending', false)->count(),
                'controlsPending' => (clone $controls)->where('kontrol_pending', true)->count(),
                'asnVisits' => (clone $month)->whereIn('kategori_peserta', Kepesertaan::persistedCategoryKeys('asn'))->where('hak_layanan_snapshot->status_kepegawaian', 'aktif')->count(),
                'referralsThisMonth' => SuratMedis::externalReferral()->whereBetween('tanggal', [$from, $to])
                    ->whereHas('kunjungan', fn ($query) => $query->where('status', '!=', 'batal'))->distinct()->count('kunjungan_id'),
                'stockAlerts' => Obat::withStockAlert()->count(),
            ];
        }

        return Inertia::render('dashboard/index', [
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()), 'href' => auth()->user()->role === 'admin'
                ? route('pelayanan.antrian')
                : route('laporan.kunjungan')],
            'stats' => [
                'totalPatients' => Pasien::count(),
                'visitsToday' => Kunjungan::whereDate('tanggal', $to)->count(),
                'visitsThisMonth' => $isAdmin ? $month->count() : Kunjungan::whereDate('tanggal', '>=', $from)->whereDate('tanggal', '<=', $to)->count(),
                'prescriptionsToday' => Farmasi::where('status', 'selesai')->whereHas('items', fn ($query) => $query->where('jumlah_diberikan', '>', 0))->whereDate('dispensed_at', $to)->count(),
                ...$adminStats,
            ],
            'visitStatuses' => Kunjungan::whereDate('tanggal', $to)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status'),
            'visitsByDay' => $days,
            'canRegister' => auth()->user()->role === 'admin',
            'isAdmin' => $isAdmin,
        ]);
    }
}
