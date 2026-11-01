<?php

namespace App\Http\Controllers;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\VisitOverviewService;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class FarmasiDashboardController extends Controller
{
    public function index(): Response
    {
        $today = today()->toDateString();
        $activePharmacyVisits = Kunjungan::query()->where('status', 'farmasi')
            ->whereDoesntHave('farmasi', fn (Builder $query) => $query->where('status', 'selesai'));
        $pharmacyVisits = (clone $activePharmacyVisits)->whereDate('tanggal', $today);
        $waitingVisits = (clone $pharmacyVisits)->whereDoesntHave('farmasi', fn (Builder $query) => $query->where('status', 'diproses'))->count();
        $processingVisits = (clone $pharmacyVisits)->whereHas('farmasi', fn (Builder $query) => $query->where('status', 'diproses'))->count();
        $completedToday = Farmasi::query()->where('status', 'selesai')->whereDate('dispensed_at', $today)->count();
        $totalPharmacyVisits = Kunjungan::query()->where(fn (Builder $query) => $query->where('status', 'farmasi')
            ->orWhereHas('farmasi', fn (Builder $records) => $records->where('status', 'selesai')))->count();
        $externalToday = Kunjungan::query()->whereDate('tanggal', $today)
            ->whereHas('pemeriksaan', fn (Builder $exam) => $exam->whereNotNull('signed_at'))
            ->whereHas('resep.resepObat', fn (Builder $items) => $items->where(fn (Builder $item) => $item
                ->where('is_resep_luar', true)->orWhereHas('farmasiResepLuar')))->count();
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
            ->orderBy('created_at')->orderBy('id')
            ->paginate(10);

        return Inertia::render('farmasi/dashboard', [
            'followUp' => [...app(VisitOverviewService::class)->summarize($activePharmacyVisits), 'href' => route('pelayanan.farmasi.index')],
            'stats' => [
                'waitingVisits' => $waitingVisits,
                'processingVisits' => $processingVisits,
                'completedToday' => $completedToday,
                'lowStockMedicines' => $lowStockMedicines,
                'totalPharmacyVisits' => $totalPharmacyVisits,
                'externalToday' => $externalToday,
            ],
            'recentVisits' => [
                'data' => $recentVisits->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                    'status' => $visit->farmasi?->status === 'diproses' ? 'diproses' : 'farmasi',
                    'actionUrl' => route('pelayanan.farmasi.show', $visit),
                    'actionLabel' => $visit->farmasi?->status === 'diproses' ? 'Lanjutkan' : 'Proses resep',
                    'actionType' => 'pharmacy',
                ])->values(),
                'currentPage' => $recentVisits->currentPage(), 'lastPage' => $recentVisits->lastPage(),
                'from' => $recentVisits->firstItem(), 'to' => $recentVisits->lastItem(), 'total' => $recentVisits->total(),
                'previousUrl' => $recentVisits->previousPageUrl(), 'nextUrl' => $recentVisits->nextPageUrl(),
            ],
        ]);
    }
}
