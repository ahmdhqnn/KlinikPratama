<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAnswer;
use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\ClinicDocumentNumber;
use App\ClinicDocumentProfile;
use App\CorrespondenceService;
use App\DateRangeFilter;
use App\DentalAssessment;
use App\DoctorAccessService;
use App\Exports\PasienExport;
use App\FarmasiClinicResolver;
use App\GeneralAssessment;
use App\GeneralConsentService;
use App\HakLayananVerifier;
use App\Http\Controllers\Controller;
use App\KepesertaanRegistry;
use App\Models\FarmasiMandiri;
use App\Models\GeneralConsent;
use App\Models\Kepesertaan;
use App\Models\MedicalInformationReleaseEvent;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\PatientDocument;
use App\Models\Poliklinik;
use App\Models\RujukanInternal;
use App\Models\User;
use App\PatientProfileSections;
use App\PatientRegistrationService;
use App\PerawatClinicResolver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PasienController extends Controller
{
    public function __construct(private DoctorAccessService $doctorAccess) {}

    public function index(Request $request, ClinicalAuditRecorder $auditRecorder, PerawatClinicResolver $perawatClinicResolver): Response|RedirectResponse
    {
        if ($request->user()->role === 'farmasi') {
            return redirect()->route('farmasi.pasien', $request->only('search'));
        }
        $doctorId = $request->user()->role === 'dokter' ? $this->doctorId() : null;
        $clinicId = $request->user()->role === 'perawat'
            ? $perawatClinicResolver->clinicId($request->user())
            : null;
        $baseQuery = Pasien::query()
            ->when($doctorId, fn ($query, int $id) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('dokter_id', $id)))
            ->when($clinicId, fn ($query, int $id) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('poliklinik_id', $id)));

        $pasien = (clone $baseQuery)->with('kepesertaan')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($query) => $query
                ->where('nama', 'like', "%$s%")
                ->orWhere('no_rm', 'like', "%$s%")
                ->orWhere('nik', 'like', "%$s%")->orWhere('nip', 'like', "%$s%")
                ->orWhere('telepon', 'like', "%$s%")))
            ->when($request->kategori, fn ($q, $category) => $q->whereHas('kepesertaan', fn ($members) => $members->whereIn('kategori', Kepesertaan::persistedCategoryKeys($category))))
            ->when(in_array($request->string('jenis_kelamin')->toString(), ['L', 'P'], true), fn ($q) => $q->where('jenis_kelamin', $request->string('jenis_kelamin')->toString()))
            ->latest('created_at')->latest('id')
            ->paginate(10)
            ->withQueryString();

        $auditRecorder->record($request, 'patient_directory.view');

        return Inertia::render('pelayanan/pasien/index', [
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'verified' => (clone $baseQuery)->whereNotNull('kepesertaan_id')->count(),
                'unverified' => (clone $baseQuery)->whereNull('kepesertaan_id')->count(),
                'registeredToday' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            ],
            'permissions' => [
                'viewRme' => in_array($request->user()->role, ['dokter', 'perawat'], true),
                'managePatients' => $request->user()->role === 'admin',
            ],
            'patients' => [
                'data' => $pasien->getCollection()->map(fn (Pasien $patient) => [
                    'id' => $patient->id,
                    'medicalRecordNumber' => $patient->no_rm,
                    'name' => $patient->nama,
                    'nik' => $patient->nik, 'nip' => $patient->nip,
                    'gender' => $patient->jenis_kelamin,
                    'age' => $patient->umur,
                    'phone' => $patient->telepon,
                    'address' => $patient->alamat,
                    'membership' => $patient->kepesertaan?->membershipLabel() ?? 'Belum terverifikasi',
                    'canVerify' => $request->user()->role === 'admin' && ! $patient->kepesertaan_id,
                    'verificationUrl' => route('pelayanan.pasien.verify-membership', $patient),
                    'verificationData' => $request->user()->role === 'admin' && ! $patient->kepesertaan_id ? [
                        'name' => $patient->nama, 'nik' => $patient->nik, 'nip' => $patient->nip, 'birthPlace' => $patient->tempat_lahir,
                        'birthDate' => $patient->tanggal_lahir?->toDateString(), 'gender' => $patient->jenis_kelamin,
                        'bloodType' => $patient->golongan_darah, 'religion' => $patient->agama, 'address' => $patient->alamat,
                        'unit' => $patient->unit_kerja,
                        'rt' => $patient->rt, 'rw' => $patient->rw, 'village' => $patient->kelurahan, 'district' => $patient->kecamatan,
                    ] : null,
                ])->values(),
                'currentPage' => $pasien->currentPage(),
                'lastPage' => $pasien->lastPage(),
                'from' => $pasien->firstItem(),
                'to' => $pasien->lastItem(),
                'total' => $pasien->total(),
                'previousUrl' => $pasien->previousPageUrl(),
                'nextUrl' => $pasien->nextPageUrl(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'kategori' => $request->string('kategori')->toString(),
                'jenisKelamin' => $request->string('jenis_kelamin')->toString(),
            ],
            'categories' => Kepesertaan::CATEGORIES,
            'otherTypes' => Kepesertaan::OTHER_TYPES,
            'today' => today()->toDateString(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('pelayanan/pasien/form', [
            'backUrl' => route($request->user()?->role === 'pendaftaran' ? 'pendaftaran.database-pasien' : 'pelayanan.pasien.index'),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(Request $request, ClinicalAuditRecorder $auditRecorder, PatientRegistrationService $registrationService): RedirectResponse
    {
        $request->mergeIfMissing(['registration_type' => 'directory']);
        $registrationType = $request->validate(['registration_type' => ['required', 'in:directory,manual']])['registration_type'];

        if ($registrationType === 'manual') {
            $data = $registrationService->validateManual($request);
            $patient = $registrationService->createManual($data);
            $auditRecorder->record($request, 'patient.manual_registration', $patient->id, metadata: [
                'membership_verification' => 'pending',
                'membership_id' => $patient->kepesertaan_id,
            ]);

            return redirect()->route('pelayanan.pasien.show', $patient)
                ->with('success', 'Pasien berhasil didaftarkan. Verifikasi kepesertaan dapat dilakukan melalui database pasien.'.' No. RM: '.$patient->no_rm);
        }

        $data = $request->validate([
            'kepesertaan_id' => ['required', 'integer', 'exists:kepesertaan,id'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['exclude'],
        ]);

        unset($data['riwayat_alergi']);

        $pasien = DB::transaction(function () use ($data): Pasien {
            $data['no_rm'] = app(ClinicDocumentNumber::class)->next('pasien', 'RM-', 6, 'pasien', 'no_rm');
            $member = app(HakLayananVerifier::class)->member((int) $data['kepesertaan_id']);
            if (! $member->tanggal_lahir || ! $member->jenis_kelamin) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Lengkapi tanggal lahir dan jenis kelamin pada direktori kepesertaan sebelum pendaftaran pasien baru.']);
            }
            if (Pasien::withTrashed()->where('kepesertaan_id', $member->id)->exists()
                || ($member->nik && Pasien::withTrashed()->where('nik', $member->nik)->exists())
                || ($member->nip && Pasien::withTrashed()->where('nip', $member->nip)->exists())) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Peserta sudah memiliki data pasien. Gunakan pasien terdaftar.']);
            }

            return Pasien::create([...$data, ...$this->identityFromMembership($member)]);
        });

        return redirect()->route('pelayanan.pasien.show', $pasien)
            ->with('success', "Pasien berhasil didaftarkan dengan No. RM: {$pasien->no_rm}");
    }

    public function verifyMembership(Request $request, Pasien $pasien, KepesertaanRegistry $registry, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'required_without:nip', 'digits:16'],
            'nip' => ['nullable', 'required_without:nik', 'string', 'regex:/^[0-9]{8,30}$/'],
            'kategori' => ['required', 'in:asn,pppk,lainnya'],
            'jenis_lainnya' => ['nullable', 'string', 'max:30'],
            'status_kepegawaian' => ['required', 'in:aktif,nonaktif'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'agama' => ['required', 'string', 'max:50'],
            'alamat' => ['required', 'string', 'max:2000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'unit_kerja' => ['required', 'string', 'max:200'],
            'referensi_bukti' => ['required', 'string', 'max:255'],
            'hak_layanan' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request, $pasien, $data, $registry, $auditRecorder): void {
            $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            if ($patient->kepesertaan_id) {
                throw ValidationException::withMessages(['nik' => 'Pasien sudah terhubung dengan kepesertaan. Perbarui status melalui menu Kepesertaan.']);
            }
            if (mb_strtolower(trim($patient->nama)) !== mb_strtolower(trim($data['nama']))) {
                throw ValidationException::withMessages(['nama' => 'Nama verifikasi harus sesuai dengan nama pasien yang dipilih.']);
            }
            if ($patient->nik && $patient->nik !== ($data['nik'] ?? null)) {
                throw ValidationException::withMessages(['nik' => 'NIK verifikasi harus sesuai dengan identitas pasien.']);
            }
            if (filled($data['nik'] ?? null) && Pasien::withTrashed()
                ->where('nik', $data['nik'])->whereKeyNot($patient->id)
                ->when($patient->nik === $data['nik'], fn ($patients) => $patients->whereNull('deleted_at'))->exists()) {
                throw ValidationException::withMessages(['nik' => 'NIK sudah terhubung dengan data pasien lain. Periksa database pasien sebelum verifikasi.']);
            }

            if ($patient->nip && $patient->nip !== ($data['nip'] ?? null)) {
                throw ValidationException::withMessages(['nip' => 'NIP verifikasi harus sesuai dengan identitas pasien.']);
            }
            if (filled($data['nip'] ?? null) && Pasien::withTrashed()
                ->where('nip', $data['nip'])->whereKeyNot($patient->id)
                ->when($patient->nip === $data['nip'], fn ($patients) => $patients->whereNull('deleted_at'))->exists()) {
                throw ValidationException::withMessages(['nip' => 'NIP sudah terhubung dengan pasien lain.']);
            }

            $matches = Kepesertaan::where(function ($query) use ($data): void {
                if (! empty($data['nik'])) {
                    $query->where('nik', $data['nik']);
                }
                if (! empty($data['nip'])) {
                    $query->orWhere('nip', $data['nip']);
                }
            })->lockForUpdate()->get();
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['nik' => 'NIK dan NIP mengarah ke peserta berbeda. Periksa kembali identitas pasien.']);
            }
            $existingMembership = $matches->first();
            if ($existingMembership) {
                if (mb_strtolower(trim($existingMembership->nama)) !== mb_strtolower(trim($patient->nama))
                    || ($existingMembership->nik && $existingMembership->nik !== ($data['nik'] ?? null))
                    || ($existingMembership->nip && $existingMembership->nip !== ($data['nip'] ?? null))
                    || Pasien::withTrashed()->where('kepesertaan_id', $existingMembership->id)->where('id', '!=', $patient->id)->exists()) {
                    throw ValidationException::withMessages(['nik' => 'Identitas sudah terhubung dengan peserta lain. Periksa direktori kepesertaan.']);
                }
            }
            $membership = $registry->save([...$data, 'hak_layanan' => true], $request->user(), $existingMembership);

            $patient->update([
                'nik' => $membership->nik,
                'nip' => $membership->nip,
                'kepesertaan_id' => $membership->id,
                'tempat_lahir' => $membership->tempat_lahir,
                'tanggal_lahir' => $membership->tanggal_lahir,
                'jenis_kelamin' => $membership->jenis_kelamin,
                'golongan_darah' => $membership->golongan_darah,
                'agama' => $membership->agama,
                'unit_kerja' => $membership->unit_kerja,
                'alamat' => $membership->alamat,
                'rt' => $membership->rt,
                'rw' => $membership->rw,
                'kelurahan' => $membership->kelurahan,
                'kecamatan' => $membership->kecamatan,
            ]);
            $auditRecorder->record($request, 'patient.membership_verified', $patient->id, metadata: [
                'membership_id' => $membership->id,
                'reference' => $membership->referensi_bukti,
            ]);
        });

        return back()->with('success', 'Kepesertaan pasien berhasil diverifikasi.');
    }

    public function show(Request $request, Pasien $pasien, ClinicalAuditRecorder $auditRecorder, PerawatClinicResolver $perawatClinicResolver, PatientProfileSections $profileSections): Response
    {
        $user = $request->user();
        $user->loadMissing('nakes.poliklinik:id,nama,jenis');
        $userClinic = $user->nakes?->poliklinik;
        $role = $user->role;
        $canManage = in_array($role, ['admin', 'pendaftaran'], true);
        $canViewRme = in_array($role, ['dokter', 'perawat'], true);
        $visits = $this->authorizedVisits($request, $pasien, $perawatClinicResolver);
        $data = $profileSections->load($request, $visits);
        $showBiodata = $role !== 'farmasi' && $data['section'] === 'informasi';
        $auditRecorder->record($request, 'patient_profile.view', $pasien->id, metadata: ['section' => $data['section']]);
        $pasien->load('kepesertaan');

        $backUrl = route(match ($role) {
            'pendaftaran' => 'pendaftaran.database-pasien',
            'dokter' => 'dokter.pasien',
            'farmasi' => 'farmasi.pasien',
            default => 'pelayanan.pasien.index',
        });

        $documents = [];
        $documentsPagination = null;
        $documentsTotal = 0;
        $documentFilters = [
            'search' => $request->string('doc_search')->toString(),
            'category' => $request->string('doc_category')->toString(),
            'archived' => $request->boolean('doc_archived'),
        ];
        $documentPermissions = null;

        if ($role !== 'farmasi' && in_array($data['section'], ['informasi', 'dokumen'], true)) {
            $docQuery = PatientDocument::query()
                ->where('pasien_id', $pasien->id)
                ->when($role === 'pendaftaran', fn ($query) => $query->where('sensitivity', PatientDocument::SENSITIVITY_ADMINISTRATIVE))
                ->when($role === 'admin', fn ($query) => $query->where(fn ($scope) => $scope
                    ->where('sensitivity', PatientDocument::SENSITIVITY_ADMINISTRATIVE)
                    ->orWhere('uploaded_by', $user->id)))
                ->with(['uploadedBy:id,name', 'kunjungan:id,no_kunjungan']);

            if ($data['section'] === 'dokumen') {
                if ($documentFilters['archived']) {
                    $docQuery->whereNotNull('archived_at');
                } else {
                    $docQuery->whereNull('archived_at');
                }
                if ($documentFilters['search'] !== '') {
                    $docSearch = $documentFilters['search'];
                    $docQuery->where(function ($query) use ($docSearch): void {
                        $query->where('title', 'like', "%{$docSearch}%")
                            ->orWhere('original_name', 'like', "%{$docSearch}%")
                            ->orWhere('issuer_facility', 'like', "%{$docSearch}%")
                            ->orWhere('notes', 'like', "%{$docSearch}%");
                    });
                }
                if ($documentFilters['category'] !== '' && array_key_exists($documentFilters['category'], PatientDocument::CATEGORIES)) {
                    $docQuery->where('category', $documentFilters['category']);
                }
                $docPaginator = $docQuery->latest('document_date')->latest('id')
                    ->paginate(10)
                    ->appends($request->only(['bagian', 'doc_search', 'doc_category', 'doc_archived']));
                $documentsTotal = $docPaginator->total();
                $documents = $docPaginator->getCollection()->map(fn (PatientDocument $doc): array => $this->serializeDocument($user, $pasien, $doc))->values()->all();
                $documentsPagination = [
                    'currentPage' => $docPaginator->currentPage(),
                    'lastPage' => $docPaginator->lastPage(),
                    'from' => $docPaginator->firstItem(),
                    'to' => $docPaginator->lastItem(),
                    'total' => $docPaginator->total(),
                    'previousUrl' => $docPaginator->previousPageUrl(),
                    'nextUrl' => $docPaginator->nextPageUrl(),
                ];
            } else {
                $docQuery->whereNull('archived_at');
                $documentsTotal = (clone $docQuery)->count();
                $documents = $docQuery->latest('document_date')->latest('id')->limit(5)->get()
                    ->map(fn (PatientDocument $doc): array => $this->serializeDocument($user, $pasien, $doc))->values()->all();
            }

            $documentPermissions = $this->documentPermissionsPayload($request, $user, $pasien, $perawatClinicResolver);
        }

        $clinicalNotes = [];
        if ($canViewRme && $data['section'] === 'informasi') {
            $clinicalNotes = $this->authorizedVisits($request, $pasien, $perawatClinicResolver)
                ->whereHas('pemeriksaan')
                ->with([
                    'poliklinik:id,nama',
                    'dokter:id,nama',
                    'pemeriksaan.diagnosa',
                    'screening:id,kunjungan_id,keluhan',
                ])
                ->latest('tanggal')->latest('id')->limit(4)->get()
                ->map(function ($visit) use ($pasien): array {
                    $primaryDiag = $visit->pemeriksaan?->diagnosa?->firstWhere('jenis', 'utama')
                        ?? $visit->pemeriksaan?->diagnosa?->first();

                    return [
                        'visitId' => $visit->id,
                        'visitNumber' => $visit->no_kunjungan,
                        'date' => $visit->tanggal?->locale('id')->isoFormat('D MMM Y'),
                        'clinic' => $visit->poliklinik?->nama ?? 'Belum dicatat',
                        'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan',
                        'title' => $primaryDiag
                            ? trim(($primaryDiag->kode_icd10 ? $primaryDiag->kode_icd10.' — ' : '').$primaryDiag->nama_diagnosa)
                            : ($visit->screening?->keluhan ?: ($visit->pemeriksaan?->anamnesis ?: 'Catatan pemeriksaan')),
                        'signed' => $visit->pemeriksaan?->signed_at !== null,
                        'detailUrl' => route('pelayanan.pasien.rekam-medis', [$pasien, 'kunjungan_id' => $visit->id]),
                    ];
                })->values()->all();
        }

        $adminSummary = null;
        if ($canManage && $data['section'] === 'informasi') {
            $latestVisit = $pasien->kunjungan()->latest('tanggal')->latest('id')->first();
            $consentSummary = $latestVisit ? app(GeneralConsentService::class)->summary($latestVisit) : null;
            $openReferrals = RujukanInternal::query()
                ->whereHas('kunjungan', fn ($q) => $q->where('pasien_id', $pasien->id))
                ->where('status', 'menunggu')
                ->count();
            $releaseCount = MedicalInformationReleaseEvent::query()
                ->whereHas('kunjungan', fn ($q) => $q->where('pasien_id', $pasien->id))
                ->count();
            $adminSummary = [
                'consentStatus' => $consentSummary['status'] ?? 'undocumented',
                'consentRecordedAt' => $consentSummary['recordedAt'] ?? null,
                'consentUrl' => $latestVisit ? route('general-consent.show', [$latestVisit, 'asal' => 'profil']) : null,
                'openReferrals' => $openReferrals,
                'referralsUrl' => route('pelayanan.pasien.show', [$pasien, 'bagian' => 'rujukan']),
                'releaseCount' => $releaseCount,
                'releasesUrl' => $role === 'pendaftaran' ? route('pelayanan.pasien.show', [$pasien, 'bagian' => 'pelepasan']) : null,
            ];
        }

        return Inertia::render('pelayanan/pasien/show', [
            'breadcrumbs' => [
                ['label' => 'Daftar pasien', 'href' => $backUrl],
                ['label' => $pasien->nama],
            ],
            'backUrl' => $backUrl,
            'editUrl' => $canManage ? route('pelayanan.pasien.edit', $pasien) : null,
            'openVisitUrl' => $canManage ? route('pendaftaran.pendaftaran-baru', ['tab' => 'registered', 'pasien_id' => $pasien->id]) : null,
            'recordUrl' => $canViewRme ? route('pelayanan.pasien.rekam-medis', $pasien) : null,
            'section' => $data['section'],
            'menu' => $data['menu'],
            'pagination' => $data['pagination'],
            'directRecords' => $data['directRecords'],
            'generalConsent' => $data['generalConsent'] ?? null,
            'visitTab' => $data['visitTab'],
            'upcomingCount' => $data['upcomingCount'],
            'visitFilters' => $data['visitFilters'],
            'visitFilterOptions' => $data['visitFilterOptions'],
            'documents' => $documents,
            'documentsTotal' => $documentsTotal,
            'documentsPagination' => $documentsPagination,
            'documentFilters' => $documentFilters,
            'documentPermissions' => $documentPermissions,
            'clinicalNotes' => $clinicalNotes,
            'adminSummary' => $adminSummary,
            'permissions' => [
                'viewRme' => $canViewRme,
                'editPatient' => $canManage,
                'openVisit' => $canManage,
                'isPharmacy' => $role === 'farmasi',
                'role' => $role,
                'clinicType' => $userClinic?->jenis,
                'clinicName' => $userClinic?->nama,
            ],
            'patient' => [
                'id' => $pasien->id,
                'medicalRecordNumber' => $pasien->no_rm,
                'name' => $pasien->nama,
                'birthDate' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->locale('id')->isoFormat('D MMMM Y'),
                'gender' => $pasien->kepesertaan?->jenis_kelamin ?? $pasien->jenis_kelamin,
                'age' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->age,
                ...($showBiodata ? [
                    'nik' => $pasien->nik,
                    'bloodType' => $pasien->kepesertaan?->golongan_darah ?? $pasien->golongan_darah,
                    'birthPlace' => $pasien->kepesertaan?->tempat_lahir ?? $pasien->tempat_lahir,
                    'religion' => $pasien->kepesertaan?->agama ?? $pasien->agama,
                    'rt' => $pasien->kepesertaan?->rt ?? $pasien->rt,
                    'rw' => $pasien->kepesertaan?->rw ?? $pasien->rw,
                    'village' => $pasien->kepesertaan?->kelurahan ?? $pasien->kelurahan,
                    'district' => $pasien->kepesertaan?->kecamatan ?? $pasien->kecamatan,
                    'postalCode' => $pasien->kode_pos,
                    'unit' => $pasien->kepesertaan?->unit_kerja ?? $pasien->unit_kerja,
                    'memberNip' => $pasien->nip ?? $pasien->kepesertaan?->nip,
                    'phone' => $pasien->telepon,
                    'guardianName' => $pasien->nama_wali,
                    'guardianPhone' => $pasien->telepon_wali,
                    'address' => $pasien->kepesertaan?->alamat ?? $pasien->alamat,
                    'membership' => $pasien->kepesertaan?->membershipLabel() ?? 'Belum terverifikasi',
                    'membershipActive' => (bool) $pasien->kepesertaan?->hak_layanan,
                ] : []),
                'allergies' => $canViewRme || $role === 'farmasi'
                    ? ($pasien->riwayat_alergi ?: 'Belum dicatat')
                    : null,
                'visits' => $data['visits'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function documentPermissionsPayload(Request $request, User $user, Pasien $pasien, PerawatClinicResolver $perawatClinicResolver): array
    {
        $user->loadMissing('nakes.poliklinik:id,nama,jenis');
        $clinic = $user->nakes?->poliklinik;
        $clinicType = $clinic?->jenis;
        $clinicName = $clinic?->nama;

        $allowedCategories = collect(PatientDocument::CATEGORIES)
            ->filter(function (array $meta, string $key) use ($user, $pasien, $clinicType): bool {
                if (! $user->can('upload', [PatientDocument::class, $pasien, $key])) {
                    return false;
                }

                if (in_array($user->role, ['dokter', 'perawat'], true)
                    && $clinicType === 'umum'
                    && $key === PatientDocument::CATEGORY_RADIOLOGI_GIGI) {
                    return false;
                }

                return true;
            })
            ->sortBy(function (array $meta, string $key) use ($clinicType): int {
                if ($clinicType === 'gigi') {
                    return match ($key) {
                        PatientDocument::CATEGORY_RADIOLOGI_GIGI => 0,
                        PatientDocument::CATEGORY_RME_LAMA => 1,
                        PatientDocument::CATEGORY_RESUME_MEDIS => 2,
                        PatientDocument::CATEGORY_SURAT_RUJUKAN => 3,
                        PatientDocument::CATEGORY_HASIL_LAB => 4,
                        default => 10,
                    };
                }

                return match ($key) {
                    PatientDocument::CATEGORY_RME_LAMA => 0,
                    PatientDocument::CATEGORY_HASIL_LAB => 1,
                    PatientDocument::CATEGORY_RADIOLOGI_UMUM => 2,
                    PatientDocument::CATEGORY_RESUME_MEDIS => 3,
                    PatientDocument::CATEGORY_SURAT_RUJUKAN => 4,
                    PatientDocument::CATEGORY_HASIL_MCU => 5,
                    default => 10,
                };
            })
            ->map(fn (array $meta, string $key): array => [
                'value' => $key,
                'label' => $meta['label'],
                'sensitivity' => $meta['sensitivity'],
            ])->values()->all();

        $visibleCategories = collect(PatientDocument::CATEGORIES)
            ->filter(function (array $meta, string $key) use ($user): bool {
                if ($user->role === 'pendaftaran') {
                    return $meta['sensitivity'] === PatientDocument::SENSITIVITY_ADMINISTRATIVE;
                }

                if ($user->role === 'admin') {
                    return in_array($key, [
                        PatientDocument::CATEGORY_DOKUMEN_IDENTITAS,
                        PatientDocument::CATEGORY_RME_LAMA,
                        PatientDocument::CATEGORY_SURAT_RUJUKAN,
                    ], true);
                }

                return true;
            })
            ->map(fn (array $meta, string $key): array => [
                'value' => $key,
                'label' => $meta['label'],
            ])->values()->all();

        $visitOptions = $this->authorizedVisits($request, $pasien, $perawatClinicResolver)
            ->with('poliklinik:id,nama')
            ->latest('tanggal')->latest('id')->limit(15)->get()
            ->map(fn ($visit): array => [
                'id' => $visit->id,
                'label' => trim(($visit->no_kunjungan ?? '').' · '.($visit->tanggal?->format('d/m/Y') ?? '').' · '.($visit->poliklinik?->nama ?? '')),
            ])->values()->all();

        return [
            'canUpload' => $allowedCategories !== [],
            'role' => $user->role,
            'clinicType' => $clinicType,
            'clinicName' => $clinicName,
            'allowedCategories' => $allowedCategories,
            'allCategories' => $visibleCategories,
            'uploadUrl' => route('pelayanan.pasien.documents.store', $pasien),
            'visitOptions' => $visitOptions,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDocument(User $user, Pasien $pasien, PatientDocument $doc): array
    {
        $canPreview = $user->can('preview', $doc);
        $canDownload = $user->can('download', $doc);
        $canArchive = $doc->archived_at === null && $user->can('archive', $doc);

        return [
            'id' => $doc->id,
            'uuid' => $doc->uuid,
            'title' => $doc->title,
            'category' => $doc->category,
            'categoryLabel' => $doc->categoryLabel(),
            'sensitivity' => $doc->sensitivity,
            'documentDate' => $doc->document_date?->locale('id')->isoFormat('D MMM Y'),
            'issuerFacility' => $doc->issuer_facility,
            'toothFdi' => $doc->tooth_fdi,
            'visitNumber' => $doc->kunjungan?->no_kunjungan,
            'originalName' => $doc->original_name,
            'mimeType' => $doc->mime_type,
            'fileSize' => $doc->file_size,
            'notes' => $doc->notes,
            'uploadedBy' => $doc->uploadedBy?->name,
            'uploadedAt' => $doc->created_at?->locale('id')->isoFormat('D MMM Y, HH:mm'),
            'archivedAt' => $doc->archived_at?->locale('id')->isoFormat('D MMM Y, HH:mm'),
            'archiveReason' => $doc->archive_reason,
            'previewUrl' => $canPreview ? route('pelayanan.pasien.documents.preview', [$pasien, $doc]) : null,
            'downloadUrl' => $canDownload ? route('pelayanan.pasien.documents.download', [$pasien, $doc]) : null,
            'archiveUrl' => $canArchive ? route('pelayanan.pasien.documents.archive', [$pasien, $doc]) : null,
            'canPreview' => $canPreview,
            'canDownload' => $canDownload,
            'canArchive' => $canArchive,
        ];
    }

    public function edit(Pasien $pasien): Response
    {
        $pasien->load('kepesertaan');

        return Inertia::render('pelayanan/pasien/form', [
            'backUrl' => route('pelayanan.pasien.show', $pasien),
            'patient' => [
                'id' => $pasien->id,
                'name' => $pasien->nama,
                'nik' => $pasien->nik,
                'birthDate' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->toDateString(),
                'gender' => $pasien->kepesertaan?->jenis_kelamin ?? $pasien->jenis_kelamin,
                'bloodType' => $pasien->kepesertaan?->golongan_darah ?? $pasien->golongan_darah,
                'religion' => $pasien->kepesertaan?->agama ?? $pasien->agama,
                'birthPlace' => $pasien->kepesertaan?->tempat_lahir ?? $pasien->tempat_lahir,
                'rt' => $pasien->kepesertaan?->rt ?? $pasien->rt,
                'rw' => $pasien->kepesertaan?->rw ?? $pasien->rw,
                'village' => $pasien->kepesertaan?->kelurahan ?? $pasien->kelurahan,
                'district' => $pasien->kepesertaan?->kecamatan ?? $pasien->kecamatan,
                'unit' => $pasien->kepesertaan?->unit_kerja ?? $pasien->unit_kerja,
                'memberNip' => $pasien->nip ?? $pasien->kepesertaan?->nip,
                'phone' => $pasien->telepon,
                'address' => $pasien->kepesertaan?->alamat ?? $pasien->alamat,
                'membershipId' => $pasien->kepesertaan_id,
            ],
            'today' => today()->toDateString(),
        ]);
    }

    public function update(Request $request, Pasien $pasien, PatientRegistrationService $registrationService): RedirectResponse
    {
        if ($request->user()->role === 'pendaftaran' || $request->input('registration_type') === 'manual') {
            $registrationService->updateIdentity($request, $pasien);

            return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
        }

        if (! $pasien->kepesertaan_id && ! $request->filled('kepesertaan_id')) {
            $data = $registrationService->validateManual($request, includeClinicalHistory: false);
            DB::transaction(function () use ($request, $pasien, $data): void {
                $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
                if ($patient->kepesertaan_id) {
                    throw ValidationException::withMessages(['nama' => 'Pasien sudah diverifikasi. Muat ulang untuk memperbarui data kepesertaan.']);
                }
                foreach (['nik', 'nip'] as $field) {
                    if (filled($data[$field] ?? null) && (string) $data[$field] !== (string) $patient->getAttribute($field)
                        && Pasien::withTrashed()->where($field, $data[$field])->whereKeyNot($patient->id)->exists()) {
                        throw ValidationException::withMessages([$field => strtoupper($field).' sudah terhubung dengan pasien lain.']);
                    }
                }
                $patient->fill($data);
                $changedFields = array_keys($patient->getDirty());
                $patient->save();
                app(ClinicalAuditRecorder::class)->record($request, 'patient.identity_updated', $patient->id, metadata: ['fields' => $changedFields]);
            });

            return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
        }

        $data = $request->validate([
            'kepesertaan_id' => ['required', 'integer', 'exists:kepesertaan,id'],
            'telepon' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($request, $data, $pasien): void {
            $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            $member = Kepesertaan::whereKey($data['kepesertaan_id'])->lockForUpdate()->firstOrFail();
            if (($patient->nik && $member->nik !== $patient->nik)
                || ($patient->nip && $member->nip !== $patient->nip)
                || (! $member->nik && mb_strtolower($member->nama) !== mb_strtolower($patient->nama))
                || ((int) $patient->kepesertaan_id !== $member->id
                    && Pasien::withTrashed()->where('kepesertaan_id', $member->id)->whereKeyNot($patient->id)->exists())) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Identitas atau tautan peserta tidak sesuai pasien ini.']);
            }
            if (! $member->tanggal_lahir || ! $member->jenis_kelamin) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Lengkapi tanggal lahir dan jenis kelamin pada direktori kepesertaan sebelum memperbarui data pasien.']);
            }
            $patient->fill([...$data, ...$this->identityFromMembership($member)]);
            $changedFields = array_keys($patient->getDirty());
            $patient->save();
            app(ClinicalAuditRecorder::class)->record($request, 'patient.identity_updated', $patient->id,
                metadata: ['fields' => $changedFields, 'membership_id' => $patient->kepesertaan_id]);
        });

        return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(Request $request, Pasien $pasien, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $pasien, $audit): void {
            $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            abort_if(
                $this->hasPatientHistory($patient),
                409,
                'Pasien dengan riwayat pelayanan tidak dapat dihapus.',
            );

            $patient->delete();
            $audit->record($request, 'patient.archived', $patient->id);
        });

        return redirect()->route('pelayanan.pasien.index')->with('success', 'Data pasien berhasil dihapus.');
    }

    public function rekamMedis(Request $request, Pasien $pasien, ClinicalAuditRecorder $auditRecorder, ClinicalNoteRecorder $noteRecorder, PerawatClinicResolver $perawatClinicResolver, CorrespondenceService $correspondence): \Symfony\Component\HttpFoundation\Response
    {
        $authorizedVisits = $this->authorizedVisits($request, $pasien, $perawatClinicResolver);
        $pasien->load('kepesertaan');
        $filters = $request->validate([
            'kunjungan_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,tindakan,farmasi,selesai,batal'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ]);

        DateRangeFilter::fromRequest($request);
        $visitQuery = (clone $authorizedVisits)
            ->when($filters['kunjungan_id'] ?? null, fn ($visits, $id) => $visits->where('kunjungan.id', $id))
            ->when($filters['poliklinik_id'] ?? null, fn ($visits, $clinicId) => $visits->where('poliklinik_id', $clinicId))
            ->when($filters['status'] ?? null, fn ($visits, $status) => $visits->where('status', $status))
            ->when($filters['dari'] ?? null, fn ($visits, $date) => $visits->whereDate('tanggal', '>=', $date))
            ->when($filters['sampai'] ?? null, fn ($visits, $date) => $visits->whereDate('tanggal', '<=', $date))
            ->when($filters['search'] ?? null, function ($visits, $search): void {
                $term = '%'.trim($search).'%';
                $visits->where(function ($query) use ($term): void {
                    $query->where('no_kunjungan', 'like', $term)
                        ->orWhereHas('poliklinik', fn ($clinic) => $clinic->where('nama', 'like', $term))
                        ->orWhereHas('dokter', fn ($doctor) => $doctor->where('nama', 'like', $term))
                        ->orWhereHas('screening', fn ($screening) => $screening->where('keluhan', 'like', $term))
                        ->orWhereHas('pemeriksaan', function ($examination) use ($term): void {
                            $examination->where('anamnesis', 'like', $term)
                                ->orWhere('riwayat_penyakit_sekarang', 'like', $term)
                                ->orWhereHas('diagnosa', fn ($diagnosis) => $diagnosis
                                    ->where('kode_icd10', 'like', $term)
                                    ->orWhere('nama_diagnosa', 'like', $term));
                        });
                });
            });

        $clinics = Poliklinik::query()
            ->whereIn('id', (clone $authorizedVisits)->select('poliklinik_id')->distinct())
            ->orderBy('nama')
            ->get(['id', 'nama']);
        $visits = $visitQuery->with([
            'poliklinik:id,nama,jenis',
            'dokter:id,nama',
            'screening.recordedBy', 'screening.petugas',
            'pemeriksaan.diagnosa',
            'pemeriksaan.signedBy',
            'pemeriksaan.clinicalNoteVersions.actor',
            'odontogramFindings',
            'resep.resepObat',
            'tindakanKunjungan.tindakan:id,nama',
            'tindakanKunjungan.performedBy:id,name',
            'screening.submittedBy', 'suratMedis', 'rujukanInternal',
        ])->latest('tanggal')->latest('id')->paginate(10)->withQueryString();
        $openedVisitId = isset($filters['kunjungan_id']) ? $visits->getCollection()->first()?->id : null;
        $auditRecorder->record($request, 'medical_record.view', $pasien->id, $openedVisitId);
        $backUrl = route('pelayanan.pasien.show', $pasien);
        $referer = $request->headers->get('referer');
        $refererParts = $referer ? parse_url($referer) : false;
        $refererPath = is_array($refererParts) ? ($refererParts['path'] ?? '/') : null;
        $refererScheme = is_array($refererParts) ? ($refererParts['scheme'] ?? null) : null;
        $refererPort = is_array($refererParts) ? ($refererParts['port'] ?? ($refererScheme === 'https' ? 443 : 80)) : null;

        if (is_array($refererParts)
            && in_array($refererScheme, ['http', 'https'], true)
            && ($refererParts['host'] ?? null) === $request->getHost()
            && $refererPort === $request->getPort()
            && is_string($refererPath)
            && preg_match('#^/(pelayanan|dokter|perawat|admin|pendaftaran)(/[A-Za-z0-9_-]+)*$#', $refererPath)
            && $refererPath !== parse_url($request->url(), PHP_URL_PATH)) {
            parse_str($refererParts['query'] ?? '', $rawQuery);
            $safeQuery = array_filter(
                array_intersect_key($rawQuery, array_flip(['tanggal', 'status', 'poliklinik_id', 'search', 'page', 'bagian', 'scope', 'tab'])),
                static fn ($value): bool => is_scalar($value) && strlen((string) $value) <= 100
            );
            $backUrl = $request->getSchemeAndHttpHost().$refererPath.($safeQuery !== [] ? '?'.http_build_query($safeQuery) : '');
        }

        $user = $request->user();
        $externalDocuments = PatientDocument::query()
            ->where('pasien_id', $pasien->id)
            ->whereNull('archived_at')
            ->with(['uploadedBy:id,name', 'kunjungan:id,no_kunjungan'])
            ->latest('document_date')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (PatientDocument $doc): array => $this->serializeDocument($user, $pasien, $doc))
            ->values()
            ->all();
        $documentPermissions = $this->documentPermissionsPayload($request, $user, $pasien, $perawatClinicResolver);

        return Inertia::render('pelayanan/pasien/rekam-medis', [
            'clinicProfile' => app(ClinicDocumentProfile::class)->data(),
            'odontogramFindingLabels' => OdontogramFinding::findingLabels(),
            'externalDocuments' => $externalDocuments,
            'documentPermissions' => $documentPermissions,
            'patient' => [
                'id' => $pasien->id,
                'name' => $pasien->nama,
                'medicalRecordNumber' => $pasien->no_rm,
                'nik' => $pasien->nik,
                'gender' => $pasien->kepesertaan?->jenis_kelamin ?? $pasien->jenis_kelamin,
                'age' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->age,
                'bloodType' => $pasien->kepesertaan?->golongan_darah ?? $pasien->golongan_darah,
                'birthPlace' => $pasien->kepesertaan?->tempat_lahir ?? $pasien->tempat_lahir,
                'birthDate' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->locale('id')->isoFormat('D MMMM Y'),
                'address' => $pasien->kepesertaan?->alamat ?? $pasien->alamat,
                'religion' => $pasien->kepesertaan?->agama ?? $pasien->agama,
                'unit' => $pasien->kepesertaan?->unit_kerja ?? $pasien->unit_kerja,
                'allergies' => $pasien->riwayat_alergi ?: 'Belum dicatat',
            ],
            'backUrl' => $backUrl,
            'profileUrl' => route('pelayanan.pasien.show', [$pasien, 'bagian' => 'catatan-medis']),
            'focusedVisitId' => isset($filters['kunjungan_id']) ? (int) $filters['kunjungan_id'] : null,
            'canSelectClinic' => $request->user()->role !== 'perawat',
            'filters' => [
                'search' => $filters['search'] ?? '',
                'poliklinik_id' => $filters['poliklinik_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'dari' => $filters['dari'] ?? '',
                'sampai' => $filters['sampai'] ?? '',
            ],
            'clinics' => $clinics->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'pagination' => [
                'currentPage' => $visits->currentPage(),
                'lastPage' => $visits->lastPage(),
                'from' => $visits->firstItem(),
                'to' => $visits->lastItem(),
                'total' => $visits->total(),
                'previousUrl' => $visits->previousPageUrl(),
                'nextUrl' => $visits->nextPageUrl(),
            ],
            'visits' => $visits->getCollection()->map(function ($visit) use ($noteRecorder, $correspondence): array {
                $versions = $visit->pemeriksaan?->clinicalNoteVersions->sortBy('version') ?? collect();
                $integrityValid = $visit->pemeriksaan?->signed_at ? $noteRecorder->verify($visit->pemeriksaan) : null;
                $effectiveNote = $integrityValid ? $versions->last()->payload : null;
                $clinicalAnswer = static fn (?string $value, string $fallback): string => ClinicalAnswer::normalize($value) ?? $fallback;
                $oralHygieneIndex = is_array($effectiveNote) ? ($effectiveNote['oral_hygiene_index'] ?? null) : $visit->pemeriksaan?->oral_hygiene_index;
                $odontogram = is_array($effectiveNote)
                    ? ($effectiveNote['odontogram'] ?? [])
                    : ($integrityValid === false ? [] : $visit->odontogramFindings->map(fn ($finding): array => [
                        'tooth_fdi' => $finding->tooth_fdi,
                        'surface' => $finding->surface,
                        'finding_code' => $finding->finding_code,
                        'notes' => $finding->notes,
                        'details' => $finding->details,
                        'detailSummary' => DentalAssessment::detailSummary($finding->details),
                    ])->all());

                return [
                    'documents' => in_array(auth()->user()->role, ['admin', 'dokter'], true) ? [
                        ...$visit->suratMedis->map(fn ($letter): array => ['name' => $letter->nomor_surat ?? 'Surat medis', 'type' => 'Surat medis', 'printUrl' => route('persuratan.surat.cetak', $letter).'?asal=rme'])->all(),
                        ...$visit->rujukanInternal->map(fn ($referral): array => ['name' => $referral->nomor_surat ?? 'Rujukan internal', 'type' => 'Rujukan internal', 'printUrl' => route('persuratan.rujukan.cetak', $referral).'?asal=rme'])->all(),
                    ] : [],
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                    'clinicType' => $visit->poliklinik?->jenis,
                    'doctor' => $visit->dokter?->nama ?? 'Belum diisi',
                    'date' => $visit->tanggal?->locale('id')->isoFormat('dddd, D MMMM Y'),
                    'status' => $visit->status,
                    'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                    'attachments' => $visit->attachmentSummaries(),
                    'attachmentArchive' => $visit->attachmentSummaries(true),
                    'screening' => $visit->screening ? [
                        'summary' => $visit->screening->handoverSummary(),
                        'submittedAt' => $visit->screening->submitted_at?->format('d/m/Y H:i'),
                        'recordedBy' => $visit->screening->petugas?->nama ?? $visit->screening->recordedBy?->name,
                        'systolic' => $visit->screening->td_sistole,
                        'diastolic' => $visit->screening->td_diastole,
                        'pulse' => $visit->screening->nadi,
                        'temperature' => $visit->screening->suhu,
                        'oxygenSaturation' => $visit->screening->spo2,
                        'weight' => $visit->screening->berat_badan,
                        'height' => $visit->screening->tinggi_badan,
                        'bmi' => $visit->screening->imt,
                        'respiration' => $visit->screening->respirasi,
                        'complaint' => $clinicalAnswer($visit->screening->keluhan, 'Belum dicatat'),
                        'medicalHistory' => $clinicalAnswer($visit->screening->reportedMedicalHistory(), 'Belum dicatat'),
                        'familyHistory' => $clinicalAnswer($visit->screening->riwayat_penyakit_keluarga, 'Belum dicatat'),
                        'allergyHistory' => $clinicalAnswer($visit->screening->riwayat_alergi ?: $visit->pasien?->riwayat_alergi, 'Belum dicatat'),
                        'fallRisk' => $visit->screening->risiko_jatuh,
                        'painScale' => $visit->screening->skala_nyeri,
                        'dentalPainLocation' => $clinicalAnswer($visit->screening->lokasi_nyeri_gigi, 'Belum dicatat'),
                        'dentalPainTriggers' => $visit->screening->pemicu_nyeri_gigi ?? [],
                        'dentalPainDuration' => $clinicalAnswer($visit->screening->durasi_keluhan_gigi, 'Belum dicatat'),
                        'dentalMedicalRisks' => $visit->screening->risiko_medis_gigi ?? [],
                        'dentalInfectionHistory' => $visit->screening->riwayat_infeksi_gigi ?? [],
                        'dentalNotes' => $clinicalAnswer($visit->screening->catatan_medis_gigi, 'Belum dicatat'),
                    ] : null,
                    'examination' => $visit->pemeriksaan ? [
                        'anamnesis' => is_array($effectiveNote) ? ($effectiveNote['anamnesis'] ?? null) : $visit->pemeriksaan->anamnesis,
                        'riwayat_obat' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_obat'] ?? null) : $visit->pemeriksaan->riwayat_obat),
                        'riwayat_perawatan_gigi' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_perawatan_gigi'] ?? null) : $visit->pemeriksaan->riwayat_perawatan_gigi),
                        'kebiasaan_gigi' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['kebiasaan_gigi'] ?? null) : $visit->pemeriksaan->kebiasaan_gigi),
                        'currentHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_sekarang'] ?? null) : $visit->pemeriksaan->riwayat_penyakit_sekarang, 'Belum dicatat'),
                        'pastHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_dahulu'] ?? null) : $visit->pemeriksaan->riwayat_penyakit_dahulu, 'Belum dicatat'),
                        'familyHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_keluarga'] ?? null) : $visit->pemeriksaan->riwayat_penyakit_keluarga, 'Belum dicatat'),
                        'allergyHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_alergi'] ?? null) : $visit->pemeriksaan->riwayat_alergi, 'Belum dicatat'),
                        'physicalExamination' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik'] ?? null) : $visit->pemeriksaan->pemeriksaan_fisik,
                        'odontogramSummary' => is_array($effectiveNote) ? ($effectiveNote['ringkasan_odontogram'] ?? null) : $visit->pemeriksaan->ringkasan_odontogram,
                        'generalClinicalSummary' => $this->recordedGeneralClinicalSummary($effectiveNote ?? [
                            'supporting_exams' => $visit->pemeriksaan->supporting_exams,
                            'review_of_systems' => $visit->pemeriksaan->review_of_systems,
                            'riwayat_sosial' => $visit->pemeriksaan->riwayat_sosial,
                            'kondisi_akhir' => $visit->pemeriksaan->kondisi_akhir,
                            'disposisi' => $visit->pemeriksaan->disposisi,
                        ]),
                        'physicalSystems' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik_terstruktur'] ?? []) : ($visit->pemeriksaan->pemeriksaan_fisik_terstruktur ?? []),
                        'bodyMap' => is_array($effectiveNote) ? ($effectiveNote['body_map'] ?? null) : $visit->pemeriksaan->body_map,
                        'extraoralExamination' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_ekstraoral'] ?? null) : $visit->pemeriksaan->pemeriksaan_ekstraoral, 'Belum diperiksa'),
                        'oralHygieneIndex' => is_numeric($oralHygieneIndex) ? (float) $oralHygieneIndex : null,
                        'differentialDiagnosis' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['diagnosis_banding'] ?? null) : $visit->pemeriksaan->diagnosis_banding, 'Belum dicatat'),
                        'nonPharmacological' => is_array($effectiveNote) ? ($effectiveNote['non_farmakologis'] ?? null) : $visit->pemeriksaan->non_farmakologis,
                        'education' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['edukasi'] ?? null) : $visit->pemeriksaan->edukasi, 'Belum dicatat'),
                        'notes' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['catatan'] ?? null) : $visit->pemeriksaan->catatan, 'Belum dicatat'),
                        'nextControl' => is_array($effectiveNote)
                            ? (isset($effectiveNote['kontrol_berikutnya']) ? Carbon::parse($effectiveNote['kontrol_berikutnya'])->format('d/m/Y') : null)
                            : $visit->pemeriksaan->kontrol_berikutnya?->format('d/m/Y'),
                        'dentalScope' => $effectiveNote['dental_review']['scope'] ?? null,
                        'dentalEscalation' => $effectiveNote['dental_review']['escalation'] ?? null,
                        'signedAt' => $visit->pemeriksaan->signed_at?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                        'signedBy' => $visit->pemeriksaan->signedBy?->name,
                        'signatureDataUrl' => $integrityValid === true
                            ? $correspondence->signatureDataUrl($visit->pemeriksaan->signedBy, $visit->pemeriksaan->signature_path_snapshot)
                            : null,
                        'integrityValid' => $integrityValid,
                        'versions' => $versions->map(fn ($version): array => [
                            'version' => $version->version,
                            'kind' => $version->kind,
                            'reason' => $version->reason,
                            'actor' => $version->actor?->name,
                            'recordedAt' => $version->recorded_at?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                            'anamnesis' => $version->payload['anamnesis'] ?? null,
                            'currentHistory' => $clinicalAnswer($version->payload['riwayat_penyakit_sekarang'] ?? null, 'Belum dicatat'),
                            'pastHistory' => $clinicalAnswer($version->payload['riwayat_penyakit_dahulu'] ?? null, 'Belum dicatat'),
                            'familyHistory' => $clinicalAnswer($version->payload['riwayat_penyakit_keluarga'] ?? null, 'Belum dicatat'),
                            'allergyHistory' => $clinicalAnswer($version->payload['riwayat_alergi'] ?? null, 'Belum dicatat'),
                            'physicalExamination' => $version->payload['pemeriksaan_fisik'] ?? null,
                            'odontogramSummary' => $version->payload['ringkasan_odontogram'] ?? null,
                            'generalClinicalSummary' => $this->recordedGeneralClinicalSummary($version->payload),
                            'physicalSystems' => $version->payload['pemeriksaan_fisik_terstruktur'] ?? [],
                            'bodyMap' => $version->payload['body_map'] ?? null,
                            'nonPharmacological' => $version->payload['non_farmakologis'] ?? null,
                            'education' => $version->payload['edukasi'] ?? null,
                            'extraoralExamination' => $clinicalAnswer($version->payload['pemeriksaan_ekstraoral'] ?? null, 'Belum diperiksa'),
                            'oralHygieneIndex' => is_numeric($version->payload['oral_hygiene_index'] ?? null) ? (float) $version->payload['oral_hygiene_index'] : null,
                            'differentialDiagnosis' => $clinicalAnswer($version->payload['diagnosis_banding'] ?? null, 'Belum dicatat'),
                            'odontogram' => collect($version->payload['odontogram'] ?? [])->map(fn (array $finding): array => [
                                'toothFdi' => $finding['tooth_fdi'],
                                'surface' => $finding['surface'],
                                'findingCode' => $finding['finding_code'],
                                'notes' => $finding['notes'] ?? null,
                                'details' => $finding['details'] ?? null,
                                'detailSummary' => DentalAssessment::detailSummary($finding['details'] ?? null),
                            ])->values()->all(),
                        ])->values()->all(),
                        'diagnoses' => collect(is_array($effectiveNote) ? ($effectiveNote['diagnoses'] ?? []) : ($integrityValid === false ? [] : $visit->pemeriksaan->diagnosa))->map(fn ($diagnosis): array => [
                            'code' => is_array($diagnosis) ? $diagnosis['code'] : $diagnosis->kode_icd10,
                            'codeSystem' => is_array($diagnosis) ? ($diagnosis['code_system'] ?? null) : $diagnosis->code_system,
                            'name' => is_array($diagnosis) ? $diagnosis['name'] : $diagnosis->nama_diagnosa,
                            'note' => is_array($diagnosis) ? ($diagnosis['note'] ?? null) : $diagnosis->keterangan,
                            'type' => is_array($diagnosis) ? $diagnosis['type'] : $diagnosis->jenis,
                            'toothFdi' => is_array($diagnosis) ? ($diagnosis['tooth_fdi'] ?? null) : $diagnosis->tooth_fdi,
                            'sourceFindingCode' => is_array($diagnosis) ? ($diagnosis['source_finding_code'] ?? null) : $diagnosis->source_finding_code,
                            'surfaces' => is_array($diagnosis) ? ($diagnosis['surfaces'] ?? []) : ($diagnosis->surfaces ?? []),
                        ])->all(),
                    ] : null,
                    'prescriptions' => collect(is_array($effectiveNote) ? ($effectiveNote['prescriptions'] ?? []) : ($integrityValid === false ? [] : ($visit->resep?->resepObat ?? collect())))->values()->map(fn ($item, int $index): array => [
                        'id' => is_array($item) ? $index + 1 : $item->id,
                        'name' => is_array($item) ? $item['name'] : $item->nama_obat,
                        'quantity' => is_array($item) ? $item['quantity'] : $item->jumlah,
                        'unit' => is_array($item) ? $item['unit'] : $item->satuan,
                        'instructions' => is_array($item) ? $item['instructions'] : $item->aturan_pakai,
                    ])->all(),
                    'procedureExecutions' => $integrityValid === false ? [] : $visit->tindakanKunjungan->map(fn ($item): array => [
                        'id' => $item->id, 'name' => $item->nama_tindakan ?? $item->nama_tindakan_manual ?? $item->tindakan?->nama,
                        'role' => $item->execution_role, 'status' => $item->execution_status,
                        'result' => $item->execution_result, 'performedBy' => $item->performedBy?->name,
                        'performedAt' => $item->performed_at?->format('d/m/Y H:i'),
                    ])->values()->all(),
                    'treatments' => collect(is_array($effectiveNote) ? ($effectiveNote['treatments'] ?? []) : ($integrityValid === false ? [] : $visit->tindakanKunjungan))->values()->map(fn ($item, int $index): array => [
                        'id' => is_array($item) ? $index + 1 : $item->id,
                        'name' => is_array($item) ? $item['name'] : ($item->nama_tindakan ?? $item->nama_tindakan_manual ?? $item->tindakan?->nama ?? 'Tindakan dihapus'),
                        'code' => is_array($item) ? ($item['code'] ?? null) : $item->kode_icd9cm,
                        'codeSystem' => is_array($item) ? ($item['code_system'] ?? null) : $item->code_system,
                        'codeRelease' => is_array($item) ? ($item['code_release'] ?? null) : $item->code_release,
                        'quantity' => is_array($item) ? $item['quantity'] : $item->jumlah,
                        'toothFdi' => is_array($item) ? ($item['tooth_fdi'] ?? null) : $item->tooth_fdi,
                        'note' => is_array($item) ? ($item['note'] ?? null) : $item->catatan,
                    ])->all(),
                    'odontogram' => collect($odontogram)->map(fn (array $finding): array => [
                        'toothFdi' => $finding['tooth_fdi'],
                        'surface' => $finding['surface'],
                        'findingCode' => $finding['finding_code'],
                        'notes' => $finding['notes'] ?? null,
                        'details' => $finding['details'] ?? null,
                        'detailSummary' => DentalAssessment::detailSummary($finding['details'] ?? null),
                    ])->values()->all(),
                ];
            })->all(),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    private function recordedGeneralClinicalSummary(array $note): array
    {
        $systems = $note['review_of_systems'] ?? [];
        $labels = array_intersect_key(GeneralAssessment::ROS, $systems);
        $supportingLabels = ['laboratory' => 'Laboratorium', 'rapid' => 'Pemeriksaan cepat', 'radiology' => 'Radiologi', 'results' => 'Hasil'];

        return array_filter([
            ...collect($note['supporting_exams'] ?? [])->mapWithKeys(fn ($value, $key): array => ['Penunjang - '.($supportingLabels[$key] ?? $key) => $value])->all(),
            ...GeneralAssessment::systemSummary($systems, $labels),
            'Riwayat sosial' => $note['riwayat_sosial'] ?? null,
            'Kondisi akhir' => $note['kondisi_akhir'] ?? null,
            'Status akhir' => $note['disposisi'] ?? null,
            'Klarifikasi dokter atas pengkajian perawat' => $note['nursing_review']['clarification'] ?? null,
        ], fn ($value): bool => ClinicalAnswer::normalize($value) !== null);
    }

    private function authorizedVisits(Request $request, Pasien $pasien, PerawatClinicResolver $perawatClinicResolver): HasMany
    {
        $visits = $pasien->kunjungan();
        if ($request->user()->role === 'dokter') {
            $doctorId = $this->doctorId();
            $visits->where(function ($query) use ($doctorId): void {
                $query->where('dokter_id', $doctorId)
                    ->orWhereHas('rujukanInternal', fn ($referral) => $referral->whereHas('kunjunganTujuan', fn ($dest) => $dest->where('dokter_id', $doctorId)))
                    ->orWhereIn('rujukan_internal_id', RujukanInternal::query()
                        ->whereHas('kunjungan', fn ($src) => $src->where('dokter_id', $doctorId))
                        ->select('id'));
            });
            abort_unless((clone $visits)->exists(), 403);
        } elseif ($request->user()->role === 'perawat') {
            $visits->where('poliklinik_id', $perawatClinicResolver->clinicId($request->user()));
            abort_unless((clone $visits)->exists(), 404);
        } elseif ($request->user()->role === 'farmasi') {
            $pharmacyClinicId = app(FarmasiClinicResolver::class)->scopedClinicIdOrFail($request->user());
            if ($pharmacyClinicId !== null) {
                $visits->where('poliklinik_id', $pharmacyClinicId);
            }
        }

        return $visits;
    }

    private function doctorId(): int
    {
        return $this->doctorAccess->doctorId(auth()->user());
    }

    public function gabung(Request $request): RedirectResponse
    {
        $request->validate([
            'pasien_utama_id' => ['required', 'integer', 'exists:pasien,id'],
            'pasien_hapus_id' => ['required', 'integer', 'exists:pasien,id', 'different:pasien_utama_id'],
        ]);

        return DB::transaction(function () use ($request): RedirectResponse {
            $patients = Pasien::whereKey([$request->integer('pasien_utama_id'), $request->integer('pasien_hapus_id')])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($patients->count() === 2, 404);
            $pasienUtama = $patients[$request->integer('pasien_utama_id')];
            $pasienHapus = $patients[$request->integer('pasien_hapus_id')];
            if (! $pasienUtama->nik || $pasienUtama->nik !== $pasienHapus->nik
                || ($pasienHapus->nip && $pasienUtama->nip !== $pasienHapus->nip)
                || ($pasienHapus->kepesertaan_id && $pasienUtama->kepesertaan_id !== $pasienHapus->kepesertaan_id)) {
                throw ValidationException::withMessages(['pasien_hapus_id' => 'Penggabungan hanya untuk identitas NIK yang sama dan kepesertaan yang sesuai.']);
            }
            if ($this->hasPatientHistory($pasienHapus)) {
                throw ValidationException::withMessages(['pasien_hapus_id' => 'Pasien duplikat memiliki riwayat pelayanan. Rekonsiliasi melalui prosedur rekam medis klinik diperlukan.']);
            }
            app(ClinicalAuditRecorder::class)->record($request, 'patient.merge', $pasienUtama->id, metadata: ['duplicate_patient_id' => $pasienHapus->id]);

            $pasienHapus->delete();

            return redirect()->route('pelayanan.pasien.show', $pasienUtama)
                ->with('success', 'Identitas duplikat berhasil diarsipkan.');
        });
    }

    private function hasPatientHistory(Pasien $patient): bool
    {
        return filled($patient->riwayat_alergi)
            || $patient->kunjungan()->exists()
            || FarmasiMandiri::where('pasien_id', $patient->id)->exists()
            || GeneralConsent::where('pasien_id', $patient->id)->exists()
            || PatientDocument::where('pasien_id', $patient->id)->exists();
    }

    /** @return array<string, int|string|null> */
    private function identityFromMembership(Kepesertaan $member): array
    {
        return [
            'kepesertaan_id' => $member->id, 'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
            'tempat_lahir' => $member->tempat_lahir, 'tanggal_lahir' => $member->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $member->jenis_kelamin, 'golongan_darah' => $member->golongan_darah,
            'agama' => $member->agama, 'alamat' => $member->alamat, 'unit_kerja' => $member->unit_kerja, 'rt' => $member->rt, 'rw' => $member->rw,
            'kelurahan' => $member->kelurahan, 'kecamatan' => $member->kecamatan,
        ];
    }

    public function export(Request $request, ClinicalAuditRecorder $auditRecorder): BinaryFileResponse
    {
        $auditRecorder->record($request, 'patient_directory.export');

        return Excel::download(new PasienExport, 'data-pasien-'.date('Y-m-d').'.xlsx');
    }
}
