<?php

namespace App\Http\Controllers\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function dashboard(): View
    {
        $today = today();
        $stats = [
            'total_kunjungan_hari_ini' => Kunjungan::whereDate('tanggal', $today)->count(),
            'pasien_baru_hari_ini' => Pasien::whereDate('created_at', $today)->count(),
            'menunggu_screening' => Kunjungan::whereDate('tanggal', $today)->where('status', 'menunggu')->count(),
            'sedang_pemeriksaan' => Kunjungan::whereDate('tanggal', $today)->whereIn('status', ['screening', 'pemeriksaan'])->count(),
        ];

        return view('pendaftaran.dashboard', compact('stats'));
    }

    public function laporanKunjungan(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,kasir,selesai,batal'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('pasien', function ($patientQuery) use ($search): void {
                    $patientQuery->where('nama', 'like', "%{$search}%")
                        ->orWhere('no_rm', 'like', "%{$search}%");
                });
            })
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $poliklinikId) => $query->where('poliklinik_id', $poliklinikId))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return view('pendaftaran.laporan-kunjungan', compact('kunjungan', 'poliklinikList'));
    }

    public function pendaftaranBaru(): View
    {
        return view('pendaftaran.pendaftaran-baru', $this->registrationFormData());
    }

    public function storePasienBaru(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'digits:16', 'unique:pasien,nik'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'agama' => ['nullable', 'string', 'max:50'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:2000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'asuransi_id' => ['nullable', 'required_if:jenis_bayar,asuransi', 'exists:asuransi,id'],
            'no_asuransi' => ['nullable', 'string', 'max:50'],
            'riwayat_alergi' => ['nullable', 'string', 'max:2000'],
            'poliklinik_id' => ['required', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureDoctorIsScheduled($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], today()->locale('id')->dayName);

        $pasien = DB::transaction(function () use ($validated): Pasien {
            $pasien = Pasien::create([
                'no_rm' => $this->generateNoRM(),
                'nama' => $validated['nama'],
                'nik' => $validated['nik'] ?? null,
                'tempat_lahir' => $validated['tempat_lahir'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'golongan_darah' => $validated['golongan_darah'] ?? null,
                'agama' => $validated['agama'] ?? null,
                'nama_ibu' => $validated['nama_ibu'] ?? null,
                'alamat' => $validated['alamat'] ?? null,
                'rt' => $validated['rt'] ?? null,
                'rw' => $validated['rw'] ?? null,
                'kelurahan' => $validated['kelurahan'] ?? null,
                'kecamatan' => $validated['kecamatan'] ?? null,
                'telepon' => $validated['telepon'] ?? null,
                'asuransi_id' => $validated['asuransi_id'] ?? null,
                'no_asuransi' => $validated['no_asuransi'] ?? null,
                'riwayat_alergi' => $validated['riwayat_alergi'] ?? null,
            ]);

            Kunjungan::create([
                'no_kunjungan' => Kunjungan::generateNomor(),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'dokter_id' => $validated['dokter_id'] ?? null,
                'asuransi_id' => $validated['asuransi_id'] ?? null,
                'tanggal' => today(),
                'status' => 'menunggu',
                'jenis_pasien' => 'baru',
                'jenis_bayar' => $validated['jenis_bayar'],
                'catatan' => $validated['catatan'] ?? null,
            ]);

            return $pasien;
        });

        return redirect()->route('pendaftaran.laporan-kunjungan')
            ->with('success', "Pasien {$pasien->nama} (No. RM: {$pasien->no_rm}) berhasil didaftarkan.");
    }

    public function pendaftaranLama(): View
    {
        return view('pendaftaran.pendaftaran-lama', $this->registrationFormData());
    }

    public function searchPasien(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $search = $validated['q'];

        $pasien = Pasien::query()
            ->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            })
            ->orderBy('nama')
            ->limit(10)
            ->get(['id', 'no_rm', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'telepon']);

        return response()->json($pasien);
    }

    public function getPasienDetail(Pasien $pasien): JsonResponse
    {
        return response()->json($pasien->only(['id', 'no_rm', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'telepon', 'asuransi_id', 'no_asuransi']));
    }

    public function storePasienLama(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poliklinik_id' => ['required', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'required_if:jenis_bayar,asuransi', 'exists:asuransi,id'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureDoctorIsScheduled($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], today()->locale('id')->dayName);
        $pasien = Pasien::findOrFail($validated['pasien_id']);

        DB::transaction(function () use ($validated, $pasien): void {
            Kunjungan::create([
                'no_kunjungan' => Kunjungan::generateNomor(),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'dokter_id' => $validated['dokter_id'] ?? null,
                'asuransi_id' => $validated['asuransi_id'] ?? null,
                'tanggal' => today(),
                'status' => 'menunggu',
                'jenis_pasien' => 'lama',
                'jenis_bayar' => $validated['jenis_bayar'],
                'catatan' => $validated['catatan'] ?? null,
            ]);
        });

        return redirect()->route('pendaftaran.laporan-kunjungan')
            ->with('success', "Pasien {$pasien->nama} berhasil didaftarkan untuk kunjungan baru.");
    }

    public function databasePasien(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
        ]);

        $pasien = Pasien::with('asuransi')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($patientQuery) use ($search): void {
                    $patientQuery->where('nama', 'like', "%{$search}%")
                        ->orWhere('no_rm', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%");
                });
            })
            ->when($filters['jenis_kelamin'] ?? null, fn ($query, string $sex) => $query->where('jenis_kelamin', $sex))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pendaftaran.database-pasien', compact('pasien'));
    }

    public function kunjunganPerPoli(Request $request): View
    {
        $filters = $request->validate([
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'tanggal' => ['nullable', 'date'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,kasir,selesai,batal'],
        ]);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $selectedPoli = $filters['poliklinik_id'] ?? $poliklinikList->first()?->id;

        $kunjungan = Kunjungan::with(['pasien', 'dokter'])
            ->where('poliklinik_id', $selectedPoli)
            ->whereDate('tanggal', $filters['tanggal'] ?? today())
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->oldest('created_at')
            ->get();

        return view('pendaftaran.kunjungan-per-poli', compact('poliklinikList', 'kunjungan', 'selectedPoli'));
    }

    public function laporanTopDiagnosa(Request $request): View
    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $tanggalMulai = Carbon::parse($filters['tanggal_mulai'] ?? now()->startOfMonth()->toDateString());
        $tanggalSelesai = Carbon::parse($filters['tanggal_selesai'] ?? now()->endOfMonth()->toDateString());

        $topDiagnosa = DB::table('diagnosa')
            ->join('pemeriksaan', 'diagnosa.pemeriksaan_id', '=', 'pemeriksaan.id')
            ->join('kunjungan', 'pemeriksaan.kunjungan_id', '=', 'kunjungan.id')
            ->select('diagnosa.kode_icd10 as kode', 'diagnosa.nama_diagnosa as nama', DB::raw('COUNT(*) as total'))
            ->whereDate('kunjungan.tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('kunjungan.tanggal', '<=', $tanggalSelesai->toDateString())
            ->groupBy('diagnosa.kode_icd10', 'diagnosa.nama_diagnosa')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return view('pendaftaran.laporan-top-diagnosa', compact('topDiagnosa', 'tanggalMulai', 'tanggalSelesai'));
    }

    public function jadwalPraktik(Request $request): View
    {
        $filters = $request->validate([
            'dokter_id' => ['nullable', 'integer', 'exists:nakes,id'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'hari' => ['nullable', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
        ]);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $hariList = JadwalDokter::getHariList();

        $jadwal = JadwalDokter::with(['dokter', 'poliklinik'])
            ->when($filters['dokter_id'] ?? null, fn ($query, int $id) => $query->where('dokter_id', $id))
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $id) => $query->where('poliklinik_id', $id))
            ->when($filters['hari'] ?? null, fn ($query, string $day) => $query->where('hari', $day))
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return view('pendaftaran.jadwal-praktik', compact('jadwal', 'poliklinikList', 'dokterList', 'hariList'));
    }

    public function storeJadwalPraktik(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dokter_id' => ['required', 'exists:nakes,id'],
            'poliklinik_id' => ['required', 'exists:poliklinik,id'],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ]);

        $doctorIsActiveDoctor = Nakes::whereKey($validated['dokter_id'])
            ->where('jabatan', 'dokter')
            ->where('is_active', true)
            ->exists();

        if (! $doctorIsActiveDoctor) {
            throw ValidationException::withMessages(['dokter_id' => 'Pilih tenaga kesehatan aktif dengan jabatan dokter.']);
        }

        $exists = JadwalDokter::where('dokter_id', $validated['dokter_id'])
            ->where('poliklinik_id', $validated['poliklinik_id'])
            ->where('hari', $validated['hari'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['hari' => 'Jadwal dokter untuk poli dan hari tersebut sudah tersedia.']);
        }

        JadwalDokter::create($validated);

        return back()->with('success', 'Jadwal praktik berhasil ditambahkan.');
    }

    public function destroyJadwalPraktik(JadwalDokter $jadwal): RedirectResponse
    {
        $jadwal->delete();

        return back()->with('success', 'Jadwal praktik berhasil dihapus.');
    }

    public function getDokterByPoli(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'poliklinik_id' => ['required', 'integer', 'exists:poliklinik,id'],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
        ]);

        $dokter = JadwalDokter::query()
            ->where('poliklinik_id', $filters['poliklinik_id'])
            ->where('hari', $filters['hari'])
            ->where('is_active', true)
            ->whereHas('dokter', fn ($query) => $query->where('is_active', true)->where('jabatan', 'dokter'))
            ->with('dokter:id,nama')
            ->get()
            ->pluck('dokter')
            ->filter()
            ->unique('id')
            ->values();

        return response()->json($dokter);
    }

    public function cetakAntrian(Kunjungan $kunjungan): View
    {
        $kunjungan->load(['pasien', 'poliklinik', 'dokter']);
        $antrian = Kunjungan::where('poliklinik_id', $kunjungan->poliklinik_id)
            ->whereDate('tanggal', $kunjungan->tanggal)
            ->where('status', '!=', 'batal')
            ->where(function ($query) use ($kunjungan): void {
                $query->where('created_at', '<', $kunjungan->created_at)
                    ->orWhere(function ($tieQuery) use ($kunjungan): void {
                        $tieQuery->where('created_at', $kunjungan->created_at)->where('id', '<=', $kunjungan->id);
                    });
            })
            ->count();

        return view('pendaftaran.cetak-antrian', compact('kunjungan', 'antrian'));
    }

    public function editKunjungan(Kunjungan $kunjungan): View
    {
        $kunjungan->load(['pasien', 'poliklinik', 'dokter']);
        $data = $this->registrationFormData();
        $data['kunjungan'] = $kunjungan;
        $data['hariIni'] = strtolower($kunjungan->tanggal->locale('id')->dayName);

        return view('pendaftaran.edit-kunjungan', $data);
    }

    public function updateKunjungan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        if (in_array($kunjungan->status, ['selesai', 'batal'], true)) {
            return back()->with('error', 'Kunjungan yang selesai atau batal tidak dapat diubah.');
        }

        $validated = $request->validate([
            'poliklinik_id' => ['required', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'required_if:jenis_bayar,asuransi', 'exists:asuransi,id'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->ensureDoctorIsScheduled($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $kunjungan->tanggal->locale('id')->dayName);
        $kunjungan->update($validated);

        return redirect()->route('pendaftaran.laporan-kunjungan')->with('success', 'Data kunjungan berhasil diperbarui.');
    }

    public function batalKunjungan(Kunjungan $kunjungan): RedirectResponse
    {
        if (in_array($kunjungan->status, ['selesai', 'batal'], true)) {
            return back()->with('error', 'Kunjungan yang selesai atau sudah batal tidak dapat dibatalkan kembali.');
        }

        $kunjungan->update(['status' => 'batal']);

        return back()->with('success', 'Kunjungan berhasil dibatalkan.');
    }

    /** @return array{poliklinikList: Collection, asuransiList: Collection, hariIni: string} */
    private function registrationFormData(): array
    {
        return [
            'poliklinikList' => Poliklinik::where('is_active', true)->orderBy('nama')->get(),
            'asuransiList' => Asuransi::where('is_active', true)->orderBy('nama')->get(),
            'hariIni' => strtolower(now()->locale('id')->dayName),
        ];
    }

    private function ensureDoctorIsScheduled(?int $doctorId, int $poliklinikId, string $day): void
    {
        if ($doctorId === null) {
            return;
        }

        $isScheduled = JadwalDokter::where('dokter_id', $doctorId)
            ->where('poliklinik_id', $poliklinikId)
            ->where('hari', strtolower($day))
            ->where('is_active', true)
            ->whereHas('dokter', fn ($query) => $query->where('jabatan', 'dokter')->where('is_active', true))
            ->exists();

        if (! $isScheduled) {
            throw ValidationException::withMessages(['dokter_id' => 'Dokter tidak memiliki jadwal aktif pada poli dan hari yang dipilih.']);
        }
    }

    private function generateNoRM(): string
    {
        $lastNoRm = Pasien::withTrashed()
            ->where('no_rm', 'like', 'RM-%')
            ->orderByDesc('no_rm')
            ->lockForUpdate()
            ->value('no_rm');
        $lastNumber = $lastNoRm ? (int) str_replace('RM-', '', $lastNoRm) : 0;

        return 'RM-'.str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
