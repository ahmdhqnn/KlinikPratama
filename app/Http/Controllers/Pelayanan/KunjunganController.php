<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\HakLayananVerifier;
use App\Http\Controllers\Controller;
use App\JadwalPraktikResolver;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\PatientAllergyHistory;
use App\PerawatClinicResolver;
use App\VisitQueryService;
use App\VisitSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KunjunganController extends Controller
{
    public function __construct(private JadwalPraktikResolver $schedule, private PerawatClinicResolver $perawatClinicResolver, private VisitSchedulingService $scheduling) {}

    public function index(Request $request, VisitQueryService $visits): Response
    {
        $assignedClinicId = $request->user()->role === 'perawat'
            ? $this->perawatClinicResolver->clinicId($request->user())
            : null;
        $filters = $visits->filters($request);
        $kunjungan = $visits->filtered($filters, $assignedClinicId)
            ->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->latest('created_at')->latest('id')
            ->paginate(10)
            ->withQueryString();

        $poliklinikList = $assignedClinicId
            ? Poliklinik::registrable()->whereKey($assignedClinicId)->orderBy('nama')->get()
            : Poliklinik::registrable()->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/index', [
            'canSelectClinic' => $assignedClinicId === null,
            'canManageVisits' => in_array($request->user()->role, ['admin', 'pendaftaran'], true),
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit) => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'date' => $visit->tanggal?->format('d/m/Y'),
                    'patient' => $visit->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                    'gender' => $visit->pasien?->jenis_kelamin,
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                    'doctor' => $visit->dokter?->nama,
                    'eligibility' => $visit->verified_at ? 'Terverifikasi' : 'Belum diverifikasi',
                    'status' => $visit->status,
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'total' => $kunjungan->total(),
                'perPage' => $kunjungan->perPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'date' => $filters['tanggal'] ?? '',
                'status' => $request->string('status')->toString(),
                'clinicId' => $assignedClinicId ? (string) $assignedClinicId : $request->string('poliklinik_id')->toString(),
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic) => ['id' => $clinic->id, 'name' => $clinic->nama, 'type' => $clinic->jenis]),
        ]);
    }

    public function antrian(): Response
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->whereDate('tanggal', today())
            ->whereNotIn('status', ['selesai', 'batal'])
            ->latest('created_at')->latest('id')
            ->paginate(10)->withQueryString();

        $visits = $kunjungan->getCollection()->map(fn (Kunjungan $visit) => [
            'id' => $visit->id,
            'number' => $visit->no_kunjungan,
            'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama,
            'status' => $visit->status,
            'examinationStarted' => $visit->pemeriksaan !== null,
            'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
        ]);

        return Inertia::render('pelayanan/kunjungan/antrian', [
            'visits' => [
                'data' => $visits,
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'today' => today()->locale('id')->isoFormat('dddd, D MMMM Y'),
        ]);
    }

    public function create(Request $request): Response
    {
        $pasienId = $request->pasien_id;
        $pasien = $pasienId ? Pasien::find($pasienId) : null;

        $pasienList = Pasien::latest('created_at')->latest('id')->limit($pasien ? 9 : 10)->get(['id', 'no_rm', 'nama', 'tanggal_lahir', 'jenis_kelamin']);
        if ($pasien && ! $pasienList->contains('id', $pasien->id)) {
            $pasienList->prepend($pasien);
        }
        $poliklinikList = Poliklinik::registrable()->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/form', [
            'patient' => $pasien ? ['id' => $pasien->id, 'name' => $pasien->nama, 'medicalRecordNumber' => $pasien->no_rm] : null,
            'patients' => $pasienList->map(fn (Pasien $patient) => ['id' => $patient->id, 'name' => $patient->nama, 'medicalRecordNumber' => $patient->no_rm]),
            ...$this->formOptions($poliklinikList),
            'doctorsUrl' => route('pelayanan.dokter.by-poli'),
            'patientSearchUrl' => route('pelayanan.kunjungan.pasien.search'),
            'today' => today()->toDateString(),
        ]);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $search = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q'];
        $patients = Pasien::query()
            ->where(fn ($query) => $query
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('no_rm', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"))
            ->latest('created_at')->latest('id')->limit(10)
            ->get(['id', 'no_rm', 'nama'])
            ->map(fn (Pasien $patient): array => [
                'id' => $patient->id,
                'name' => $patient->nama,
                'medicalRecordNumber' => $patient->no_rm,
            ])->values();

        return response()->json($patients);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', Rule::exists('nakes', 'id')->where('jabatan', 'dokter')->where('is_active', true)->whereNull('deleted_at')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'catatan' => ['nullable', 'string'],
        ]);
        $data['catatan'] = filled($data['catatan'] ?? null)
            ? trim($data['catatan'])
            : null;
        $data['dokter_id'] = $this->scheduling->resolveDoctor($data['dokter_id'] ?? null, (int) $data['poliklinik_id'], $data['tanggal']);

        $data['no_kunjungan'] = Kunjungan::generateNomor();
        $data['status'] = 'menunggu';

        $kunjungan = DB::transaction(function () use ($data, $request): Kunjungan {
            $patient = Pasien::whereKey($data['pasien_id'])->lockForUpdate()->firstOrFail();
            $this->scheduling->ensureNoDuplicate($patient->id, (int) $data['poliklinik_id'], $data['tanggal']);

            return Kunjungan::create([...$data, ...app(HakLayananVerifier::class)->patient($patient, $request)]);
        });

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)
            ->with('success', "Kunjungan berhasil didaftarkan: {$kunjungan->no_kunjungan}");
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder, PatientAllergyHistory $allergies): Response
    {
        if ($request->user()->role === 'perawat') {
            abort_if((int) $kunjungan->poliklinik_id !== $this->perawatClinicResolver->clinicId($request->user()), 404);
        }
        $auditRecorder->record($request, 'visit.view', $kunjungan->pasien_id, $kunjungan->id);
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'screening:id,kunjungan_id,submitted_at,riwayat_alergi,alergi_jenis,alergi_reaksi',
            'pemeriksaan', 'farmasi:id,kunjungan_id,status', 'tindakanKunjungan', 'resep.resepObat',
        ]);

        $role = $request->user()->role;
        $isRegistrationPage = $request->routeIs('pendaftaran.kunjungan.show');
        $canViewRme = in_array($role, ['admin', 'perawat'], true);
        $screeningCompleted = $kunjungan->screening !== null && ($kunjungan->screening->submitted_at !== null
            || ! in_array($kunjungan->status, ['menunggu', 'screening', 'batal'], true));
        $confirmedVisit = clone $kunjungan;
        if (! $screeningCompleted) {
            $confirmedVisit->setRelation('screening', null);
        }
        $allergyHistory = $canViewRme && $kunjungan->pasien ? $allergies->forVisit($confirmedVisit)['riwayat_alergi'] : null;
        $canEdit = in_array($role, ['admin', 'pendaftaran'], true) && $kunjungan->status === 'menunggu';
        $canScreen = $role === 'perawat' && ! $kunjungan->tanggal->isFuture()
            && in_array($kunjungan->status, ['menunggu', 'screening'], true);

        return Inertia::render('pelayanan/kunjungan/show', [
            'backUrl' => $isRegistrationPage ? route('pendaftaran.kunjungan-per-poli') : match ($role) {
                'perawat' => route('perawat.dashboard'),
                default => route('pelayanan.kunjungan.index'),
            },
            'permissions' => [
                'editVisit' => $canEdit,
                'viewRme' => $canViewRme,
                'processScreening' => $canScreen,
            ],
            'links' => [
                'edit' => $canEdit
                    ? route($isRegistrationPage ? 'pendaftaran.edit-kunjungan' : 'pelayanan.kunjungan.edit', $kunjungan)
                    : null,
                'patient' => $kunjungan->pasien
                    ? ($role === 'pendaftaran'
                        ? route('pendaftaran.database-pasien', ['search' => $kunjungan->pasien->no_rm])
                        : route('pelayanan.pasien.show', $kunjungan->pasien))
                    : null,
                'screening' => $canScreen ? route('pelayanan.screening.show', $kunjungan) : null,
                'screeningPrint' => in_array($role, ['admin', 'perawat'], true) && $kunjungan->screening
                    ? route('pelayanan.screening.print', $kunjungan) : null,
                'medicalRecord' => $canViewRme && $kunjungan->pasien
                    ? route('pelayanan.pasien.rekam-medis', $kunjungan->pasien)
                    : null,
            ],
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'date' => $kunjungan->tanggal?->locale('id')->isoFormat('dddd, D MMMM Y'),
                'status' => $kunjungan->status,
                'examinationStarted' => $kunjungan->pemeriksaan !== null,
                'isReservation' => $kunjungan->tanggal->isFuture() && $kunjungan->status === 'menunggu',
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'doctor' => $kunjungan->dokter?->nama,
                'eligibility' => $kunjungan->hak_layanan_snapshot,
                'verifiedAt' => $kunjungan->verified_at?->format('d/m/Y H:i'),
                'patientType' => $kunjungan->jenis_pasien,
                'notes' => $kunjungan->catatan,
                'patient' => [
                    'id' => $kunjungan->pasien?->id,
                    'name' => $kunjungan->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? 'Belum diisi',
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                    'bloodType' => $kunjungan->pasien?->golongan_darah,
                    'allergies' => $allergyHistory,
                ],
                'progress' => [
                    ['label' => 'Verifikasi hak layanan', 'done' => (bool) $kunjungan->verified_at],
                    ['label' => 'Pengkajian awal perawat', 'done' => $screeningCompleted, 'active' => $kunjungan->status === 'screening', 'note' => $kunjungan->screening && ! $screeningCompleted ? 'Draf pengkajian belum dikirim ke dokter.' : null],
                    ['label' => 'Pemeriksaan dokter', 'done' => $kunjungan->pemeriksaan?->status === 'selesai'],
                    ...($kunjungan->tindakanKunjungan->where('execution_role', 'perawat')->isNotEmpty() ? [
                        ['label' => 'Tindakan perawat', 'done' => ! $kunjungan->tindakanKunjungan->where('execution_role', 'perawat')->contains('execution_status', 'pending'), 'active' => $kunjungan->status === 'tindakan'],
                    ] : []),
                    ...($kunjungan->resep?->resepObat->contains('is_resep_luar', false) ? [
                        ['label' => 'Farmasi', 'done' => $kunjungan->farmasi?->status === 'selesai'],
                    ] : []),
                    ['label' => 'Kunjungan selesai', 'done' => $kunjungan->status === 'selesai'],
                ],
            ],
        ]);
    }

    public function edit(Kunjungan $kunjungan): Response
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $poliklinikList = Poliklinik::registrable()->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/form', [
            'visit' => [
                'id' => $kunjungan->id,
                'patient' => ['id' => $kunjungan->pasien_id, 'name' => $kunjungan->pasien?->nama ?? 'Pasien'],
                'clinicId' => $kunjungan->poliklinik_id,
                'doctorId' => $kunjungan->dokter_id,
                'date' => $kunjungan->tanggal?->toDateString(),
                'patientType' => $kunjungan->jenis_pasien,
                'eligibility' => $kunjungan->hak_layanan_snapshot,
                'verifiedAt' => $kunjungan->verified_at?->format('d/m/Y H:i'),
                'notes' => $kunjungan->catatan,
            ],
            ...$this->formOptions($poliklinikList),
            'doctorsUrl' => route('pelayanan.dokter.by-poli'),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $data = $request->validate([
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', Rule::exists('nakes', 'id')->where('jabatan', 'dokter')->where('is_active', true)->whereNull('deleted_at')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'catatan' => ['nullable', 'string'],
        ]);
        $data['catatan'] = filled($data['catatan'] ?? null)
            ? trim($data['catatan'])
            : null;
        $data['dokter_id'] = $this->scheduling->resolveDoctor($data['dokter_id'] ?? null, (int) $data['poliklinik_id'], $data['tanggal']);

        DB::transaction(function () use ($kunjungan, $data, $request): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');
            Pasien::whereKey($lockedVisit->pasien_id)->lockForUpdate()->firstOrFail();
            $this->scheduling->ensureNoDuplicate($lockedVisit->pasien_id, (int) $data['poliklinik_id'], $data['tanggal'], $lockedVisit->id);
            $lockedVisit->update([...$data, ...app(HakLayananVerifier::class)->patient($lockedVisit->pasien, $request)]);
        });

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)->with('success', 'Kunjungan berhasil diperbarui.');
    }

    public function destroy(Kunjungan $kunjungan): RedirectResponse
    {
        $result = DB::transaction(function () use ($kunjungan): string {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat dihapus.');

            if ($lockedVisit->screening()->exists() || $lockedVisit->resep()->exists() || $lockedVisit->pemeriksaan()->exists()) {
                return 'pelayanan';
            }

            $lockedVisit->delete();

            return 'deleted';
        });

        if ($result === 'pelayanan') {
            return back()->with('error', 'Tidak dapat menghapus kunjungan yang sudah ada pelayanan medis.');
        }

        return redirect()->route('pelayanan.kunjungan.index')->with('success', 'Kunjungan berhasil dihapus.');
    }

    public function batal(Kunjungan $kunjungan): RedirectResponse
    {
        DB::transaction(function () use ($kunjungan): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat dibatalkan.');
            $lockedVisit->update(['status' => 'batal']);
        });

        return back()->with('success', 'Kunjungan berhasil dibatalkan.');
    }

    /**
     * @param  Collection<int, Poliklinik>  $poliklinikList
     * @return array{clinics: Collection<int, array{id: int, name: string}>}
     */
    private function formOptions(Collection $poliklinikList): array
    {
        return [
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic) => ['id' => $clinic->id, 'name' => $clinic->nama, 'type' => $clinic->jenis]),
        ];
    }

    public function doctorsByClinic(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'poliklinik_id' => ['required', 'integer', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ]);
        $date = Carbon::parse($filters['tanggal']);

        $doctors = $this->scheduledDoctors((int) $filters['poliklinik_id'], $date)
            ->map(fn (Nakes $doctor): array => ['id' => $doctor->id, 'nama' => $doctor->nama]);

        return response()->json($doctors);
    }

    /**
     * @return Collection<int, Nakes>
     */
    private function scheduledDoctors(int $clinicId, Carbon $date): Collection
    {
        return $this->schedule->doctors($clinicId, $date);
    }
}
