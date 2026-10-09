<?php

namespace App\Http\Controllers;

use App\Models\Diagnosa;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokterDashboardController extends Controller
{
    public function index(): Response
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
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('dokter/dashboard', [
            'stats' => [
                'visitsToday' => $kunjunganHariIni,
                'waitingExaminations' => $menungguPemeriksaan,
                'completedToday' => $selesaiHariIni,
            ],
            'schedule' => $jadwalHariIni->map(fn (JadwalDokter $schedule): array => [
                'id' => $schedule->id,
                'clinic' => $schedule->poliklinik->nama,
                'start' => substr($schedule->jam_mulai, 0, 5),
                'end' => substr($schedule->jam_selesai, 0, 5),
            ])->values(),
            'recentVisits' => $kunjunganTerkini->map(fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'patient' => $visit->pasien->nama,
                'medicalRecordNumber' => $visit->pasien->no_rm,
                'clinic' => $visit->poliklinik->nama,
                'status' => $visit->status,
            ])->values(),
        ]);
    }

    public function appointments(Request $request): Response
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

        return Inertia::render('dokter/appointments', [
            'filters' => [
                'dari' => $dari,
                'sampai' => $filters['sampai'] ?? '',
            ],
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'date' => $visit->tanggal->format('d/m/Y'),
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien->nama,
                    'medicalRecordNumber' => $visit->pasien->no_rm,
                    'clinic' => $visit->poliklinik->nama,
                    'status' => $visit->status,
                    'examinationUrl' => route('pelayanan.pemeriksaan.show', $visit),
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

    public function patients(Request $request): Response
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

        return Inertia::render('dokter/patients', [
            'search' => $request->string('search')->toString(),
            'patients' => [
                'data' => $pasien->getCollection()->map(fn (Pasien $patient): array => [
                    'id' => $patient->id,
                    'medicalRecordNumber' => $patient->no_rm,
                    'name' => $patient->nama,
                    'gender' => $patient->jenis_kelamin,
                    'visitCount' => $patient->jumlah_kunjungan,
                    'recordUrl' => route('pelayanan.pasien.rekam-medis', $patient),
                ])->values(),
                'currentPage' => $pasien->currentPage(),
                'lastPage' => $pasien->lastPage(),
                'from' => $pasien->firstItem(),
                'to' => $pasien->lastItem(),
                'total' => $pasien->total(),
                'previousUrl' => $pasien->previousPageUrl(),
                'nextUrl' => $pasien->nextPageUrl(),
            ],
        ]);
    }

    public function stock(): Response
    {
        $obat = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')->where('is_active', true)->where('jenis', 'obat')
            ->orderBy('nama')->paginate(30);

        return Inertia::render('dokter/stock', [
            'medicines' => [
                'data' => $obat->getCollection()->map(fn (Obat $medicine): array => [
                    'id' => $medicine->id,
                    'code' => $medicine->kode,
                    'name' => $medicine->nama,
                    'unit' => $medicine->satuan_kecil,
                    'stock' => (float) $medicine->usable_stock,
                    'minimumStock' => (float) $medicine->stok_minimum,
                    'isLow' => (float) $medicine->usable_stock <= (float) $medicine->stok_minimum,
                ])->values(),
                'currentPage' => $obat->currentPage(),
                'lastPage' => $obat->lastPage(),
                'from' => $obat->firstItem(),
                'to' => $obat->lastItem(),
                'total' => $obat->total(),
                'previousUrl' => $obat->previousPageUrl(),
                'nextUrl' => $obat->nextPageUrl(),
            ],
        ]);
    }

    public function topDiagnoses(Request $request): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
        $dari = $filters['dari'] ?? now()->startOfMonth()->toDateString();
        $sampai = $filters['sampai'] ?? today()->toDateString();
        $diagnosa = Diagnosa::query()
            ->select('kode_icd10', 'nama_diagnosa')
            ->selectRaw('COUNT(*) as jumlah')
            ->whereHas('pemeriksaan.kunjungan', fn (Builder $query) => $query
                ->where('dokter_id', $this->dokterId())
                ->whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai))
            ->groupBy('kode_icd10', 'nama_diagnosa')
            ->orderByDesc('jumlah')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('dokter/top-diagnoses', [
            'filters' => ['dari' => $dari, 'sampai' => $sampai],
            'diagnoses' => [
                'data' => $diagnosa->getCollection()->map(fn (Diagnosa $diagnosis): array => [
                    'code' => $diagnosis->kode_icd10,
                    'name' => $diagnosis->nama_diagnosa,
                    'count' => (int) $diagnosis->jumlah,
                ])->values(),
                'currentPage' => $diagnosa->currentPage(),
                'lastPage' => $diagnosa->lastPage(),
                'from' => $diagnosa->firstItem(),
                'to' => $diagnosa->lastItem(),
                'total' => $diagnosa->total(),
                'previousUrl' => $diagnosa->previousPageUrl(),
                'nextUrl' => $diagnosa->nextPageUrl(),
            ],
        ]);
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
