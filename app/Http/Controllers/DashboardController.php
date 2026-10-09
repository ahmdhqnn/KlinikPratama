<?php

namespace App\Http\Controllers;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\UtilisasiQuery;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(UtilisasiQuery $utilization): Response
    {
        $from = now()->startOfMonth()->toDateString();
        $to = today()->toDateString();
        $report = $utilization->report($from, $to);
        $daily = $utilization->visits(today()->subDays(6)->toDateString(), $to)
            ->selectRaw('DATE(tanggal) as date, COUNT(*) as count')->groupBy('date')->pluck('count', 'date');
        $days = collect(range(6, 0))->map(fn (int $offset): array => [
            'tanggal' => today()->subDays($offset)->toDateString(),
            'jumlah' => (int) ($daily[today()->subDays($offset)->toDateString()] ?? 0),
        ]);

        return Inertia::render('dashboard/index', [
            'stats' => [
                'totalPatients' => Pasien::count(),
                'visitsToday' => $utilization->visits($to, $to)->count(),
                'visitsThisMonth' => $report['stats']['visits'],
                'prescriptionsToday' => Farmasi::where('status', 'selesai')->whereHas('items', fn ($query) => $query->where('jumlah_diberikan', '>', 0))->whereDate('dispensed_at', $to)->count(),
            ],
            'visitStatuses' => Kunjungan::whereDate('tanggal', $to)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status'),
            'visitsByDay' => $days,
            'utilization' => $report,
            'canRegister' => auth()->user()->role === 'admin',
        ]);
    }
}
