<?php

namespace App\Http\Controllers;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Obat;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class FarmasiDashboardController extends Controller
{
    public function index(): Response
    {
        $today = today()->toDateString();
        $pharmacyVisits = Kunjungan::query()->whereDate('tanggal', $today)->where('status', 'farmasi');
        $waitingVisits = (clone $pharmacyVisits)->whereDoesntHave('farmasi', fn (Builder $query) => $query->where('status', 'diproses'))->count();
        $processingVisits = (clone $pharmacyVisits)->whereHas('farmasi', fn (Builder $query) => $query->where('status', 'diproses'))->count();
        $completedToday = Farmasi::query()->where('status', 'selesai')->whereDate('dispensed_at', $today)->count();
        $lowStockMedicines = Obat::query()
            ->where('is_active', true)
            ->where('jenis', 'obat')
            ->whereRaw(
                'COALESCE((SELECT SUM(obat_batch.stok) FROM obat_batch WHERE obat_batch.obat_id = obat.id AND obat_batch.status = ? AND obat_batch.stok > 0 AND obat_batch.expired_at > ?), 0) <= obat.stok_minimum',
                ['tersedia', $today],
            )
            ->count();
        $recentVisits = (clone $pharmacyVisits)
            ->with(['pasien', 'poliklinik', 'farmasi'])
            ->orderBy('created_at')
            ->limit(8)
            ->get();

        return Inertia::render('farmasi/dashboard', [
            'stats' => [
                'waitingVisits' => $waitingVisits,
                'processingVisits' => $processingVisits,
                'completedToday' => $completedToday,
                'lowStockMedicines' => $lowStockMedicines,
            ],
            'recentVisits' => $recentVisits->map(fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'patient' => $visit->pasien?->nama ?? '—',
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                'clinic' => $visit->poliklinik?->nama ?? '—',
                'status' => $visit->farmasi?->status === 'diproses' ? 'diproses' : 'farmasi',
                'actionUrl' => route('pelayanan.farmasi.show', $visit),
                'actionLabel' => $visit->farmasi?->status === 'diproses' ? 'Lanjutkan' : 'Proses resep',
                'actionType' => 'pharmacy',
            ])->values(),
        ]);
    }
}
