<?php

namespace App\Http\Controllers\Pendaftaran;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\JadwalDokter;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function dashboard(): Response
    {
        $today = today();
        $stats = [
            'total_kunjungan_hari_ini' => Kunjungan::whereDate('tanggal', $today)->count(),
            'pasien_baru_hari_ini' => Pasien::whereDate('created_at', $today)->count(),
            'menunggu_screening' => Kunjungan::whereDate('tanggal', $today)->where('status', 'menunggu')->count(),
            'sedang_pemeriksaan' => Kunjungan::whereDate('tanggal', $today)->whereIn('status', ['screening', 'pemeriksaan'])->count(),
        ];

        return Inertia::render('pendaftaran/dashboard', [
            'stats' => [
                'visitsToday' => $stats['total_kunjungan_hari_ini'],
                'newPatientsToday' => $stats['pasien_baru_hari_ini'],
                'waitingScreening' => $stats['menunggu_screening'],
                'inProgress' => $stats['sedang_pemeriksaan'],
            ],
        ]);
    }

    public function laporanKunjungan(Request $request): Response
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

        return Inertia::render('pendaftaran/laporan-kunjungan', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tanggal' => $filters['tanggal'] ?? '',
                'status' => $filters['status'] ?? '',
                'poliklinikId' => isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : '',
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'doctor' => $visit->dokter?->nama ?? '—',
                    'date' => $visit->tanggal?->format('d/m/Y') ?? '—',
                    'status' => $visit->status,
                    'ticketUrl' => $visit->status !== 'batal' ? route('pendaftaran.cetak-antrian', $visit) : null,
                    'editUrl' => $visit->status === 'menunggu'
                        ? route('pendaftaran.edit-kunjungan', $visit)
                        : null,
                    'cancelUrl' => $visit->status === 'menunggu'
                        ? route('pendaftaran.batal-kunjungan', $visit)
                        : null,
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

    public function pendaftaranBaru(): Response
    {
        return Inertia::render('pendaftaran/pendaftaran-baru', $this->registrationFormData());
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
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
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

        return redirect()->to($this->afterRegistrationUrl())
            ->with('success', "Pasien {$pasien->nama} (No. RM: {$pasien->no_rm}) berhasil didaftarkan.");
    }

    public function pendaftaranLama(): Response
    {
        return Inertia::render('pendaftaran/pendaftaran-lama', $this->registrationFormData());
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
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
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

        return redirect()->to($this->afterRegistrationUrl())
            ->with('success', "Pasien {$pasien->nama} berhasil didaftarkan untuk kunjungan baru.");
    }

    private function afterRegistrationUrl(): string
    {
        if (KlinikSetting::pelaksanaTtv() === 'pendaftaran') {
            return route('pelayanan.screening.index');
        }

        return route('pendaftaran.laporan-kunjungan');
    }

    public function databasePasien(Request $request): Response
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

        return Inertia::render('pendaftaran/database-pasien', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'jenisKelamin' => $filters['jenis_kelamin'] ?? '',
            ],
            'patients' => [
                'data' => $pasien->getCollection()->map(fn (Pasien $patient): array => [
                    'id' => $patient->id,
                    'medicalRecordNumber' => $patient->no_rm,
                    'name' => $patient->nama,
                    'gender' => $patient->jenis_kelamin,
                    'birthDate' => $patient->tanggal_lahir?->format('d/m/Y') ?? '—',
                    'age' => $patient->umur,
                    'phone' => $patient->telepon,
                    'insurance' => $patient->asuransi?->nama ?? 'Umum',
                    'registeredAt' => $patient->created_at?->format('d/m/Y') ?? '—',
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

    public function kunjunganPerPoli(Request $request): Response
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

        return Inertia::render('pendaftaran/kunjungan-per-poli', [
            'filters' => [
                'poliklinikId' => $selectedPoli ? (int) $selectedPoli : '',
                'tanggal' => $filters['tanggal'] ?? today()->toDateString(),
                'status' => $filters['status'] ?? '',
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'stats' => [
                'total' => $kunjungan->count(),
                'waiting' => $kunjungan->where('status', 'menunggu')->count(),
                'screening' => $kunjungan->where('status', 'screening')->count(),
                'examination' => $kunjungan->where('status', 'pemeriksaan')->count(),
            ],
            'visits' => $kunjungan->map(fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'patient' => $visit->pasien?->nama ?? '—',
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan',
                'status' => $visit->status,
                'ticketUrl' => $visit->status !== 'batal' && $request->user()->role !== 'perawat' ? route('pendaftaran.cetak-antrian', $visit) : null,
            ])->values(),
        ]);
    }

    public function laporanTopDiagnosa(Request $request): Response
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

        $totalKasus = (int) $topDiagnosa->sum('total');

        return Inertia::render('pendaftaran/laporan-top-diagnosa', [
            'filters' => [
                'tanggalMulai' => $tanggalMulai->toDateString(),
                'tanggalSelesai' => $tanggalSelesai->toDateString(),
            ],
            'summary' => [
                'diagnosisCount' => $topDiagnosa->count(),
                'caseCount' => $totalKasus,
            ],
            'diagnoses' => $topDiagnosa->map(fn (object $diagnosis, int $index): array => [
                'rank' => $index + 1,
                'code' => $diagnosis->kode,
                'name' => $diagnosis->nama,
                'count' => (int) $diagnosis->total,
                'percentage' => $totalKasus > 0 ? round(((int) $diagnosis->total / $totalKasus) * 100, 1) : 0,
            ])->values(),
        ]);
    }

    public function jadwalPraktik(Request $request): Response
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

        return Inertia::render('pendaftaran/jadwal-praktik', [
            'canManage' => $request->user()->role !== 'perawat',
            'filters' => [
                'dokterId' => isset($filters['dokter_id']) ? (int) $filters['dokter_id'] : '',
                'poliklinikId' => isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : '',
                'hari' => $filters['hari'] ?? '',
            ],
            'doctors' => $dokterList->map(fn (Nakes $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->nama,
            ])->values(),
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'days' => $hariList,
            'schedules' => $jadwal->map(fn (JadwalDokter $schedule): array => [
                'id' => $schedule->id,
                'doctor' => $schedule->dokter?->nama ?? '—',
                'clinic' => $schedule->poliklinik?->nama ?? '—',
                'day' => $schedule->hari,
                'start' => substr($schedule->jam_mulai, 0, 5),
                'end' => substr($schedule->jam_selesai, 0, 5),
                'active' => $schedule->is_active,
                'deleteUrl' => route('pendaftaran.destroy-jadwal-praktik', $schedule),
            ])->values(),
        ]);
    }

    public function storeJadwalPraktik(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dokter_id' => ['required', 'exists:nakes,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
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
            'poliklinik_id' => ['required', 'integer', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
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

    public function cetakAntrian(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): Response
    {
        $auditRecorder->record($request, 'queue_ticket.print', $kunjungan->pasien_id, $kunjungan->id);
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

        return Inertia::render('pendaftaran/cetak-antrian', [
            'ticket' => [
                'queueNumber' => $antrian,
                'visitNumber' => $kunjungan->no_kunjungan,
                'date' => now()->locale('id')->isoFormat('dddd, D MMMM Y HH:mm'),
                'patient' => $kunjungan->pasien?->nama ?? '—',
                'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? '—',
                'clinic' => $kunjungan->poliklinik?->nama ?? '—',
                'doctor' => $kunjungan->dokter?->nama ?? 'Belum ditentukan',
            ],
            'backUrl' => route('pendaftaran.laporan-kunjungan'),
        ]);
    }

    public function editKunjungan(Kunjungan $kunjungan): Response
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $kunjungan->load(['pasien', 'poliklinik', 'dokter']);
        $data = $this->registrationFormData();
        $data['kunjungan'] = $kunjungan;
        $data['hariIni'] = strtolower($kunjungan->tanggal->locale('id')->dayName);

        return Inertia::render('pendaftaran/edit-kunjungan', [
            'visit' => [
                'id' => $kunjungan->id,
                'patient' => [
                    'name' => $kunjungan->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? '—',
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                ],
                'clinicId' => (string) $kunjungan->poliklinik_id,
                'doctorId' => (string) ($kunjungan->dokter_id ?? ''),
                'doctor' => $kunjungan->dokter ? ['id' => $kunjungan->dokter->id, 'name' => $kunjungan->dokter->nama] : null,
                'insuranceId' => (string) ($kunjungan->asuransi_id ?? ''),
                'paymentType' => $kunjungan->jenis_bayar,
                'patientType' => $kunjungan->jenis_pasien,
                'notes' => $kunjungan->catatan ?? '',
            ],
            'clinics' => $data['clinics'],
            'insuranceProviders' => $data['insuranceProviders'],
            'day' => $data['day'],
            'doctorsUrl' => route('pendaftaran.dokter.by-poli'),
            'updateUrl' => route('pendaftaran.update-kunjungan', $kunjungan),
            'cancelUrl' => route('pendaftaran.laporan-kunjungan'),
        ]);
    }

    public function updateKunjungan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $validated = $request->validate([
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'required_if:jenis_bayar,asuransi', 'exists:asuransi,id'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->ensureDoctorIsScheduled($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $kunjungan->tanggal->locale('id')->dayName);
        DB::transaction(function () use ($kunjungan, $validated): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');
            $lockedVisit->update($validated);
        });

        return redirect()->route('pendaftaran.laporan-kunjungan')->with('success', 'Data kunjungan berhasil diperbarui.');
    }

    public function batalKunjungan(Kunjungan $kunjungan): RedirectResponse
    {
        DB::transaction(function () use ($kunjungan): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat dibatalkan.');
            $lockedVisit->update(['status' => 'batal']);
        });

        return back()->with('success', 'Kunjungan berhasil dibatalkan.');
    }

    /** @return array{clinics: array<int, array{id: int, name: string}>, insuranceProviders: array<int, array{id: int, name: string}>, today: string, day: string} */
    private function registrationFormData(): array
    {
        return [
            'clinics' => Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])
                ->map(fn (Poliklinik $clinic): array => ['id' => $clinic->id, 'name' => $clinic->nama])
                ->all(),
            'insuranceProviders' => Asuransi::where('is_active', true)->orderBy('nama')->get(['id', 'nama'])
                ->map(fn (Asuransi $insurance): array => ['id' => $insurance->id, 'name' => $insurance->nama])
                ->all(),
            'today' => today()->toDateString(),
            'day' => strtolower(today()->locale('id')->dayName),
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
