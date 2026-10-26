<?php

namespace App\Http\Controllers\Pendaftaran;

use App\ClinicalAuditRecorder;
use App\ClinicDocumentNumber;
use App\DateRangeFilter;
use App\DoctorAccessService;
use App\HakLayananVerifier;
use App\Http\Controllers\Controller;
use App\JadwalPraktikResolver;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\PatientRegistrationService;
use App\PerawatClinicResolver;
use App\PracticeScheduleAccess;
use App\VisitOverviewService;
use App\VisitQueryService;
use App\VisitSchedulingService;
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
    public function __construct(private JadwalPraktikResolver $schedule, private PerawatClinicResolver $perawatClinicResolver, private VisitSchedulingService $scheduling, private PracticeScheduleAccess $scheduleAccess) {}

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
            'today' => $today->toDateString(),
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()), 'href' => route('pendaftaran.laporan-kunjungan')],
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
        $visits = new VisitQueryService;
        $filters = $visits->filters($request);
        $kunjungan = $visits->filtered($filters)->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();

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
                    'patient' => $visit->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                    'doctor' => $visit->dokter?->nama ?? 'Belum diisi',
                    'date' => $visit->tanggal?->format('d/m/Y') ?? 'Belum diisi',
                    'status' => $visit->status,
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
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

    public function pendaftaranBaru(Request $request): Response
    {
        return Inertia::render('pendaftaran/pendaftaran-baru', [...$this->registrationFormData(), 'initialTab' => in_array($request->query('tab'), ['manual', 'directory', 'registered'], true) ? $request->query('tab') : 'manual']);
    }

    public function storePasienBaru(Request $request, PatientRegistrationService $registrationService): RedirectResponse
    {
        $request->mergeIfMissing(['registration_type' => 'directory']);
        $registrationType = $request->validate(['registration_type' => ['required', 'in:directory,manual']])['registration_type'];

        $manualData = $registrationType === 'manual' ? $registrationService->validateManual($request) : null;

        $validated = $request->validate([
            'registration_type' => ['required', 'in:directory,manual'],
            'kepesertaan_id' => ['exclude_unless:registration_type,directory', 'required', 'integer', 'exists:kepesertaan,id', 'unique:pasien,kepesertaan_id'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['nullable', 'string', 'max:2000'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['riwayat_alergi'] = filled($validated['riwayat_alergi'] ?? null)
            ? trim($validated['riwayat_alergi'])
            : null;
        $validated['catatan'] = filled($validated['catatan'] ?? null)
            ? trim($validated['catatan'])
            : null;

        $validated['tanggal'] = $validated['tanggal'] ?? today()->toDateString();
        $validated['dokter_id'] = $this->scheduling->resolveDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $validated['tanggal']);

        $pasien = DB::transaction(function () use ($validated, $request, $manualData, $registrationService): Pasien {
            if ($manualData !== null) {
                $pasien = $registrationService->createManual($manualData);
            } else {
                $medicalRecordNumber = $this->generateNoRM();
                $member = app(HakLayananVerifier::class)->member((int) $validated['kepesertaan_id']);
                if (! $member->tanggal_lahir || ! $member->jenis_kelamin) {
                    throw ValidationException::withMessages(['kepesertaan_id' => 'Lengkapi tanggal lahir dan jenis kelamin pada direktori kepesertaan sebelum pendaftaran pasien baru.']);
                }
                if (Pasien::withTrashed()->where('kepesertaan_id', $member->id)->exists() || ($member->nik && Pasien::withTrashed()->where('nik', $member->nik)->exists()) || ($member->nip && Pasien::withTrashed()->where('nip', $member->nip)->exists())) {
                    throw ValidationException::withMessages(['kepesertaan_id' => 'Peserta sudah memiliki data pasien. Gunakan pasien terdaftar atau tautkan pasien historis terlebih dahulu.']);
                }
                $pasien = Pasien::create([
                    'no_rm' => $medicalRecordNumber,
                    'nama' => $member->nama,
                    'nik' => $member->nik,
                    'nip' => $member->nip,
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
                    'riwayat_alergi' => $validated['riwayat_alergi'] ?? null,
                ]);
            }

            Kunjungan::create([
                'no_kunjungan' => Kunjungan::generateNomor(),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'dokter_id' => $validated['dokter_id'] ?? null,
                'tanggal' => $validated['tanggal'],
                'status' => 'menunggu',
                'jenis_pasien' => 'baru',
                ...app(HakLayananVerifier::class)->patient($pasien, $request),
                'catatan' => $validated['catatan'] ?? null,
            ]);

            return $pasien;
        });

        return redirect()->to($this->afterRegistrationUrl())
            ->with('success', "Pasien {$pasien->nama} (No. RM: {$pasien->no_rm}) berhasil didaftarkan.");
    }

    public function pendaftaranLama(): RedirectResponse
    {
        return redirect()->route('pendaftaran.pendaftaran-baru', ['tab' => 'registered']);
    }

    public function searchPasien(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $search = trim($validated['q']);

        $pasien = Pasien::query()
            ->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%");
                $query->orWhereHas('kepesertaan', fn ($members) => $members->where('nip', 'like', "%{$search}%"));
            })
            ->latest('created_at')->latest('id')
            ->limit(10)
            ->with('kepesertaan.penanggung')->get()
            ->map(fn (Pasien $patient): array => $this->patientRegistrationData($patient));

        return response()->json($pasien);
    }

    public function getPasienDetail(Pasien $pasien): JsonResponse
    {
        $pasien->loadMissing('kepesertaan.penanggung');

        return response()->json($this->patientRegistrationData($pasien));
    }

    /** @return array<string, mixed> */
    private function patientRegistrationData(Pasien $patient): array
    {
        $member = $patient->kepesertaan;

        return [
            ...$patient->only(['id', 'no_rm', 'nama', 'nik', 'telepon', 'nama_ibu']),
            'nip' => $patient->nip ?? $member?->nip,
            'tanggal_lahir' => ($member?->tanggal_lahir ?? $patient->tanggal_lahir)?->toDateString(),
            'jenis_kelamin' => $member?->jenis_kelamin ?? $patient->jenis_kelamin,
            'tempat_lahir' => $member?->tempat_lahir ?? $patient->tempat_lahir,
            'golongan_darah' => $member?->golongan_darah ?? $patient->golongan_darah,
            'agama' => $member?->agama ?? $patient->agama,
            'alamat' => $member?->alamat ?? $patient->alamat,
            'rt' => $member?->rt ?? $patient->rt,
            'rw' => $member?->rw ?? $patient->rw,
            'kelurahan' => $member?->kelurahan ?? $patient->kelurahan,
            'kecamatan' => $member?->kecamatan ?? $patient->kecamatan,
            'unit_kerja' => $member?->unit_kerja,
            'membership' => $member?->membershipLabel() ?? 'Menunggu verifikasi',
            'eligibilityReason' => app(HakLayananVerifier::class)->patientReason($patient),
        ];
    }

    public function storePasienLama(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],

            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['catatan'] = filled($validated['catatan'] ?? null)
            ? trim($validated['catatan'])
            : null;

        $validated['tanggal'] = $validated['tanggal'] ?? today()->toDateString();
        $validated['dokter_id'] = $this->scheduling->resolveDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $validated['tanggal']);
        $pasien = Pasien::findOrFail($validated['pasien_id']);

        DB::transaction(function () use ($validated, $pasien, $request): void {
            $pasien = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            $this->scheduling->ensureNoDuplicate($pasien->id, (int) $validated['poliklinik_id'], $validated['tanggal']);
            Kunjungan::create([
                'no_kunjungan' => Kunjungan::generateNomor(),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'dokter_id' => $validated['dokter_id'] ?? null,
                'tanggal' => $validated['tanggal'],
                'status' => 'menunggu',
                'jenis_pasien' => 'lama',
                ...app(HakLayananVerifier::class)->patient($pasien, $request),
                'catatan' => $validated['catatan'] ?? null,
            ]);
        });

        return redirect()->to($this->afterRegistrationUrl())
            ->with('success', "Pasien {$pasien->nama} berhasil didaftarkan untuk kunjungan baru.");
    }

    private function afterRegistrationUrl(): string
    {
        return route('pendaftaran.laporan-kunjungan');
    }

    public function databasePasien(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
        ]);

        $clinicId = $request->user()->role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $pasien = Pasien::with('kepesertaan')
            ->when($clinicId, fn ($query, int $id) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('poliklinik_id', $id)))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($patientQuery) use ($search): void {
                    $patientQuery->where('nama', 'like', "%{$search}%")
                        ->orWhere('no_rm', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%");
                });
            })
            ->when($filters['jenis_kelamin'] ?? null, fn ($query, string $sex) => $query->where('jenis_kelamin', $sex))
            ->latest()
            ->paginate(10)
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
                    'name' => $patient->nama, 'nip' => $patient->nip,
                    'gender' => $patient->jenis_kelamin,
                    'birthDate' => $patient->tanggal_lahir?->format('d/m/Y') ?? 'Belum diisi',
                    'age' => $patient->umur,
                    'phone' => $patient->telepon,
                    'membership' => $patient->kepesertaan?->membershipLabel() ?? 'Belum terverifikasi',
                    'registeredAt' => $patient->created_at?->format('d/m/Y') ?? 'Belum diisi',
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
            'poliklinik_id' => ['nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'tanggal' => ['nullable', 'date'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,selesai,batal'],
        ]);
        $role = $request->user()->role;
        $assignedClinicId = $role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $poliklinikList = $assignedClinicId
            ? Poliklinik::whereKey($assignedClinicId)->where('is_active', true)->orderBy('nama')->get()
            : Poliklinik::registrable()->orderBy('nama')->get();
        $selectedPoli = $assignedClinicId ?? ($filters['poliklinik_id'] ?? null);

        $visitsQuery = Kunjungan::query()
            ->when($selectedPoli, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
        $kunjungan = (clone $visitsQuery)->with(['pasien', 'dokter', 'poliklinik', 'pemeriksaan'])
            ->latest('created_at')->latest('id')->paginate(10)->withQueryString();
        $kunjunganTerbaru = Kunjungan::with(['pasien', 'dokter', 'poliklinik', 'pemeriksaan'])->whereDate('tanggal', today())
            ->when($assignedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->latest('tanggal')->latest('created_at')->latest('id')
            ->paginate(10, ['*'], 'recent_page')
            ->withQueryString();
        $mapVisit = fn (Kunjungan $visit): array => [
            'id' => $visit->id,
            'number' => $visit->no_kunjungan,
            'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan',
            'date' => $visit->tanggal->format('d/m/Y'),
            'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'status' => $visit->status,
            'examinationStarted' => $visit->pemeriksaan !== null,
            'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
            'actionUrl' => $role === 'perawat' && ! $visit->tanggal->isFuture()
                ? (in_array($visit->status, ['menunggu', 'screening'], true)
                    ? route('pelayanan.screening.show', $visit)
                    : ($visit->status === 'batal'
                        ? route('pelayanan.kunjungan.show', $visit)
                        : route('pelayanan.pasien.rekam-medis', $visit->pasien)))
                : route($role === 'pendaftaran' ? 'pendaftaran.kunjungan.show' : 'pelayanan.kunjungan.show', $visit),
            'actionLabel' => $role === 'perawat' && ! $visit->tanggal->isFuture()
                ? (in_array($visit->status, ['menunggu', 'screening'], true)
                    ? ($visit->status === 'screening' ? 'Lanjutkan pengkajian' : 'Mulai pengkajian')
                    : ($visit->status === 'batal' ? 'Detail' : 'Lihat RME'))
                : 'Detail',
            'ticketUrl' => $visit->status !== 'batal' && $request->user()->role !== 'perawat' ? route('pendaftaran.cetak-antrian', $visit) : null,
            'editUrl' => in_array($role, ['admin', 'pendaftaran'], true) && $visit->status === 'menunggu'
                ? route('pendaftaran.edit-kunjungan', $visit)
                : null,
            'cancelUrl' => in_array($role, ['admin', 'pendaftaran'], true) && $visit->status === 'menunggu'
                ? route('pendaftaran.batal-kunjungan', $visit)
                : null,
        ];

        return Inertia::render('pendaftaran/kunjungan-per-poli', [
            'access' => [
                'manageVisits' => in_array($role, ['admin', 'pendaftaran'], true),
                'monitorClinicalFlow' => $role === 'perawat',
                'selectClinic' => $role !== 'perawat',
            ],
            'filters' => [
                'poliklinikId' => $selectedPoli ? (int) $selectedPoli : '',
                'tanggal' => $filters['tanggal'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'stats' => [
                'total' => (clone $visitsQuery)->count(),
                'waiting' => (clone $visitsQuery)->where('status', 'menunggu')->count(),
                'screening' => (clone $visitsQuery)->where('status', 'screening')->count(),
                'examination' => (clone $visitsQuery)->where('status', 'pemeriksaan')->count(),
            ],
            'visits' => [
                'data' => $kunjungan->getCollection()->map($mapVisit)->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'recentVisits' => [
                'data' => $kunjunganTerbaru->getCollection()->map($mapVisit)->values(),
                'currentPage' => $kunjunganTerbaru->currentPage(),
                'lastPage' => $kunjunganTerbaru->lastPage(),
                'from' => $kunjunganTerbaru->firstItem(),
                'to' => $kunjunganTerbaru->lastItem(),
                'total' => $kunjunganTerbaru->total(),
                'previousUrl' => $kunjunganTerbaru->previousPageUrl(),
                'nextUrl' => $kunjunganTerbaru->nextPageUrl(),
            ],
        ]);
    }

    public function laporanTopDiagnosa(Request $request): Response
    {
        $period = DateRangeFilter::fromRequest($request, 'tanggal_mulai', 'tanggal_selesai');
        $diagnosisQuery = DB::table('diagnosa')
            ->join('pemeriksaan', 'diagnosa.pemeriksaan_id', '=', 'pemeriksaan.id')
            ->join('kunjungan', 'pemeriksaan.kunjungan_id', '=', 'kunjungan.id')
            ->select('diagnosa.kode_icd10 as kode', 'diagnosa.nama_diagnosa as nama', DB::raw('COUNT(*) as total'))
            ->when($period->from, fn ($query, string $date) => $query->whereDate('kunjungan.tanggal', '>=', $date))
            ->when($period->to, fn ($query, string $date) => $query->whereDate('kunjungan.tanggal', '<=', $date))
            ->when($request->user()->role === 'perawat', fn ($query) => $query->where('kunjungan.poliklinik_id', $this->perawatClinicResolver->clinicId($request->user())))
            ->groupBy('diagnosa.kode_icd10', 'diagnosa.nama_diagnosa');
        $summary = DB::query()->fromSub(clone $diagnosisQuery, 'diagnosis_summary')
            ->selectRaw('COUNT(*) as diagnosis_count, COALESCE(SUM(total), 0) as case_count')
            ->first();
        $topDiagnosa = (clone $diagnosisQuery)
            ->orderByDesc('total')->orderBy('kode')->paginate(10)->withQueryString();

        $totalKasus = (int) ($summary->case_count ?? 0);

        return Inertia::render('pendaftaran/laporan-top-diagnosa', [
            'filters' => [
                'tanggalMulai' => $period->from ?? '',
                'tanggalSelesai' => $period->to ?? '',
            ],
            'summary' => [
                'diagnosisCount' => (int) ($summary->diagnosis_count ?? 0),
                'caseCount' => $totalKasus,
            ],
            'diagnoses' => [
                'data' => $topDiagnosa->getCollection()->map(fn (object $diagnosis, int $index): array => [
                    'rank' => (int) $topDiagnosa->firstItem() + $index,
                    'code' => $diagnosis->kode,
                    'name' => $diagnosis->nama,
                    'count' => (int) $diagnosis->total,
                    'percentage' => $totalKasus > 0 ? round(((int) $diagnosis->total / $totalKasus) * 100, 1) : 0,
                ])->values(),
                'currentPage' => $topDiagnosa->currentPage(),
                'lastPage' => $topDiagnosa->lastPage(),
                'from' => $topDiagnosa->firstItem(),
                'to' => $topDiagnosa->lastItem(),
                'total' => $topDiagnosa->total(),
                'previousUrl' => $topDiagnosa->previousPageUrl(),
                'nextUrl' => $topDiagnosa->nextPageUrl(),
            ],
        ]);
    }

    public function jadwalPraktik(Request $request, DoctorAccessService $doctorAccess): Response
    {
        $filters = $request->validate([
            'dokter_id' => ['nullable', 'integer', 'exists:nakes,id'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'hari' => ['nullable', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'tanggal' => ['nullable', 'date'],
        ]);
        $assignedClinicId = $request->user()->role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $poliklinikList = $assignedClinicId
            ? Poliklinik::whereKey($assignedClinicId)->where('is_active', true)->orderBy('nama')->get()
            : Poliklinik::registrable()->orderBy('nama')->get();
        $canManage = $this->scheduleAccess->canManage($request->user());
        $assignedDoctorId = $request->user()->role === 'dokter' ? $doctorAccess->doctorId($request->user()) : null;
        $scheduledDoctorIds = $assignedClinicId && ! $canManage
            ? JadwalDokter::query()->where('poliklinik_id', $assignedClinicId)->select('dokter_id')
            : null;
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)
            ->when($assignedDoctorId, fn ($query, int $id) => $query->whereKey($id))
            ->when($scheduledDoctorIds, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->orderBy('nama')->get();
        $hariList = JadwalDokter::getHariList();

        $jadwal = JadwalDokter::with(['dokter', 'poliklinik'])
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', today()))
            ->when($assignedDoctorId ?? ($filters['dokter_id'] ?? null), fn ($query, int $id) => $query->where('dokter_id', $id))
            ->when($assignedClinicId, fn ($query, int $id) => $query->where('poliklinik_id', $id))
            ->when(! $assignedClinicId && ($filters['poliklinik_id'] ?? null), fn ($query) => $query->where('poliklinik_id', $filters['poliklinik_id']))
            ->when($filters['hari'] ?? null, fn ($query, string $day) => $query->where('hari', $day))
            ->orderByDesc('berlaku_mulai')
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->paginate(10, ['*'], 'schedules_page')->withQueryString();
        $holidays = DB::table('hari_libur_praktik')->where('tanggal', '>=', today()->toDateString())
            ->orderBy('tanggal')->paginate(10, ['*'], 'holidays_page')->withQueryString();
        $exceptions = DB::table('jadwal_khusus_dokter')->where('tanggal', '>=', today()->toDateString())
            ->when($assignedDoctorId, fn ($query, int $id) => $query->where('dokter_id', $id))
            ->when($assignedClinicId, fn ($query, int $id) => $query->where('poliklinik_id', $id))
            ->orderBy('tanggal')->paginate(10, ['*'], 'exceptions_page')->withQueryString();

        return Inertia::render('pendaftaran/jadwal-praktik', [
            'canManage' => $canManage,
            'canManageHolidays' => $request->user()->hasRole('admin', 'pendaftaran'),
            'canSelectClinic' => $assignedClinicId === null,
            'filters' => [
                'dokterId' => $assignedDoctorId ?? (isset($filters['dokter_id']) ? (int) $filters['dokter_id'] : ''),
                'poliklinikId' => $assignedClinicId ? (int) $assignedClinicId : (isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : ''),
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
            'holidays' => [
                'data' => $holidays->getCollection()->map(fn (object $holiday): array => [
                    'id' => $holiday->id,
                    'tanggal' => $holiday->tanggal,
                    'nama' => $holiday->nama,
                    'jenis' => $holiday->jenis,
                    'sumber' => $holiday->sumber,
                ])->values(),
                'currentPage' => $holidays->currentPage(), 'lastPage' => $holidays->lastPage(),
                'from' => $holidays->firstItem(), 'to' => $holidays->lastItem(), 'total' => $holidays->total(),
                'previousUrl' => $holidays->previousPageUrl(), 'nextUrl' => $holidays->nextPageUrl(),
            ],
            'exceptions' => [
                'data' => $exceptions->getCollection()->map(fn (object $exception): array => [
                    'id' => $exception->id,
                    'dokter_id' => $exception->dokter_id,
                    'poliklinik_id' => $exception->poliklinik_id,
                    'tanggal' => $exception->tanggal,
                    'jam_mulai' => $exception->jam_mulai,
                    'jam_selesai' => $exception->jam_selesai,
                    'is_active' => (bool) $exception->is_active,
                    'alasan' => $exception->alasan,
                ])->values(),
                'currentPage' => $exceptions->currentPage(), 'lastPage' => $exceptions->lastPage(),
                'from' => $exceptions->firstItem(), 'to' => $exceptions->lastItem(), 'total' => $exceptions->total(),
                'previousUrl' => $exceptions->previousPageUrl(), 'nextUrl' => $exceptions->nextPageUrl(),
            ],
            'schedules' => [
                'data' => $jadwal->getCollection()->map(fn (JadwalDokter $schedule): array => [
                    'id' => $schedule->id,
                    'doctorId' => $schedule->dokter_id,
                    'clinicId' => $schedule->poliklinik_id,
                    'doctor' => $schedule->dokter?->nama ?? 'Belum diisi',
                    'clinic' => $schedule->poliklinik?->nama ?? 'Belum diisi',
                    'day' => $schedule->hari,
                    'start' => substr($schedule->jam_mulai, 0, 5),
                    'end' => substr($schedule->jam_selesai, 0, 5),
                    'active' => $schedule->is_active,
                    'validFrom' => $schedule->berlaku_mulai?->toDateString(),
                    'validUntil' => $schedule->berlaku_sampai?->toDateString(),
                    'updateUrl' => route('pendaftaran.update-jadwal-praktik', $schedule),
                    'deleteUrl' => route('pendaftaran.destroy-jadwal-praktik', $schedule),
                ])->values(),
                'currentPage' => $jadwal->currentPage(), 'lastPage' => $jadwal->lastPage(),
                'from' => $jadwal->firstItem(), 'to' => $jadwal->lastItem(), 'total' => $jadwal->total(),
                'previousUrl' => $jadwal->previousPageUrl(), 'nextUrl' => $jadwal->nextPageUrl(),
            ],
        ]);
    }

    public function storeJadwalPraktik(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dokter_id' => ['required', 'exists:nakes,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'berlaku_mulai' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'berlaku_sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:berlaku_mulai'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ]);
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $validated['poliklinik_id']);

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
                })->where(fn ($start) => $start->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $end));
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
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $jadwal->poliklinik_id);

        $validated = $request->validate([
            'dokter_id' => ['required', 'exists:nakes,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'berlaku_mulai' => ['required', 'date_format:Y-m-d'],
            'berlaku_sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:berlaku_mulai'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ]);
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $validated['poliklinik_id']);

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
            ->where(fn ($start) => $start->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $periodEnd))
            ->exists();

        if ($overlapsAnotherSchedule) {
            throw ValidationException::withMessages(['hari' => 'Jadwal dokter untuk poli dan hari tersebut sudah bertumpuk dengan jadwal lain.']);
        }

        $jadwal->update($validated);

        return back()->with('success', 'Jadwal praktik berhasil diperbarui.');
    }

    public function destroyJadwalPraktik(Request $request, JadwalDokter $jadwal): RedirectResponse
    {
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $jadwal->poliklinik_id);

        $jadwal->delete();

        return back()->with('success', 'Jadwal praktik berhasil dihapus.');
    }

    public function applyAsnSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dokter_id' => ['required', Rule::exists('nakes', 'id')->where('jabatan', 'dokter')->where('is_active', true)->whereNull('deleted_at')],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
        ]);
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $data['poliklinik_id']);
        $added = DB::transaction(fn (): int => $this->schedule->applyAsnTemplate((int) $data['dokter_id'], (int) $data['poliklinik_id'], today()->toDateString()));

        return back()->with('success', "{$added} hari jadwal ASN ditambahkan; jadwal yang sudah diatur tetap dipertahankan.");
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:nasional,cuti_bersama,instansi'],
            'sumber' => ['nullable', 'string', 'max:255'],
        ]);
        DB::table('hari_libur_praktik')->upsert([[
            ...$data, 'updated_at' => now(), 'created_at' => now(),
        ]], ['tanggal'], ['nama', 'jenis', 'sumber', 'updated_at']);

        return back()->with('success', 'Hari libur praktik disimpan.');
    }

    public function destroyHoliday(int $holiday): RedirectResponse
    {
        DB::table('hari_libur_praktik')->where('id', $holiday)->delete();

        return back()->with('success', 'Hari libur praktik dihapus.');
    }

    public function storeScheduleException(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dokter_id' => ['required', Rule::exists('nakes', 'id')->where('jabatan', 'dokter')->where('is_active', true)->whereNull('deleted_at')],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'is_active' => ['required', 'boolean'],
            'jam_mulai' => ['required_if:is_active,1', 'nullable', 'date_format:H:i'],
            'jam_selesai' => ['required_if:is_active,1', 'nullable', 'date_format:H:i', 'after:jam_mulai'],
            'alasan' => ['required', 'string', 'max:255'],
        ]);
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $data['poliklinik_id']);
        if (! $data['is_active']) {
            $data['jam_mulai'] = null;
            $data['jam_selesai'] = null;
        }
        DB::table('jadwal_khusus_dokter')->upsert([[
            ...$data, 'updated_at' => now(), 'created_at' => now(),
        ]], ['dokter_id', 'poliklinik_id', 'tanggal'], ['is_active', 'jam_mulai', 'jam_selesai', 'alasan', 'updated_at']);

        return back()->with('success', 'Pengecualian jadwal praktik disimpan.');
    }

    public function destroyScheduleException(Request $request, int $exception): RedirectResponse
    {
        $scheduleException = DB::table('jadwal_khusus_dokter')->where('id', $exception)->first();
        abort_unless($scheduleException !== null, 404);
        $this->scheduleAccess->authorizeClinic($request->user(), (int) $scheduleException->poliklinik_id);

        DB::table('jadwal_khusus_dokter')->where('id', $exception)->delete();

        return back()->with('success', 'Pengecualian jadwal dihapus.');
    }

    public function getDokterByPoli(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'poliklinik_id' => ['required', 'integer', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'tanggal' => ['required', 'date'],
        ]);
        $date = Carbon::parse($filters['tanggal']);
        $dokter = $this->schedule->doctors((int) $filters['poliklinik_id'], $date);

        return response()->json($dokter);
    }

    public function getDokterTerjadwal(Request $request): JsonResponse
    {
        $filters = $request->validate(['tanggal' => ['required', 'date']]);
        $date = Carbon::parse($filters['tanggal']);
        $doctors = Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])
            ->flatMap(fn (Poliklinik $clinic) => $this->schedule->doctors($clinic->id, $date)->map(fn (Nakes $doctor): array => [
                'id' => $doctor->id,
                'nama' => $doctor->nama,
                'poliklinik_id' => $clinic->id,
                'poliklinik_nama' => $clinic->nama,
            ]))
            ->values();

        return response()->json($doctors);
    }

    public function cetakAntrian(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): Response
    {
        abort_if($kunjungan->status === 'batal', 422, 'Kunjungan yang dibatalkan tidak memiliki tiket antrian.');

        $auditRecorder->record($request, 'queue_ticket.print', $kunjungan->pasien_id, $kunjungan->id);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'pemeriksaan']);
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
                'date' => $kunjungan->tanggal->locale('id')->isoFormat('dddd, D MMMM Y'),
                'isReservation' => $kunjungan->tanggal->isFuture(),
                'patient' => $kunjungan->pasien?->nama ?? 'Belum diisi',
                'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? 'Belum diisi',
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'doctor' => $kunjungan->dokter?->nama ?? 'Belum ditentukan',
            ],
            'backUrl' => route('pendaftaran.laporan-kunjungan'),
        ]);
    }

    public function editKunjungan(Kunjungan $kunjungan): Response
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'pemeriksaan']);
        $data = $this->registrationFormData();
        $data['kunjungan'] = $kunjungan;
        $data['day'] = strtolower($kunjungan->tanggal->locale('id')->dayName);

        return Inertia::render('pendaftaran/edit-kunjungan', [
            'visit' => [
                'id' => $kunjungan->id,
                'patient' => [
                    'name' => $kunjungan->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? 'Belum diisi',
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                ],
                'clinicId' => (string) $kunjungan->poliklinik_id,
                'doctorId' => (string) ($kunjungan->dokter_id ?? ''),
                'doctor' => $kunjungan->dokter ? ['id' => $kunjungan->dokter->id, 'name' => $kunjungan->dokter->nama] : null,
                'date' => $kunjungan->tanggal->toDateString(),
                'patientType' => $kunjungan->jenis_pasien,
                'notes' => $kunjungan->catatan,
            ],
            'clinics' => $data['clinics'],
            'day' => $data['day'],
            'today' => $data['today'],
            'doctorsUrl' => $data['doctorsUrl'],
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
            'tanggal' => ['nullable', 'date_format:Y-m-d'],

            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['catatan'] = filled($validated['catatan'] ?? null)
            ? trim($validated['catatan'])
            : null;
        $validated['tanggal'] = $validated['tanggal'] ?? $kunjungan->tanggal->toDateString();
        $validated['dokter_id'] = $this->scheduling->resolveDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $validated['tanggal'], $kunjungan);
        DB::transaction(function () use ($kunjungan, $validated, $request): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');
            Pasien::whereKey($lockedVisit->pasien_id)->lockForUpdate()->firstOrFail();
            $this->scheduling->ensureNoDuplicate($lockedVisit->pasien_id, (int) $validated['poliklinik_id'], $validated['tanggal'], $lockedVisit->id);
            $lockedVisit->update([...$validated, ...app(HakLayananVerifier::class)->patient($lockedVisit->pasien, $request)]);
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

    /** @return array{clinics: array<int, array{id: int, name: string}>, today: string, day: string} */
    private function registrationFormData(): array
    {
        return [
            'clinics' => Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama', 'jenis'])
                ->map(fn (Poliklinik $clinic): array => ['id' => $clinic->id, 'name' => $clinic->nama, 'type' => $clinic->jenis])
                ->all(),
            'today' => today()->toDateString(),
            'day' => strtolower(today()->locale('id')->dayName),
            'doctorsUrl' => route('pendaftaran.dokter.terjadwal'),
        ];
    }

    private function generateNoRM(): string
    {
        return app(ClinicDocumentNumber::class)->next('pasien', 'RM-', 6, 'pasien', 'no_rm');
    }
}
