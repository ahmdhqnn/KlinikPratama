<?php

namespace App\Http\Controllers\Pendaftaran;

use App\ClinicalAuditRecorder;
use App\ClinicDocumentNumber;
use App\HakLayananVerifier;
use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\PatientRegistrationService;
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
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,selesai,batal'],
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

    public function storePasienBaru(Request $request, PatientRegistrationService $registrationService): RedirectResponse
    {
        $request->mergeIfMissing(['registration_type' => 'directory']);
        $registrationType = $request->validate(['registration_type' => ['required', 'in:directory,manual']])['registration_type'];

        if ($registrationType === 'manual') {
            $data = $registrationService->validateManual($request);
            $patient = $registrationService->createManual($data, $request->user());

            return redirect()->route('pendaftaran.database-pasien')
                ->with('success', "Pasien {$patient->nama} (No. RM: {$patient->no_rm}) berhasil didaftarkan. Hak layanan belum diverifikasi; tautkan kepesertaan sebelum membuat kunjungan internal.");
        }

        $validated = $request->validate([
            'registration_type' => ['required', 'in:directory'],
            'kepesertaan_id' => ['nullable', 'required_if:registration_type,directory', 'integer', 'exists:kepesertaan,id', 'unique:pasien,kepesertaan_id'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['nullable', 'string', 'max:2000'],
            'no_asuransi' => ['nullable', 'string', 'max:50'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['dokter_id'] = $this->resolveScheduledDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], today()->toDateString());

        $pasien = DB::transaction(function () use ($validated, $request): Pasien {
            $member = app(HakLayananVerifier::class)->member((int) $validated['kepesertaan_id'], today()->toDateString());
            if (! $member->tanggal_lahir || ! $member->jenis_kelamin) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Lengkapi tanggal lahir dan jenis kelamin pada direktori kepesertaan sebelum pendaftaran pasien baru.']);
            }
            if (Pasien::withTrashed()->where('kepesertaan_id', $member->id)->exists() || ($member->nik && Pasien::withTrashed()->where('nik', $member->nik)->exists())) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Peserta sudah memiliki data pasien. Gunakan pasien terdaftar atau tautkan pasien historis terlebih dahulu.']);
            }
            $pasien = Pasien::create([
                'no_rm' => $this->generateNoRM(),
                'nama' => $member->nama,
                'nik' => $member->nik,
                'kepesertaan_id' => $member->id,
                'tempat_lahir' => $member->tempat_lahir,
                'tanggal_lahir' => $member->tanggal_lahir,
                'jenis_kelamin' => $member->jenis_kelamin,
                'golongan_darah' => $member->golongan_darah,
                'agama' => $member->agama,
                'nama_ibu' => $validated['nama_ibu'] ?? null,
                'alamat' => $member->alamat,
                'rt' => $member->rt,
                'rw' => $member->rw,
                'kelurahan' => $member->kelurahan,
                'kecamatan' => $member->kecamatan,
                'telepon' => $validated['telepon'] ?? null,
                'asuransi_id' => null,
                'no_asuransi' => null,
                'riwayat_alergi' => $validated['riwayat_alergi'] ?? null,
            ]);

            Kunjungan::create([
                'no_kunjungan' => Kunjungan::generateNomor(),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'dokter_id' => $validated['dokter_id'] ?? null,
                'asuransi_id' => null,
                'tanggal' => today(),
                'status' => 'menunggu',
                'jenis_pasien' => 'baru',
                ...app(HakLayananVerifier::class)->patient($pasien, today()->toDateString(), $request),
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
            ->latest('created_at')->latest('id')
            ->limit(10)
            ->with('kepesertaan.penanggung')->get(['id', 'no_rm', 'nama', 'nik', 'jenis_kelamin', 'tanggal_lahir', 'telepon', 'kepesertaan_id'])
            ->map(fn (Pasien $patient): array => [...$patient->only(['id', 'no_rm', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'telepon']), 'eligibilityReason' => app(HakLayananVerifier::class)->reason($patient->kepesertaan, today()->toDateString())]);

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

            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['dokter_id'] = $this->resolveScheduledDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], today()->toDateString());
        $pasien = Pasien::findOrFail($validated['pasien_id']);

        DB::transaction(function () use ($validated, $pasien, $request): void {
            $pasien = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            Kunjungan::create([
                'no_kunjungan' => Kunjungan::generateNomor(),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'dokter_id' => $validated['dokter_id'] ?? null,
                'asuransi_id' => null,
                'tanggal' => today(),
                'status' => 'menunggu',
                'jenis_pasien' => 'lama',
                ...app(HakLayananVerifier::class)->patient($pasien, today()->toDateString(), $request),
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

        $pasien = Pasien::with('kepesertaan')
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
                    'insurance' => $patient->kepesertaan ? (Kepesertaan::CATEGORIES[$patient->kepesertaan->kategori] ?? 'Internal') : 'Belum terverifikasi',
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
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,selesai,batal'],
        ]);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $selectedPoli = $filters['poliklinik_id'] ?? $poliklinikList->first()?->id;

        $kunjungan = Kunjungan::with(['pasien', 'dokter'])
            ->where('poliklinik_id', $selectedPoli)
            ->whereDate('tanggal', $filters['tanggal'] ?? today())
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('created_at')->latest('id')
            ->get();
        $role = $request->user()->role;

        return Inertia::render('pendaftaran/kunjungan-per-poli', [
            'access' => [
                'manageVisits' => in_array($role, ['admin', 'pendaftaran'], true),
                'monitorClinicalFlow' => $role === 'perawat',
            ],
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
                'actionUrl' => $role === 'perawat'
                    ? (in_array($visit->status, ['menunggu', 'screening'], true)
                        ? route('pelayanan.screening.show', $visit)
                        : ($visit->status === 'batal'
                            ? route('pelayanan.kunjungan.show', $visit)
                            : route('pelayanan.pasien.rekam-medis', $visit->pasien)))
                    : route('pelayanan.kunjungan.show', $visit),
                'actionLabel' => $role === 'perawat'
                    ? (in_array($visit->status, ['menunggu', 'screening'], true)
                        ? 'Skrining'
                        : ($visit->status === 'batal' ? 'Detail' : 'Lihat RME'))
                    : 'Detail',
                'ticketUrl' => $visit->status !== 'batal' && $request->user()->role !== 'perawat' ? route('pendaftaran.cetak-antrian', $visit) : null,
                'editUrl' => in_array($role, ['admin', 'pendaftaran'], true) && $visit->status === 'menunggu'
                    ? route('pendaftaran.edit-kunjungan', $visit)
                    : null,
                'cancelUrl' => in_array($role, ['admin', 'pendaftaran'], true) && $visit->status === 'menunggu'
                    ? route('pendaftaran.batal-kunjungan', $visit)
                    : null,
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
            'tanggal' => ['nullable', 'date'],
        ]);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $hariList = JadwalDokter::getHariList();

        $jadwal = JadwalDokter::with(['dokter', 'poliklinik'])
            ->when($filters['dokter_id'] ?? null, fn ($query, int $id) => $query->where('dokter_id', $id))
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $id) => $query->where('poliklinik_id', $id))
            ->when($filters['hari'] ?? null, fn ($query, string $day) => $query->where('hari', $day))
            ->orderByDesc('berlaku_mulai')
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return Inertia::render('pendaftaran/jadwal-praktik', [
            'canManage' => in_array($request->user()->role, ['admin', 'pendaftaran'], true),
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
            'today' => today()->toDateString(),
            'schedules' => $jadwal->map(fn (JadwalDokter $schedule): array => [
                'id' => $schedule->id,
                'doctorId' => $schedule->dokter_id,
                'clinicId' => $schedule->poliklinik_id,
                'doctor' => $schedule->dokter?->nama ?? '—',
                'clinic' => $schedule->poliklinik?->nama ?? '—',
                'day' => $schedule->hari,
                'start' => substr($schedule->jam_mulai, 0, 5),
                'end' => substr($schedule->jam_selesai, 0, 5),
                'active' => $schedule->is_active,
                'validFrom' => $schedule->berlaku_mulai?->toDateString(),
                'validUntil' => $schedule->berlaku_sampai?->toDateString(),
                'updateUrl' => route('pendaftaran.update-jadwal-praktik', $schedule),
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
            'berlaku_mulai' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'berlaku_sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:berlaku_mulai'],
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
            ->where(function ($query) use ($validated): void {
                $end = $validated['berlaku_sampai'] ?? '9999-12-31';
                $query->where(function ($period) use ($validated): void {
                    $period->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $validated['berlaku_mulai']);
                })->whereDate('berlaku_mulai', '<=', $end);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['hari' => 'Jadwal dokter untuk poli dan hari tersebut sudah tersedia.']);
        }

        JadwalDokter::create($validated);

        return back()->with('success', 'Jadwal praktik berhasil ditambahkan.');
    }

    public function updateJadwalPraktik(Request $request, JadwalDokter $jadwal): RedirectResponse
    {
        $validated = $request->validate([
            'dokter_id' => ['required', 'exists:nakes,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'berlaku_mulai' => ['required', 'date_format:Y-m-d'],
            'berlaku_sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:berlaku_mulai'],
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

        $periodEnd = $validated['berlaku_sampai'] ?? '9999-12-31';
        $overlapsAnotherSchedule = JadwalDokter::where('id', '!=', $jadwal->id)
            ->where('dokter_id', $validated['dokter_id'])
            ->where('poliklinik_id', $validated['poliklinik_id'])
            ->where('hari', $validated['hari'])
            ->where(function ($period) use ($validated): void {
                $period->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $validated['berlaku_mulai']);
            })
            ->whereDate('berlaku_mulai', '<=', $periodEnd)
            ->exists();

        if ($overlapsAnotherSchedule) {
            throw ValidationException::withMessages(['hari' => 'Jadwal dokter untuk poli dan hari tersebut sudah bertumpuk dengan jadwal lain.']);
        }

        $jadwal->update($validated);

        return back()->with('success', 'Jadwal praktik berhasil diperbarui.');
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
            'tanggal' => ['required', 'date'],
        ]);
        $date = Carbon::parse($filters['tanggal']);
        $day = strtolower($date->locale('id')->dayName);

        $dokter = JadwalDokter::query()
            ->where('poliklinik_id', $filters['poliklinik_id'])
            ->where('hari', $day)
            ->where(function ($query) use ($date): void {
                $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $date->toDateString());
            })
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
                'date' => $kunjungan->tanggal->toDateString(),
                'insuranceId' => (string) ($kunjungan->asuransi_id ?? ''),
                'paymentType' => $kunjungan->jenis_bayar,
                'patientType' => $kunjungan->jenis_pasien,
                'notes' => $kunjungan->catatan ?? '',
            ],
            'clinics' => $data['clinics'],
            'insuranceProviders' => $data['insuranceProviders'],
            'day' => $data['hariIni'],
            'doctorsUrl' => route('pelayanan.dokter.by-poli'),
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

            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['dokter_id'] = $this->resolveScheduledDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $kunjungan->tanggal->toDateString());
        DB::transaction(function () use ($kunjungan, $validated, $request): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');
            $lockedVisit->update([...$validated, ...app(HakLayananVerifier::class)->patient($lockedVisit->pasien, $lockedVisit->tanggal->toDateString(), $request)]);
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
            'doctorsUrl' => route('pendaftaran.dokter.by-poli'),
        ];
    }

    private function resolveScheduledDoctor(?int $doctorId, int $poliklinikId, string $date): ?int
    {
        $visitDate = Carbon::parse($date);
        $scheduledDoctors = JadwalDokter::query()
            ->where('poliklinik_id', $poliklinikId)
            ->where('hari', strtolower($visitDate->locale('id')->dayName))
            ->where(fn ($query) => $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $visitDate->toDateString()))
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $visitDate->toDateString()))
            ->where('is_active', true)
            ->whereHas('dokter', fn ($query) => $query->where('jabatan', 'dokter')->where('is_active', true))
            ->pluck('dokter_id')->unique()->values();

        if ($doctorId !== null && ! $scheduledDoctors->contains($doctorId)) {
            throw ValidationException::withMessages(['dokter_id' => 'Dokter tidak memiliki jadwal aktif pada poli dan hari yang dipilih.']);
        }

        if ($doctorId !== null) {
            return $doctorId;
        }

        if ($scheduledDoctors->count() === 1) {
            return (int) $scheduledDoctors->first();
        }

        if ($scheduledDoctors->count() > 1) {
            throw ValidationException::withMessages(['dokter_id' => 'Pilih salah satu dokter yang tersedia pada poliklinik dan tanggal tersebut.']);
        }

        return null;
    }

    private function generateNoRM(): string
    {
        return app(ClinicDocumentNumber::class)->next('pasien', 'RM-', 6, 'pasien', 'no_rm');
    }
}
