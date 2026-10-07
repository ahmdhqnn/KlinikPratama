<?php

namespace App\Http\Controllers;

use App\Models\Diagnosa;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DokterDashboardController extends Controller
{
    public function index(): View
    {
        $dokterId = $this->dokterId();
        $today = today();
        $base = Kunjungan::where('dokter_id', $dokterId);

        $kunjunganHariIni = (clone $base)->whereDate('tanggal', $today)->count();
        $menungguPemeriksaan = (clone $base)->whereDate('tanggal', $today)
            ->where('status', 'pemeriksaan')->count();
        $selesaiHariIni = (clone $base)->whereDate('tanggal', $today)
            ->whereIn('status', ['farmasi', 'kasir', 'selesai'])->count();
        $jadwalHariIni = JadwalDokter::with('poliklinik')
            ->where('dokter_id', $dokterId)
            ->where('hari', strtolower($this->hariIndonesia($today->dayOfWeek)))
            ->where('is_active', true)
            ->orderBy('jam_mulai')
            ->get();

        $kunjunganTerkini = (clone $base)->with(['pasien', 'poliklinik'])
            ->whereDate('tanggal', $today)
            ->whereIn('status', ['pemeriksaan', 'farmasi', 'kasir'])
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        return view('dokter.dashboard', compact(
            'kunjunganHariIni', 'menungguPemeriksaan', 'selesaiHariIni',
            'jadwalHariIni', 'kunjunganTerkini'
        ));
    }

    public function appointments(Request $request): View
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
        $dari = $filters['dari'] ?? today()->toDateString();

        $kunjungan = $this->doctorVisits()
            ->with(['pasien', 'poliklinik'])
            ->whereDate('tanggal', '>=', $dari)
            ->when(isset($filters['sampai']), fn (Builder $query) => $query->whereDate('tanggal', '<=', $filters['sampai']))
            ->orderBy('tanggal')
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('dokter.appointments', compact('kunjungan'));
    }

    public function patients(Request $request): View
    {
        $pasien = Pasien::query()
            ->whereHas('kunjungan', fn (Builder $query) => $query->where('dokter_id', $this->dokterId()))
            ->withCount(['kunjungan as jumlah_kunjungan' => fn (Builder $query) => $query->where('dokter_id', $this->dokterId())])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $nested) use ($request): void {
                $search = $request->string('search')->toString();
                $nested->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            }))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('dokter.patients', compact('pasien'));
    }

    public function stock(): View
    {
        $obat = Obat::query()->where('is_active', true)->where('jenis', 'obat')
            ->orderBy('nama')->paginate(30);

        return view('dokter.stock', compact('obat'));
    }

    public function topDiagnoses(Request $request): View
    {
        $dari = $request->date('dari', now()->startOfMonth());
        $sampai = $request->date('sampai', today());
        $diagnosa = Diagnosa::query()
            ->select('kode_icd10', 'nama_diagnosa')
            ->selectRaw('COUNT(*) as jumlah')
            ->whereHas('pemeriksaan.kunjungan', fn (Builder $query) => $query
                ->where('dokter_id', $this->dokterId())
                ->whereBetween('tanggal', [$dari, $sampai]))
            ->groupBy('kode_icd10', 'nama_diagnosa')
            ->orderByDesc('jumlah')
            ->paginate(20)
            ->withQueryString();

        return view('dokter.top-diagnoses', compact('diagnosa', 'dari', 'sampai'));
    }

    private function doctorVisits(): Builder
    {
        return Kunjungan::query()->where('dokter_id', $this->dokterId());
    }

    private function dokterId(): int
    {
        $dokterId = auth()->user()->nakes?->id;
        abort_unless($dokterId, 403, 'Akun dokter belum terhubung dengan data tenaga kesehatan.');

        return $dokterId;
    }

    private function hariIndonesia(int $day): string
    {
        return ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'][$day];
    }
}
