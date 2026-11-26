<?php

namespace App\Http\Controllers\Pendaftaran;

use App\ClinicalAuditRecorder;
use App\ClinicDocumentNumber;
use App\DateRangeFilter;
use App\EffectiveDiagnosisReport;
use App\Exports\DiagnosisExport;
use App\Exports\KunjunganExport;
use App\GeneralConsentService;
use App\HakLayananVerifier;
use App\Http\Controllers\Controller;
use App\InternalReferralDirectory;
use App\JadwalPraktikResolver;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\RuangPoli;
use App\Models\RujukanInternal;
use App\PatientRegistrationService;
use App\PerawatClinicResolver;
use App\PracticeScheduleAccess;
use App\VisitCancellation;
use App\VisitOverviewService;
use App\VisitQueryService;
use App\VisitSchedulingService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrationController extends Controller
{
    public function __construct(private JadwalPraktikResolver $schedule, private PerawatClinicResolver $perawatClinicResolver, private VisitSchedulingService $scheduling, private PracticeScheduleAccess $scheduleAccess) {}

    public function dashboard(): Response
    {
        $today = today();
        $stats = [
            'total_kunjungan_hari_ini' => Kunjungan::whereDate('tanggal', $today)->count(),
            'total_kunjungan' => Kunjungan::count(),
            'menunggu_screening' => Kunjungan::whereDate('tanggal', $today)->where('status', 'menunggu')->where('kontrol_pending', false)->count(),
            'sedang_pemeriksaan' => Kunjungan::whereDate('tanggal', $today)->whereIn('status', ['screening', 'pemeriksaan', 'tindakan'])->count(),
        ];

        $schedules = Nakes::where('is_active', true)->where('jabatan', 'dokter')->orderBy('nama')->get(['id', 'nama'])
            ->flatMap(fn (Nakes $doctor) => $this->schedule->doctorSchedule($doctor->id, $today)
                ->map(fn (array $schedule): array => [...$schedule, 'doctor' => $doctor->nama, 'doctorId' => $doctor->id]))
            ->sortBy(['clinic', 'start', 'doctor'])->values();
        $visits = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->whereDate('tanggal', $today)->latest('created_at')->latest('id')->paginate(3)->withQueryString();

        return Inertia::render('pendaftaran/dashboard', [
            'schedules' => $schedules,
            'visits' => [
                'data' => $visits->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id, 'number' => $visit->no_kunjungan,
                    'date' => $visit->tanggal?->format('d/m/Y') ?? 'Belum diisi',
                    'patient' => $visit->pasien?->nama ?? 'Pasien tidak tersedia',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '-',
                    'clinic' => $visit->poliklinik?->nama ?? '-', 'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan',
                    'status' => $visit->status, 'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                    'detailUrl' => route('pendaftaran.kunjungan.show', $visit),
                    'pendingReservation' => $visit->isPendingReservation(),
                    'ticketUrl' => $visit->status !== 'batal' && ! $visit->isPendingReservation()
                        ? route('pendaftaran.cetak-antrian', $visit)
                        : null,
                ])->values(),
                'currentPage' => $visits->currentPage(), 'lastPage' => $visits->lastPage(),
                'from' => $visits->firstItem(), 'to' => $visits->lastItem(), 'total' => $visits->total(),
                'previousUrl' => $visits->previousPageUrl(), 'nextUrl' => $visits->nextPageUrl(),
            ],
            'today' => $today->toDateString(),
            'followUp' => [...app(VisitOverviewService::class)->summarize(Kunjungan::query()), 'href' => route('pendaftaran.kunjungan-per-poli')],
            'stats' => [
                'visitsToday' => $stats['total_kunjungan_hari_ini'],
                'totalVisits' => $stats['total_kunjungan'],
                'waitingScreening' => $stats['menunggu_screening'],
                'inProgress' => $stats['sedang_pemeriksaan'],
            ],
        ]);
    }

    public function laporanKunjungan(Request $request): Response
    {
        $visits = new VisitQueryService;
        $filters = $visits->filters($request);
        $kunjungan = $visits->filtered($filters)
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('registered_by', $request->user()->id))
            ->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $doctorList = Nakes::query()->where('is_active', true)->where('jabatan', 'dokter')->orderBy('nama')->get(['id', 'nama']);

        return Inertia::render('pendaftaran/laporan-kunjungan', [
            'exportUrl' => route('pendaftaran.laporan-kunjungan.export', $filters),
            'personal' => ! $request->user()->isAdmin(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tanggal' => $filters['tanggal'] ?? '',
                'status' => $filters['status'] ?? '',
                'poliklinikId' => isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : '',
                'dokterId' => isset($filters['dokter_id']) ? (int) $filters['dokter_id'] : '',
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'doctors' => $doctorList->map(fn (Nakes $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->nama,
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
                    'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                    'pendingReservation' => $visit->isPendingReservation(),
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'ticketUrl' => $visit->status !== 'batal' && ! $visit->isPendingReservation() ? route('pendaftaran.cetak-antrian', $visit) : null,
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

    public function exportKunjungan(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = (new VisitQueryService)->filters($request);
        $audit->record($request, 'visit_report.export', metadata: $filters);

        return Excel::download(new KunjunganExport(
            clinicId: isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null,
            status: $filters['status'] ?? null,
            search: $filters['search'] ?? null,
            date: $filters['tanggal'] ?? null,
            registrarId: $request->user()->isAdmin() ? null : $request->user()->id,
            doctorId: isset($filters['dokter_id']) ? (int) $filters['dokter_id'] : null,
        ), 'laporan-kunjungan-'.today()->toDateString().'.xlsx');
    }

    public function rujukanInternal(Request $request, InternalReferralDirectory $directory): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'ke_poli_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:menunggu,terdaftar,dilayani,selesai,batal'],
        ]);
        $search = $filters['search'] ?? '';

        return Inertia::render('pendaftaran/rujukan-internal', [
            'search' => $search,
            'date' => $filters['tanggal'] ?? '',
            'destinationClinicId' => (string) ($filters['ke_poli_id'] ?? ''),
            'status' => $filters['status'] ?? '',
            'clinics' => Poliklinik::query()->whereIn('jenis', ['umum', 'gigi'])->orderBy('nama')->get(['id', 'nama']),
            'stats' => [
                'total' => RujukanInternal::count(),
                'waiting' => RujukanInternal::where('status', 'menunggu')->count(),
                'registered' => RujukanInternal::where('status', 'terdaftar')->count(),
                'completed' => RujukanInternal::where('status', 'selesai')->count(),
            ],
            'referrals' => $directory->paginate($search, null, null, 'pendaftaran.kunjungan.show', false,
                destinationClinicId: $request->filled('ke_poli_id') ? $request->integer('ke_poli_id') : null,
                date: $filters['tanggal'] ?? null,
                status: $filters['status'] ?? null),
        ]);
    }

    public function pendaftaranBaru(Request $request): Response
    {
        $data = $request->validate([
            'pasien_id' => ['nullable', 'integer', 'exists:pasien,id'],
            'rujukan_internal_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $referral = null;
        if (isset($data['rujukan_internal_id'])) {
            $referral = RujukanInternal::with(['kunjunganTujuanTerakhir', 'kunjungan', 'kePoli'])->findOrFail($data['rujukan_internal_id']);
            abort_unless($referral->canRegisterDestination(), 422, 'Rujukan ini tidak dapat didaftarkan.');
        }

        $patientId = $referral?->kunjungan->pasien_id ?? ($data['pasien_id'] ?? null);
        $patient = $patientId ? Pasien::with('kepesertaan.penanggung')->findOrFail($patientId) : null;
        $tab = $request->query('tab');

        return Inertia::render('pendaftaran/pendaftaran-baru', [
            ...$this->registrationFormData(),
            'generalConsent' => ['text' => GeneralConsentService::TEXT, 'version' => GeneralConsentService::VERSION],
            'initialPatient' => $patient ? $this->patientRegistrationData($patient) : null,
            'initialTab' => $referral ? 'registered' : (in_array($tab, ['manual', 'directory', 'registered'], true) ? $tab : 'manual'),
            'internalReferral' => $referral ? ['id' => $referral->id, 'number' => $referral->nomor_surat, 'clinicId' => $referral->ke_poli_id] : null,
            'referralBackUrl' => $request->user()->isAdmin() ? route('persuratan.index').'#rujukan-internal' : route('pendaftaran.rujukan-internal'),
        ]);
    }

    public function storePasienBaru(Request $request, PatientRegistrationService $registrationService, GeneralConsentService $consents): RedirectResponse
    {
        $request->mergeIfMissing(['registration_type' => 'directory']);
        $registrationType = $request->validate(['registration_type' => ['required', 'in:directory,manual']])['registration_type'];

        $manualData = $registrationType === 'manual' ? $registrationService->validateManual($request) : null;

        $validated = $request->validate([
            'registration_type' => ['required', 'in:directory,manual'],
            'kepesertaan_id' => ['exclude_unless:registration_type,directory', 'required', 'integer', 'exists:kepesertaan,id', 'unique:pasien,kepesertaan_id'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['exclude'],
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

        $confirmation = $request->validate([
            'general_consent_confirmed' => ['required', 'accepted'],
            'general_consent_version' => ['required', Rule::in([GeneralConsentService::VERSION])],
            'general_consent_relationship' => ['required', Rule::in(['patient', 'parent', 'spouse', 'guardian', 'other'])],
            'general_consent_representative' => ['exclude_if:general_consent_relationship,patient', 'required', 'string', 'min:2', 'max:150'],
            'general_consent_authority' => ['exclude_if:general_consent_relationship,patient', 'required', 'string', 'min:10', 'max:1000'],
            'satusehat_sharing_decision' => ['sometimes', 'nullable', Rule::in(['opt_in', 'opt_out', 'undocumented'])],
        ], [
            'general_consent_confirmed.*' => 'Konfirmasi persetujuan pasien sebelum mendaftarkan kunjungan pertama.',
            'general_consent_version.*' => 'Muat ulang formulir untuk membaca versi Persetujuan Umum yang berlaku.',
        ]);

        $pasien = DB::transaction(function () use ($validated, $request, $manualData, $registrationService, $consents, $confirmation): Pasien {
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
                    'unit_kerja' => $member->unit_kerja,
                    'alamat' => $member->alamat,
                    'rt' => $member->rt,
                    'rw' => $member->rw,
                    'kelurahan' => $member->kelurahan,
                    'kecamatan' => $member->kecamatan,
                    'telepon' => $validated['telepon'] ?? null,
                    'riwayat_alergi' => $validated['riwayat_alergi'] ?? null,
                ]);
            }

            $defaultRoomId = RuangPoli::query()->where('poliklinik_id', $validated['poliklinik_id'])->where('is_active', true)->orderBy('id')->value('id');
            $visit = Kunjungan::create([
                'general_consent_required' => true,
                'registered_by' => $request->user()->id,
                'no_kunjungan' => Kunjungan::generateNomor(),
                'no_antrian' => Kunjungan::nextQueueNumber((int) $validated['poliklinik_id'], $validated['tanggal']),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'ruang_poli_id' => $defaultRoomId,
                'dokter_id' => $validated['dokter_id'] ?? null,
                'tanggal' => $validated['tanggal'],
                'status' => 'menunggu',
                'jenis_pasien' => 'baru',
                ...app(HakLayananVerifier::class)->patient($pasien, $request),
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $consents->recordFirstAcceptance($request, $visit, $pasien, $confirmation);

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
            ->with(['kepesertaan.penanggung', 'latestGeneralConsent'])->get()
            ->map(fn (Pasien $patient): array => $this->patientRegistrationData($patient, verifyConsentFile: false));

        return response()->json($pasien);
    }

    public function identitySuggestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['tempat_lahir', 'kelurahan', 'kecamatan'])],
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
        ]);
        $field = $data['field'];
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $query = static fn (string $model) => $model::query()->select($field)
            ->where($field, 'like', $term.'%')
            ->when($field === 'kelurahan' && filled($data['kecamatan'] ?? null),
                fn ($query) => $query->where('kecamatan', trim($data['kecamatan'])))
            ->distinct()->orderBy($field)->limit(10)->pluck($field);
        $suggestions = $query(Pasien::class)->merge($query(Kepesertaan::class))
            ->map(fn (string $value): string => trim($value))->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))->sort()->take(10)->values();

        return response()->json($suggestions)->header('Cache-Control', 'private, no-store');
    }

    public function getPasienDetail(Pasien $pasien): JsonResponse
    {
        $pasien->loadMissing(['kepesertaan.penanggung', 'latestGeneralConsent']);

        return response()->json($this->patientRegistrationData($pasien));
    }

    /** @return array<string, mixed> */
    private function patientRegistrationData(Pasien $patient, bool $verifyConsentFile = true): array
    {
        $member = $patient->kepesertaan;
        $consent = $patient->latestGeneralConsent;
        $consentDate = $verifyConsentFile
            ? app(GeneralConsentService::class)->activeDate($consent)
            : ($consent?->decision === 'accepted' ? $consent->recorded_at?->format('d-m-Y') : null);

        return [
            ...$patient->only(['id', 'no_rm', 'nama', 'nik', 'telepon']),
            'nip' => $patient->nip ?? $member?->nip,
            'tanggal_lahir' => ($member?->tanggal_lahir ?? $patient->tanggal_lahir)?->toDateString(),
            'tanggal_lahir_tampil' => ($member?->tanggal_lahir ?? $patient->tanggal_lahir)?->locale('id')->isoFormat('D MMMM Y'),
            'umur' => ($member?->tanggal_lahir ?? $patient->tanggal_lahir)?->age,
            'jenis_kelamin' => $member?->jenis_kelamin ?? $patient->jenis_kelamin,
            'tempat_lahir' => $member?->tempat_lahir ?? $patient->tempat_lahir,
            'golongan_darah' => $member?->golongan_darah ?? $patient->golongan_darah,
            'agama' => $member?->agama ?? $patient->agama,
            'alamat' => $member?->alamat ?? $patient->alamat,
            'rt' => $member?->rt ?? $patient->rt,
            'rw' => $member?->rw ?? $patient->rw,
            'kelurahan' => $member?->kelurahan ?? $patient->kelurahan,
            'kecamatan' => $member?->kecamatan ?? $patient->kecamatan,
            'unit_kerja' => $member?->unit_kerja ?? $patient->unit_kerja,
            'membership' => $member?->membershipLabel() ?? 'Menunggu verifikasi',
            'eligibilityReason' => app(HakLayananVerifier::class)->patientReason($patient),
            'generalConsentSignedAt' => $consentDate,
        ];
    }

    public function storePasienLama(Request $request, GeneralConsentService $consents): RedirectResponse
    {
        $validated = $request->validate([
            'rujukan_internal_id' => ['nullable', 'integer', 'min:1'],
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],

            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $referralId = $validated['rujukan_internal_id'] ?? null;

        $validated['catatan'] = filled($validated['catatan'] ?? null)
            ? trim($validated['catatan'])
            : null;

        $validated['tanggal'] = $validated['tanggal'] ?? today()->toDateString();
        $pasien = Pasien::findOrFail($validated['pasien_id']);

        $visit = DB::transaction(function () use ($validated, $pasien, $request, $referralId, $consents): Kunjungan {
            $pasien = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            if ($referralId) {
                $referral = RujukanInternal::whereKey($referralId)->lockForUpdate()->firstOrFail();
                $origin = $referral->kunjungan;
                abort_unless($origin && $origin->pasien_id === $pasien->id, 404);
                if ($referral->ke_poli_id !== (int) $validated['poliklinik_id']) {
                    throw ValidationException::withMessages(['poliklinik_id' => 'Poli tujuan harus sesuai dengan rujukan.']);
                }
                if ($origin->status === 'batal' || $referral->status !== 'menunggu'
                    || $referral->kunjunganTujuan()->where('status', '!=', 'batal')->exists()) {
                    throw ValidationException::withMessages(['rujukan_internal_id' => 'Rujukan ini sudah terdaftar atau tidak dapat digunakan.']);
                }
            }

            $validated['dokter_id'] = $this->scheduling->resolveDoctor($validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $validated['tanggal']);
            $this->scheduling->ensureNoDuplicate($pasien->id, (int) $validated['poliklinik_id'], $validated['tanggal']);
            $consent = $pasien->latestGeneralConsent;
            $defaultRoomId = RuangPoli::query()->where('poliklinik_id', $validated['poliklinik_id'])->where('is_active', true)->orderBy('id')->value('id');
            $visit = new Kunjungan([
                'general_consent_required' => true,
                'registered_by' => $request->user()->id,
                'no_kunjungan' => Kunjungan::generateNomor(),
                'no_antrian' => Kunjungan::nextQueueNumber((int) $validated['poliklinik_id'], $validated['tanggal']),
                'pasien_id' => $pasien->id,
                'poliklinik_id' => $validated['poliklinik_id'],
                'ruang_poli_id' => $defaultRoomId,
                'dokter_id' => $validated['dokter_id'] ?? null,
                'tanggal' => $validated['tanggal'],
                'status' => 'menunggu',
                'jenis_pasien' => 'lama',
                'rujukan_internal_id' => $referralId,
                ...app(HakLayananVerifier::class)->patient($pasien, $request),
                'catatan' => $validated['catatan'] ?? null,
            ]);
            $visit->forceFill(['general_consent_id' => $consents->activeDate($consent) ? $consent->id : null])->save();
            if ($referralId) {
                app(ClinicalAuditRecorder::class)->record($request, 'internal_referral.register', $visit->pasien_id, $visit->id, ['referral_id' => (int) $referralId]);
            }

            return $visit;
        }, attempts: 5);

        if ($referralId) {
            return redirect()->route('pendaftaran.kunjungan.show', $visit)->with('success', 'Kunjungan poli tujuan berhasil didaftarkan.');
        }

        return redirect()->to($this->afterRegistrationUrl())
            ->with('success', "Pasien {$pasien->nama} berhasil didaftarkan untuk kunjungan baru.");
    }

    private function afterRegistrationUrl(): string
    {
        return route('pendaftaran.kunjungan-per-poli', ['tanggal' => today()->toDateString()]);
    }

    public function databasePasien(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'kategori' => ['nullable', 'in:asn,pppk,lainnya'],
        ]);

        $clinicId = $request->user()->role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $baseQuery = Pasien::query()
            ->when($clinicId, fn ($query, int $id) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('poliklinik_id', $id)));

        $pasien = (clone $baseQuery)->with('kepesertaan')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($patientQuery) use ($search): void {
                    $patientQuery->where('nama', 'like', "%{$search}%")
                        ->orWhere('no_rm', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%");
                });
            })
            ->when($filters['jenis_kelamin'] ?? null, fn ($query, string $sex) => $query->where('jenis_kelamin', $sex))
            ->when($filters['kategori'] ?? null, fn ($query, string $category) => $query->whereHas('kepesertaan', fn ($members) => $members->whereIn('kategori', Kepesertaan::persistedCategoryKeys($category))))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('pendaftaran/database-pasien', [
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'verified' => (clone $baseQuery)->whereNotNull('kepesertaan_id')->count(),
                'unverified' => (clone $baseQuery)->whereNull('kepesertaan_id')->count(),
                'registeredToday' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            ],
            'categories' => Kepesertaan::CATEGORIES,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'jenisKelamin' => $filters['jenis_kelamin'] ?? '',
                'kategori' => $filters['kategori'] ?? '',
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
                    'viewUrl' => route('pelayanan.pasien.show', $patient),
                    'editUrl' => in_array($request->user()->role, ['admin', 'pendaftaran'], true) ? route('pelayanan.pasien.edit', $patient) : null,
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
            'status' => ['nullable', 'in:'.implode(',', Kunjungan::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'dokter_id' => ['nullable', 'integer', 'exists:nakes,id'],
            'res_search' => ['nullable', 'string', 'max:100'],
            'res_poli' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'res_tanggal' => ['nullable', 'date'],
        ]);
        $role = $request->user()->role;
        $assignedClinicId = $role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $poliklinikList = $assignedClinicId
            ? Poliklinik::whereKey($assignedClinicId)->where('is_active', true)->orderBy('nama')->get()
            : Poliklinik::registrable()->orderBy('nama')->get();
        $selectedPoli = $assignedClinicId ?? ($filters['poliklinik_id'] ?? null);
        $doctorList = Nakes::query()->where('jabatan', 'dokter')->where('is_active', true)
            ->when($selectedPoli, fn ($q, int $clinicId) => $q->where('poliklinik_id', $clinicId))
            ->orderBy('nama')->get(['id', 'nama']);

        $visitsQuery = Kunjungan::query()
            ->when($selectedPoli, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date), fn ($query) => $query->where('tanggal', '<', today()->addDays(8)->toDateString()))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['dokter_id'] ?? null, fn ($query, int $doctorId) => $query->where('dokter_id', $doctorId))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($sub) => $sub
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn ($p) => $p->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))));
        $summaryQuery = Kunjungan::query()
            ->when($assignedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId));
        $kunjungan = (clone $visitsQuery)->with(['pasien', 'dokter', 'poliklinik', 'pemeriksaan'])
            ->latest('created_at')->latest('id')->paginate(10)->withQueryString();
        $kunjunganTerbaru = Kunjungan::with(['pasien', 'dokter', 'poliklinik', 'pemeriksaan'])->whereDate('tanggal', today())
            ->when($assignedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->latest('tanggal')->latest('created_at')->latest('id')
            ->paginate(10, ['*'], 'recent_page')
            ->withQueryString();
        $resClinicId = $assignedClinicId ?? ($filters['res_poli'] ?? $selectedPoli);
        $reservations = Kunjungan::query()->upcomingReservations()
            ->when($resClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->when($filters['res_tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['res_search'] ?? null, fn ($query, string $search) => $query->where(fn ($sub) => $sub
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn ($p) => $p->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->with(['pasien', 'dokter', 'poliklinik', 'pemeriksaan'])
            ->orderBy('tanggal')->orderBy('id')->paginate(10, ['*'], 'reservations_page')->withQueryString();
        $mapVisit = fn (Kunjungan $visit): array => [
            'id' => $visit->id,
            'number' => $visit->no_kunjungan,
            'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan',
            'date' => $visit->tanggal->format('d/m/Y'),
            'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'status' => $visit->status,
            'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
            'pendingReservation' => $visit->isPendingReservation(),
            'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
            'actionUrl' => $role === 'perawat' && $visit->status === 'tindakan' ? route('pelayanan.tindakan.show', $visit) : ($role === 'perawat' && ! $visit->tanggal->isFuture() && ! $visit->isPendingReservation()
                ? (in_array($visit->status, ['menunggu', 'screening'], true)
                    ? route('pelayanan.screening.show', $visit)
                    : ($visit->status === 'batal'
                        ? route('pelayanan.kunjungan.show', $visit)
                        : route('pelayanan.pasien.rekam-medis', $visit->pasien)))
                : route($role === 'pendaftaran' ? 'pendaftaran.kunjungan.show' : 'pelayanan.kunjungan.show', $visit)),
            'actionLabel' => $role === 'perawat' && $visit->status === 'tindakan' ? 'Laksanakan tindakan' : ($role === 'perawat' && ! $visit->tanggal->isFuture() && ! $visit->isPendingReservation()
                ? (in_array($visit->status, ['menunggu', 'screening'], true)
                    ? ($visit->status === 'screening' ? 'Lanjutkan pengkajian' : 'Mulai pengkajian')
                    : ($visit->status === 'batal' ? 'Detail' : 'Lihat RME'))
                : 'Detail'),
            'ticketUrl' => $visit->status !== 'batal' && ! $visit->isPendingReservation() && $request->user()->role !== 'perawat' ? route('pendaftaran.cetak-antrian', $visit) : null,
            'consentUrl' => in_array($role, ['admin', 'pendaftaran'], true) && in_array($visit->status, ['menunggu', 'screening'], true) && ! $visit->tanggal->isFuture() && ! $visit->isPendingReservation()
                ? route('general-consent.show', $visit)
                : null,
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
                'search' => $filters['search'] ?? '',
                'dokterId' => isset($filters['dokter_id']) ? (int) $filters['dokter_id'] : '',
            ],
            'reservationFilters' => [
                'search' => $filters['res_search'] ?? '',
                'poliklinikId' => isset($filters['res_poli']) ? (int) $filters['res_poli'] : '',
                'tanggal' => $filters['res_tanggal'] ?? '',
            ],
            'doctors' => $doctorList->map(fn (Nakes $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->nama,
            ])->values(),
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'stats' => [
                'total' => (clone $summaryQuery)->count(),
                'waiting' => (clone $summaryQuery)->whereDate('tanggal', '<=', today())->where('status', 'menunggu')->where('kontrol_pending', false)->count(),
                'screening' => (clone $summaryQuery)->where('status', 'screening')->count(),
                'examination' => (clone $summaryQuery)->where('status', 'pemeriksaan')->count(),
                'completedToday' => Kunjungan::whereDate('tanggal', today())->where('status', 'selesai')
                    ->when($assignedClinicId, fn ($query, int $id) => $query->where('poliklinik_id', $id))->count(),
            ],
            'reservations' => [
                'data' => $reservations->getCollection()->map($mapVisit)->values(),
                'currentPage' => $reservations->currentPage(),
                'lastPage' => $reservations->lastPage(),
                'from' => $reservations->firstItem(),
                'to' => $reservations->lastItem(),
                'total' => $reservations->total(),
                'previousUrl' => $reservations->previousPageUrl(),
                'nextUrl' => $reservations->nextPageUrl(),
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
        $diagnosisQuery = $this->diagnosisReportQuery($request, $period);
        $summary = DB::query()->fromSub(clone $diagnosisQuery, 'diagnosis_summary')
            ->selectRaw('COUNT(*) as diagnosis_count, COALESCE(SUM(total), 0) as case_count')
            ->first();
        $topDiagnosa = (clone $diagnosisQuery)
            ->orderByDesc('total')->orderBy('kode')->paginate(10)->withQueryString();

        $totalKasus = (int) ($summary->case_count ?? 0);

        return Inertia::render('pendaftaran/laporan-top-diagnosa', [
            'exportUrl' => in_array($request->user()->role, ['admin', 'pendaftaran'], true)
                ? route('pendaftaran.laporan-top-diagnosa.export', ['tanggal_mulai' => $period->from, 'tanggal_selesai' => $period->to, 'search' => $request->input('search')]) : null,
            'filters' => [
                'search' => $request->input('search', ''),
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

    public function exportTopDiagnosa(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $period = DateRangeFilter::fromRequest($request, 'tanggal_mulai', 'tanggal_selesai');
        $audit->record($request, 'diagnosis_report.export', metadata: ['from' => $period->from, 'to' => $period->to]);

        return Excel::download(new DiagnosisExport($this->diagnosisReportQuery($request, $period)),
            'laporan-diagnosis-'.today()->toDateString().'.xlsx');
    }

    private function diagnosisReportQuery(Request $request, DateRangeFilter $period): Builder
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $visits = DB::table('kunjungan')
            ->when($period->from, fn ($query, string $date) => $query->whereDate('kunjungan.tanggal', '>=', $date))
            ->when($period->to, fn ($query, string $date) => $query->whereDate('kunjungan.tanggal', '<=', $date))
            ->when($request->user()->role === 'perawat', fn ($query) => $query->where('kunjungan.poliklinik_id', $this->perawatClinicResolver->clinicId($request->user())));

        return app(EffectiveDiagnosisReport::class)->query($visits)
            ->join('pemeriksaan', 'diagnosa.pemeriksaan_id', '=', 'pemeriksaan.id')
            ->join('kunjungan', 'pemeriksaan.kunjungan_id', '=', 'kunjungan.id')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($names) => $names->where('diagnosa.kode_icd10', 'like', "%{$search}%")->orWhere('diagnosa.nama_diagnosa', 'like', "%{$search}%")))
            ->select('diagnosa.kode_icd10 as kode', 'diagnosa.nama_diagnosa as nama', DB::raw('COUNT(*) as total'))
            ->when($period->from, fn ($query, string $date) => $query->whereDate('kunjungan.tanggal', '>=', $date))
            ->when($period->to, fn ($query, string $date) => $query->whereDate('kunjungan.tanggal', '<=', $date))
            ->when($request->user()->role === 'perawat', fn ($query) => $query->where('kunjungan.poliklinik_id', $this->perawatClinicResolver->clinicId($request->user())))
            ->groupBy('diagnosa.kode_icd10', 'diagnosa.nama_diagnosa')
            ->when(in_array($request->user()->role, ['admin', 'pendaftaran'], true), fn ($query) => $query->havingRaw('COUNT(DISTINCT kunjungan.pasien_id) >= 5'));
    }

    public function jadwalPraktik(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'dokter_id' => ['nullable', 'integer', 'exists:nakes,id'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'hari' => ['nullable', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'libur_tahun' => ['nullable', 'digits:4'],
            'libur_jenis' => ['nullable', 'in:nasional,cuti_bersama,instansi'],
            'libur_search' => ['nullable', 'string', 'max:100'],
            'exc_dokter' => ['nullable', 'integer', 'exists:nakes,id'],
            'exc_poli' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'exc_tanggal' => ['nullable', 'date_format:Y-m-d'],
            'exc_status' => ['nullable', 'in:buka,tutup'],
        ]);
        $assignedClinicId = $request->user()->role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $poliklinikList = $assignedClinicId
            ? Poliklinik::whereKey($assignedClinicId)->where('is_active', true)->orderBy('nama')->get()
            : Poliklinik::registrable()->orderBy('nama')->get();
        $canManage = $this->scheduleAccess->canManage($request->user());
        $scheduledDoctorIds = $assignedClinicId && ! $canManage
            ? JadwalDokter::query()->where('poliklinik_id', $assignedClinicId)->select('dokter_id')
            : null;
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)
            ->when($scheduledDoctorIds, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->orderBy('nama')->get();
        $hariList = JadwalDokter::getHariList();

        $matchingSchedules = JadwalDokter::with(['dokter', 'poliklinik'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($names) => $names
                ->whereHas('dokter', fn ($doctors) => $doctors->where('nama', 'like', "%{$search}%"))
                ->orWhereHas('poliklinik', fn ($clinics) => $clinics->where('nama', 'like', "%{$search}%"))))
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', today()))
            ->when($filters['dokter_id'] ?? null, fn ($query, int $id) => $query->where('dokter_id', $id))
            ->when($assignedClinicId, fn ($query, int $id) => $query->where('poliklinik_id', $id))
            ->when(! $assignedClinicId && ($filters['poliklinik_id'] ?? null), fn ($query) => $query->where('poliklinik_id', $filters['poliklinik_id']))
            ->when($filters['hari'] ?? null, fn ($query, string $day) => $query->where('hari', $day))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'aktif'));
        $jadwal = (clone $matchingSchedules)->orderByDesc('berlaku_mulai')
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->paginate(10, ['*'], 'schedules_page')->withQueryString();
        $holidaysQuery = DB::table('hari_libur_praktik')
            ->when($filters['libur_tahun'] ?? null, fn ($q, string $year) => $q->whereYear('tanggal', (int) $year), fn ($q) => $q->where('tanggal', '>=', today()->toDateString()))
            ->when($filters['libur_jenis'] ?? null, fn ($q, string $type) => $q->where('jenis', $type))
            ->when($filters['libur_search'] ?? null, fn ($q, string $search) => $q->where(fn ($sub) => $sub->where('nama', 'like', "%{$search}%")->orWhere('sumber', 'like', "%{$search}%")));
        $holidays = $holidaysQuery->orderBy('tanggal')->paginate(4, ['*'], 'holidays_page')->withQueryString();
        $holidayYears = DB::table('hari_libur_praktik')->select('tanggal')->get()
            ->map(fn ($row): string => substr((string) $row->tanggal, 0, 4))
            ->filter(fn (string $y): bool => preg_match('/^\d{4}$/', $y) === 1)
            ->unique()->sortDesc()->values()->all();

        $excClinicId = $assignedClinicId ?? ($filters['exc_poli'] ?? ($filters['poliklinik_id'] ?? null));
        $excDoctorId = $filters['exc_dokter'] ?? ($filters['dokter_id'] ?? null);
        $exceptions = $canManage ? DB::table('jadwal_khusus_dokter')
            ->when($filters['exc_tanggal'] ?? null, fn ($q, string $date) => $q->whereDate('tanggal', $date), fn ($q) => $q->where('tanggal', '>=', today()->toDateString()))
            ->when($excDoctorId, fn ($query, $id) => $query->where('dokter_id', (int) $id))
            ->when($excClinicId, fn ($query, $id) => $query->where('poliklinik_id', (int) $id))
            ->when(($filters['exc_status'] ?? null) === 'buka', fn ($q) => $q->where('is_active', true))
            ->when(($filters['exc_status'] ?? null) === 'tutup', fn ($q) => $q->where('is_active', false))
            ->orderBy('tanggal')->paginate(10, ['*'], 'exceptions_page')->withQueryString() : null;

        return Inertia::render('pendaftaran/jadwal-praktik', [
            'canManage' => $canManage,
            'matchingDoctors' => (clone $matchingSchedules)->distinct()->count('dokter_id'),
            'canManageHolidays' => $request->user()->isAdmin(),
            'canSelectClinic' => $assignedClinicId === null,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'dokterId' => (isset($filters['dokter_id']) ? (int) $filters['dokter_id'] : ''),
                'poliklinikId' => $assignedClinicId ? (int) $assignedClinicId : (isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : ''),
                'hari' => $filters['hari'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'holidayFilters' => [
                'tahun' => $filters['libur_tahun'] ?? '',
                'jenis' => $filters['libur_jenis'] ?? '',
                'search' => $filters['libur_search'] ?? '',
            ],
            'holidayYears' => $holidayYears,
            'exceptionFilters' => [
                'dokterId' => isset($filters['exc_dokter']) ? (int) $filters['exc_dokter'] : '',
                'poliklinikId' => isset($filters['exc_poli']) ? (int) $filters['exc_poli'] : '',
                'tanggal' => $filters['exc_tanggal'] ?? '',
                'status' => $filters['exc_status'] ?? '',
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
            'exceptions' => $exceptions ? [
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
            ] : null,
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

        DB::transaction(function () use ($validated): void {
            $this->lockScheduleDoctor((int) $validated['dokter_id'], (int) $validated['poliklinik_id']);
            $this->ensureSchedulePeriodAvailable($validated);
            JadwalDokter::create($validated);
        }, attempts: 3);

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

        DB::transaction(function () use ($request, $jadwal, $validated): void {
            $this->lockScheduleDoctor((int) $validated['dokter_id'], (int) $validated['poliklinik_id']);
            $current = JadwalDokter::whereKey($jadwal->id)->lockForUpdate()->firstOrFail();
            $this->scheduleAccess->authorizeClinic($request->user(), (int) $current->poliklinik_id);
            $this->ensureSchedulePeriodAvailable($validated, $current->id);
            $current->update($validated);
        }, attempts: 3);

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
        $added = DB::transaction(function () use ($data): int {
            $this->lockScheduleDoctor((int) $data['dokter_id'], (int) $data['poliklinik_id']);

            return $this->schedule->applyAsnTemplate((int) $data['dokter_id'], (int) $data['poliklinik_id'], today()->toDateString());
        }, attempts: 3);

        return back()->with('success', "{$added} hari jadwal ASN ditambahkan; jadwal yang sudah diatur tetap dipertahankan.");
    }

    private function lockScheduleDoctor(int $doctorId, ?int $poliklinikId = null): void
    {
        $doctor = Nakes::whereKey($doctorId)->where('jabatan', 'dokter')
            ->where('is_active', true)->lockForUpdate()->first();

        if (! $doctor) {
            throw ValidationException::withMessages(['dokter_id' => 'Pilih tenaga kesehatan aktif dengan jabatan dokter.']);
        }

        if ($poliklinikId !== null && $doctor->poliklinik_id !== null && (int) $doctor->poliklinik_id !== $poliklinikId) {
            throw ValidationException::withMessages(['poliklinik_id' => 'Poliklinik jadwal harus sesuai dengan poliklinik penugasan dokter.']);
        }
    }

    private function ensureSchedulePeriodAvailable(array $data, ?int $exceptId = null): void
    {
        $periodEnd = $data['berlaku_sampai'] ?? '9999-12-31';
        $overlap = JadwalDokter::where('dokter_id', $data['dokter_id'])
            ->where('poliklinik_id', $data['poliklinik_id'])
            ->where('hari', $data['hari'])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $data['berlaku_mulai']))
            ->where(fn ($query) => $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $periodEnd))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['hari' => 'Jadwal dokter untuk poli dan hari tersebut bertumpuk dengan jadwal lain.']);
        }
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
        abort_if($kunjungan->isPendingReservation(), 422, 'Konfirmasikan reservasi kontrol sebelum mencetak tiket.');
        abort_if($kunjungan->status === 'batal', 422, 'Kunjungan yang dibatalkan tidak memiliki tiket antrian.');

        $auditRecorder->record($request, 'queue_ticket.print', $kunjungan->pasien_id, $kunjungan->id);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'pemeriksaan']);
        $antrian = $kunjungan->no_antrian ?? Kunjungan::where('poliklinik_id', $kunjungan->poliklinik_id)
            ->when(
                $kunjungan->getConnection()->getDriverName() === 'sqlite',
                fn ($query) => $query->whereDate('tanggal', $kunjungan->tanggal),
                fn ($query) => $query->where('tanggal', $kunjungan->tanggal->toDateString()),
            )
            ->where('status', '!=', 'batal')->where('kontrol_pending', false)
            ->where(function ($query) use ($kunjungan): void {
                $query->where('created_at', '<', $kunjungan->created_at)
                    ->orWhere(function ($tieQuery) use ($kunjungan): void {
                        $tieQuery->where('created_at', $kunjungan->created_at)->where('id', '<=', $kunjungan->id);
                    });
            })
            ->count();

        $clinic = KlinikSetting::first();

        return Inertia::render('pendaftaran/cetak-antrian', [
            'clinic' => [
                'name' => $clinic?->nama_klinik ?: 'Klinik Pratama',
                'address' => $clinic?->alamat,
                'phone' => $clinic?->telepon,
                'email' => $clinic?->email,
                'logoUrl' => $clinic?->logo ? Storage::disk('public')->url($clinic->logo) : null,
            ],
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
                'pendingReservation' => $kunjungan->isPendingReservation(),
            ],
            'clinics' => $data['clinics'],
            'day' => $data['day'],
            'today' => $data['today'],
            'doctorsUrl' => $data['doctorsUrl'],
            'updateUrl' => route('pendaftaran.update-kunjungan', $kunjungan),
            'backUrl' => route('pendaftaran.kunjungan.show', $kunjungan),
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
        $date = DB::transaction(function () use ($kunjungan, $validated, $request): string {
            Pasien::whereKey($kunjungan->pasien_id)->lockForUpdate()->firstOrFail();
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');
            if ($lockedVisit->rujukan_internal_id && $lockedVisit->poliklinik_id !== (int) $validated['poliklinik_id']) {
                throw ValidationException::withMessages(['poliklinik_id' => 'Poli tujuan terkunci sesuai rujukan internal.']);
            }
            $validated['tanggal'] ??= $lockedVisit->tanggal->toDateString();
            $validated['dokter_id'] = $this->scheduling->resolveDoctorForUpdate($lockedVisit, $validated['dokter_id'] ?? null, (int) $validated['poliklinik_id'], $validated['tanggal']);
            $this->scheduling->ensureNoDuplicate($lockedVisit->pasien_id, (int) $validated['poliklinik_id'], $validated['tanggal'], $lockedVisit->id);
            $pendingReservation = $lockedVisit->isPendingReservation();
            $slotChanged = (int) $lockedVisit->poliklinik_id !== (int) $validated['poliklinik_id']
                || $lockedVisit->tanggal?->toDateString() !== $validated['tanggal'];
            $queueNumber = ($pendingReservation || $slotChanged || $lockedVisit->no_antrian === null)
                ? Kunjungan::nextQueueNumber((int) $validated['poliklinik_id'], $validated['tanggal'])
                : $lockedVisit->no_antrian;
            $lockedVisit->update([
                ...$validated, 'kontrol_pending' => false, 'no_antrian' => $queueNumber,
                ...($pendingReservation ? ['registered_by' => $request->user()->id] : []),
                ...app(HakLayananVerifier::class)->patient($lockedVisit->pasien, $request),
            ]);
            if ($pendingReservation) {
                app(ClinicalAuditRecorder::class)->record($request, 'reservation.confirm', $lockedVisit->pasien_id, $lockedVisit->id);
            }

            return $validated['tanggal'];
        });

        return redirect()->route('pendaftaran.kunjungan-per-poli', ['tanggal' => $date])->with('success', 'Data kunjungan berhasil diperbarui.');
    }

    public function batalKunjungan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        app(VisitCancellation::class)->cancel($request, $kunjungan);

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
