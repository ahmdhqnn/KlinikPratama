<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicDocumentNumber;
use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\Exports\PasienExport;
use App\HakLayananVerifier;
use App\Http\Controllers\Controller;
use App\KepesertaanRegistry;
use App\Models\Kepesertaan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\PatientRegistrationService;
use App\PerawatClinicResolver;
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
    public function __construct(private \App\DoctorAccessService $doctorAccess) {}

    public function index(Request $request, ClinicalAuditRecorder $auditRecorder, PerawatClinicResolver $perawatClinicResolver): Response
    {
        $doctorId = $request->user()->role === 'dokter' ? $this->doctorId() : null;
        $clinicId = $request->user()->role === 'perawat'
            ? $perawatClinicResolver->clinicId($request->user())
            : null;
        $pasien = Pasien::with('kepesertaan')
            ->when($doctorId, fn ($query, int $id) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('dokter_id', $id)))
            ->when($clinicId, fn ($query, int $id) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('poliklinik_id', $id)))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($query) => $query
                ->where('nama', 'like', "%$s%")
                ->orWhere('no_rm', 'like', "%$s%")
                ->orWhere('nik', 'like', "%$s%")->orWhere('nip', 'like', "%$s%")
                ->orWhere('telepon', 'like', "%$s%")))
            ->when($request->kategori, fn ($q, $category) => $q->whereHas('kepesertaan', fn ($members) => $members->whereIn('kategori', Kepesertaan::persistedCategoryKeys($category))))
            ->latest('created_at')->latest('id')
            ->paginate(10)
            ->withQueryString();

        $auditRecorder->record($request, 'patient_directory.view');

        return Inertia::render('pelayanan/pasien/index', [
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
            ],
            'categories' => Kepesertaan::CATEGORIES,
            'otherTypes' => Kepesertaan::OTHER_TYPES,
            'sponsors' => $request->user()->role === 'admin' ? Kepesertaan::where('kategori', 'asn')->orderBy('nama')->get(['id', 'nama']) : [],
            'today' => today()->toDateString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('pelayanan/pasien/form', [
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
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['riwayat_alergi'] = filled($data['riwayat_alergi'] ?? null)
            ? trim($data['riwayat_alergi'])
            : null;

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
            'kategori' => ['required', 'in:asn,lainnya'],
            'jenis_lainnya' => ['nullable', 'string'],
            'status_kepegawaian' => ['required', 'in:aktif,pensiun'],
            'pegawai_penanggung_id' => ['nullable', 'integer'],
            'hubungan_keluarga' => ['nullable', 'string'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'agama' => ['required', 'string', 'max:50'],
            'alamat' => ['required', 'string', 'max:2000'],
            'rt' => ['required', 'string', 'max:5'],
            'rw' => ['required', 'string', 'max:5'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'unit_kerja' => ['required', 'string', 'max:200'],
            'referensi_bukti' => ['required', 'string', 'max:255'],
            'hak_layanan' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request, $pasien, $data, $registry, $auditRecorder): void {
            $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            if ($patient->kepesertaan_id) {
                throw ValidationException::withMessages(['nik' => 'Pasien sudah terhubung dengan kepesertaan. Perbarui status melalui menu Kepesertaan & Hak Layanan.']);
            }
            if (mb_strtolower(trim($patient->nama)) !== mb_strtolower(trim($data['nama']))) {
                throw ValidationException::withMessages(['nama' => 'Nama verifikasi harus sesuai dengan nama pasien yang dipilih.']);
            }
            if ($patient->nik && $patient->nik !== ($data['nik'] ?? null)) {
                throw ValidationException::withMessages(['nik' => 'NIK verifikasi harus sesuai dengan identitas pasien.']);
            }
            if (! empty($data['nik']) && Pasien::withTrashed()->where('nik', $data['nik'])->where('id', '!=', $patient->id)->exists()) {
                throw ValidationException::withMessages(['nik' => 'NIK sudah terhubung dengan data pasien lain. Periksa database pasien sebelum verifikasi.']);
            }

            if ($patient->nip && $patient->nip !== ($data['nip'] ?? null)) {
                throw ValidationException::withMessages(['nip' => 'NIP verifikasi harus sesuai dengan identitas pasien.']);
            }
            if (! empty($data['nip']) && Pasien::withTrashed()->where('nip', $data['nip'])->where('id', '!=', $patient->id)->exists()) {
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

    public function show(Request $request, Pasien $pasien, ClinicalAuditRecorder $auditRecorder, PerawatClinicResolver $perawatClinicResolver): Response
    {
        $this->authorizeDoctorPatient($pasien);
        $clinicId = $request->user()->role === 'perawat'
            ? $perawatClinicResolver->clinicId($request->user())
            : null;
        abort_if($clinicId && ! $pasien->kunjungan()->where('poliklinik_id', $clinicId)->exists(), 404);
        $auditRecorder->record($request, 'patient_profile.view', $pasien->id);
        $pasien->load(['kepesertaan', 'kunjungan' => fn ($q) => $q
            ->when(auth()->user()->role === 'dokter', fn ($visits) => $visits->where('dokter_id', $this->doctorId()))
            ->when($clinicId, fn ($visits) => $visits->where('poliklinik_id', $clinicId))
            ->with(['poliklinik', 'dokter'])->latest()->limit(10)]);

        return Inertia::render('pelayanan/pasien/show', [
            'permissions' => [
                'viewRme' => in_array(auth()->user()->role, ['dokter', 'perawat'], true),
                'editPatient' => auth()->user()->role === 'admin',
                'openVisit' => in_array(auth()->user()->role, ['admin', 'pendaftaran'], true),
            ],
            'patient' => [
                'id' => $pasien->id,
                'medicalRecordNumber' => $pasien->no_rm,
                'name' => $pasien->nama,
                'nik' => $pasien->nik,
                'birthDate' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->isoFormat('D MMMM Y'),
                'gender' => $pasien->kepesertaan?->jenis_kelamin ?? $pasien->jenis_kelamin,
                'age' => ($pasien->kepesertaan?->tanggal_lahir ?? $pasien->tanggal_lahir)?->age,
                'bloodType' => $pasien->kepesertaan?->golongan_darah ?? $pasien->golongan_darah,
                'birthPlace' => $pasien->kepesertaan?->tempat_lahir ?? $pasien->tempat_lahir,
                'religion' => $pasien->kepesertaan?->agama ?? $pasien->agama,
                'rt' => $pasien->kepesertaan?->rt ?? $pasien->rt,
                'rw' => $pasien->kepesertaan?->rw ?? $pasien->rw,
                'village' => $pasien->kepesertaan?->kelurahan ?? $pasien->kelurahan,
                'district' => $pasien->kepesertaan?->kecamatan ?? $pasien->kecamatan,
                'unit' => $pasien->kepesertaan?->unit_kerja,
                'memberNip' => $pasien->nip ?? $pasien->kepesertaan?->nip,
                'phone' => $pasien->telepon,
                'address' => $pasien->kepesertaan?->alamat ?? $pasien->alamat,
                'membership' => $pasien->kepesertaan?->membershipLabel() ?? 'Belum terverifikasi',
                'allergies' => in_array(auth()->user()->role, ['dokter', 'perawat'], true)
                    ? ($pasien->riwayat_alergi ?: 'Belum dicatat')
                    : null,
                'visits' => $pasien->kunjungan->map(fn ($visit) => [
                    'id' => $visit->id,
                    'url' => auth()->user()->role === 'dokter'
                        ? route('pelayanan.pemeriksaan.show', $visit)
                        : route('pelayanan.kunjungan.show', $visit),
                    'number' => $visit->no_kunjungan,
                    'date' => $visit->tanggal?->isoFormat('D MMM Y'),
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                    'doctor' => $visit->dokter?->nama ?? 'Belum diisi',
                    'status' => $visit->status,
                ]),
            ],
        ]);
    }

    public function edit(Pasien $pasien): Response
    {
        $pasien->load('kepesertaan');

        return Inertia::render('pelayanan/pasien/form', [
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
                'unit' => $pasien->kepesertaan?->unit_kerja,
                'memberNip' => $pasien->nip ?? $pasien->kepesertaan?->nip,
                'phone' => $pasien->telepon,
                'address' => $pasien->kepesertaan?->alamat ?? $pasien->alamat,
                'membershipId' => $pasien->kepesertaan_id,
                'allergyHistory' => $pasien->riwayat_alergi,
            ],
            'today' => today()->toDateString(),
        ]);
    }

    public function update(Request $request, Pasien $pasien, PatientRegistrationService $registrationService): RedirectResponse
    {
        if (! $pasien->kepesertaan_id && ! $request->filled('kepesertaan_id')) {
            $data = $registrationService->validateManual($request);
            DB::transaction(function () use ($pasien, $data): void {
                $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
                if ($patient->kepesertaan_id) {
                    throw ValidationException::withMessages(['nama' => 'Pasien sudah diverifikasi. Muat ulang untuk memperbarui data kepesertaan.']);
                }
                foreach (['nik', 'nip'] as $field) {
                    if (! empty($data[$field]) && Pasien::withTrashed()->where($field, $data[$field])->where('id', '!=', $patient->id)->exists()) {
                        throw ValidationException::withMessages([$field => strtoupper($field).' sudah terhubung dengan pasien lain.']);
                    }
                }
                $patient->update($data);
            });

            return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
        }

        $data = $request->validate([
            'kepesertaan_id' => ['required', 'integer', 'exists:kepesertaan,id'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('riwayat_alergi', $data)) {
            $data['riwayat_alergi'] = filled($data['riwayat_alergi'])
                ? trim($data['riwayat_alergi'])
                : null;
        }

        DB::transaction(function () use ($data, $pasien): void {
            $member = Kepesertaan::whereKey($data['kepesertaan_id'])->lockForUpdate()->firstOrFail();
            $patient = Pasien::whereKey($pasien->id)->lockForUpdate()->firstOrFail();
            if (($patient->nik && $member->nik !== $patient->nik)
                || ($patient->nip && $member->nip !== $patient->nip)
                || (! $member->nik && mb_strtolower($member->nama) !== mb_strtolower($patient->nama))
                || Pasien::withTrashed()->where('kepesertaan_id', $member->id)->where('id', '!=', $patient->id)->exists()) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Identitas atau tautan peserta tidak sesuai pasien ini.']);
            }
            if (! $member->tanggal_lahir || ! $member->jenis_kelamin) {
                throw ValidationException::withMessages(['kepesertaan_id' => 'Lengkapi tanggal lahir dan jenis kelamin pada direktori kepesertaan sebelum memperbarui data pasien.']);
            }
            $patient->update([...$data, ...$this->identityFromMembership($member)]);
        });

        return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(Pasien $pasien): RedirectResponse
    {
        $pasien->delete();

        return redirect()->route('pelayanan.pasien.index')->with('success', 'Data pasien berhasil dihapus.');
    }

    public function rekamMedis(Request $request, Pasien $pasien, ClinicalAuditRecorder $auditRecorder, ClinicalNoteRecorder $noteRecorder, PerawatClinicResolver $perawatClinicResolver): Response
    {
        $this->authorizeDoctorPatient($pasien);
        $clinicId = $request->user()->role === 'perawat'
            ? $perawatClinicResolver->clinicId($request->user())
            : null;
        abort_if($clinicId && ! $pasien->kunjungan()->where('poliklinik_id', $clinicId)->exists(), 404);
        $auditRecorder->record($request, 'medical_record.view', $pasien->id);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,selesai,batal'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ]);

        \App\DateRangeFilter::fromRequest($request);
        $authorizedVisits = $pasien->kunjungan()
            ->when(auth()->user()->role === 'dokter', fn ($visits) => $visits->where('dokter_id', $this->doctorId()))
            ->when($clinicId, fn ($visits) => $visits->where('poliklinik_id', $clinicId));
        $visitQuery = (clone $authorizedVisits)
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
            'screening',
            'pemeriksaan.diagnosa',
            'pemeriksaan.signedBy',
            'pemeriksaan.clinicalNoteVersions.actor',
            'odontogramFindings',
            'resep.resepObat',
            'tindakanKunjungan.tindakan:id,nama',
        ])->latest('tanggal')->latest('id')->paginate(10)->withQueryString();
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
            && $refererPath !== parse_url($request->url(), PHP_URL_PATH)) {
            $backUrl = $request->getSchemeAndHttpHost().$refererPath.(isset($refererParts['query']) ? '?'.$refererParts['query'] : '');
        }

        return Inertia::render('pelayanan/pasien/rekam-medis', [
            'patient' => [
                'id' => $pasien->id,
                'name' => $pasien->nama,
                'medicalRecordNumber' => $pasien->no_rm,
                'nik' => $pasien->nik,
                'gender' => $pasien->jenis_kelamin,
                'age' => $pasien->umur,
                'bloodType' => $pasien->golongan_darah,
                'allergies' => $pasien->riwayat_alergi ?: 'Belum dicatat',
            ],
            'backUrl' => $backUrl,
            'canSelectClinic' => $clinicId === null,
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
            'visits' => $visits->getCollection()->map(function ($visit) use ($noteRecorder): array {
                $versions = $visit->pemeriksaan?->clinicalNoteVersions->sortBy('version') ?? collect();
                $integrityValid = $visit->pemeriksaan?->signed_at ? $noteRecorder->verify($visit->pemeriksaan) : null;
                $effectiveNote = $integrityValid ? $versions->last()->payload : null;
                $clinicalAnswer = static fn (?string $value, string $fallback): string => filled($value) ? $value : $fallback;
                $oralHygieneIndex = is_array($effectiveNote) ? ($effectiveNote['oral_hygiene_index'] ?? null) : $visit->pemeriksaan?->oral_hygiene_index;
                $odontogram = is_array($effectiveNote)
                    ? ($effectiveNote['odontogram'] ?? [])
                    : ($integrityValid === false ? [] : $visit->odontogramFindings->map(fn ($finding): array => [
                        'tooth_fdi' => $finding->tooth_fdi,
                        'surface' => $finding->surface,
                        'finding_code' => $finding->finding_code,
                        'notes' => $finding->notes,
                    ])->all());

                return [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                    'clinicType' => $visit->poliklinik?->jenis,
                    'doctor' => $visit->dokter?->nama ?? 'Belum diisi',
                    'date' => $visit->tanggal?->locale('id')->isoFormat('dddd, D MMMM Y'),
                    'status' => $visit->status,
                    'screening' => $visit->screening ? [
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
                        'medicalHistory' => $clinicalAnswer($visit->screening->riwayat_penyakit, 'Belum dicatat'),
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
                        'currentHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_sekarang'] ?? null) : $visit->pemeriksaan->riwayat_penyakit_sekarang, 'Belum dicatat'),
                        'pastHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_dahulu'] ?? null) : $visit->pemeriksaan->riwayat_penyakit_dahulu, 'Belum dicatat'),
                        'familyHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_keluarga'] ?? null) : $visit->pemeriksaan->riwayat_penyakit_keluarga, 'Belum dicatat'),
                        'allergyHistory' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['riwayat_alergi'] ?? null) : ($visit->pemeriksaan->riwayat_alergi ?: $visit->screening?->riwayat_alergi ?: $visit->pasien?->riwayat_alergi), 'Belum dicatat'),
                        'physicalExamination' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik'] ?? null) : $visit->pemeriksaan->pemeriksaan_fisik,
                        'physicalSystems' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik_terstruktur'] ?? []) : ($visit->pemeriksaan->pemeriksaan_fisik_terstruktur ?? []),
                        'extraoralExamination' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_ekstraoral'] ?? null) : $visit->pemeriksaan->pemeriksaan_ekstraoral, 'Belum diperiksa'),
                        'oralHygieneIndex' => is_numeric($oralHygieneIndex) ? (float) $oralHygieneIndex : null,
                        'differentialDiagnosis' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['diagnosis_banding'] ?? null) : $visit->pemeriksaan->diagnosis_banding, 'Belum dicatat'),
                        'education' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['edukasi'] ?? null) : $visit->pemeriksaan->edukasi, 'Belum dicatat'),
                        'notes' => $clinicalAnswer(is_array($effectiveNote) ? ($effectiveNote['catatan'] ?? null) : $visit->pemeriksaan->catatan, 'Belum dicatat'),
                        'nextControl' => is_array($effectiveNote)
                            ? (isset($effectiveNote['kontrol_berikutnya']) ? Carbon::parse($effectiveNote['kontrol_berikutnya'])->format('d/m/Y') : null)
                            : $visit->pemeriksaan->kontrol_berikutnya?->format('d/m/Y'),
                        'signedAt' => $visit->pemeriksaan->signed_at?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                        'signedBy' => $visit->pemeriksaan->signedBy?->name,
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
                            'physicalSystems' => $version->payload['pemeriksaan_fisik_terstruktur'] ?? [],
                            'extraoralExamination' => $clinicalAnswer($version->payload['pemeriksaan_ekstraoral'] ?? null, 'Belum diperiksa'),
                            'oralHygieneIndex' => is_numeric($version->payload['oral_hygiene_index'] ?? null) ? (float) $version->payload['oral_hygiene_index'] : null,
                            'differentialDiagnosis' => $clinicalAnswer($version->payload['diagnosis_banding'] ?? null, 'Belum dicatat'),
                            'odontogram' => collect($version->payload['odontogram'] ?? [])->map(fn (array $finding): array => [
                                'toothFdi' => $finding['tooth_fdi'],
                                'surface' => $finding['surface'],
                                'findingCode' => $finding['finding_code'],
                                'notes' => $finding['notes'] ?? null,
                            ])->values()->all(),
                        ])->values()->all(),
                        'diagnoses' => collect(is_array($effectiveNote) ? ($effectiveNote['diagnoses'] ?? []) : ($integrityValid === false ? [] : $visit->pemeriksaan->diagnosa))->map(fn ($diagnosis): array => [
                            'code' => is_array($diagnosis) ? $diagnosis['code'] : $diagnosis->kode_icd10,
                            'name' => is_array($diagnosis) ? $diagnosis['name'] : $diagnosis->nama_diagnosa,
                            'type' => is_array($diagnosis) ? $diagnosis['type'] : $diagnosis->jenis,
                        ])->all(),
                    ] : null,
                    'prescriptions' => collect(is_array($effectiveNote) ? ($effectiveNote['prescriptions'] ?? []) : ($integrityValid === false ? [] : ($visit->resep?->resepObat ?? collect())))->values()->map(fn ($item, int $index): array => [
                        'id' => is_array($item) ? $index + 1 : $item->id,
                        'name' => is_array($item) ? $item['name'] : $item->nama_obat,
                        'quantity' => is_array($item) ? $item['quantity'] : $item->jumlah,
                        'unit' => is_array($item) ? $item['unit'] : $item->satuan,
                        'instructions' => is_array($item) ? $item['instructions'] : $item->aturan_pakai,
                    ])->all(),
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
                    ])->values()->all(),
                ];
            })->all(),
        ]);
    }

    private function authorizeDoctorPatient(Pasien $pasien): void
    {
        if (auth()->user()->role !== 'dokter') {
            return;
        }

        $doctorId = $this->doctorId();
        abort_unless(
            $doctorId && $pasien->kunjungan()->where('dokter_id', $doctorId)->exists(),
            403,
            'Pasien bukan tanggung jawab dokter ini.'
        );
    }

    private function doctorId(): int
    {
        return $this->doctorAccess->doctorId(auth()->user());
    }

    public function gabung(Request $request): RedirectResponse
    {
        $request->validate([
            'pasien_utama_id' => ['required', 'exists:pasien,id'],
            'pasien_hapus_id' => ['required', 'exists:pasien,id', 'different:pasien_utama_id'],
        ]);

        return DB::transaction(function () use ($request): RedirectResponse {
            $pasienUtama = Pasien::whereKey($request->pasien_utama_id)->lockForUpdate()->firstOrFail();
            $pasienHapus = Pasien::whereKey($request->pasien_hapus_id)->lockForUpdate()->firstOrFail();
            if (! $pasienUtama->nik || $pasienUtama->nik !== $pasienHapus->nik || ($pasienHapus->kepesertaan_id && $pasienUtama->kepesertaan_id !== $pasienHapus->kepesertaan_id)) {
                throw ValidationException::withMessages(['pasien_hapus_id' => 'Penggabungan hanya untuk identitas NIK yang sama dan kepesertaan yang sesuai.']);
            }
            app(ClinicalAuditRecorder::class)->record($request, 'patient.merge', $pasienUtama->id, metadata: ['duplicate_patient_id' => $pasienHapus->id]);

            // Move all kunjungan from pasienHapus to pasienUtama
            $pasienHapus->kunjungan()->update(['pasien_id' => $pasienUtama->id]);
            $pasienHapus->delete();

            return redirect()->route('pelayanan.pasien.show', $pasienUtama)
                ->with('success', 'Rekam medis berhasil digabungkan.');
        });
    }

    /** @return array<string, int|string|null> */
    private function identityFromMembership(Kepesertaan $member): array
    {
        return [
            'kepesertaan_id' => $member->id, 'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
            'tempat_lahir' => $member->tempat_lahir, 'tanggal_lahir' => $member->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $member->jenis_kelamin, 'golongan_darah' => $member->golongan_darah,
            'agama' => $member->agama, 'alamat' => $member->alamat, 'rt' => $member->rt, 'rw' => $member->rw,
            'kelurahan' => $member->kelurahan, 'kecamatan' => $member->kecamatan,
        ];
    }

    public function export(Request $request, ClinicalAuditRecorder $auditRecorder): BinaryFileResponse
    {
        $auditRecorder->record($request, 'patient_directory.export');

        return Excel::download(new PasienExport, 'data-pasien-'.date('Y-m-d').'.xlsx');
    }
}
