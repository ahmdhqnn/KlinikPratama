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
use Illuminate\Pagination\LengthAwarePaginator;
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
        $todayVisits = Kunjungan::query()->whereDate('tanggal', today())
            ->when($assignedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->latest('tanggal')->latest('created_at')->latest('id')
            ->paginate(10, ['*'], 'today_page')->withQueryString();
        $summaryQuery = Kunjungan::query()
            ->when($assignedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId));
        $mapVisit = fn (Kunjungan $visit): array => [
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
            'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
            'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
        ];

        $poliklinikList = $assignedClinicId
            ? Poliklinik::registrable()->whereKey($assignedClinicId)->orderBy('nama')->get()
            : Poliklinik::registrable()->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/index', [
            'canSelectClinic' => $assignedClinicId === null,
            'canManageVisits' => in_array($request->user()->role, ['admin', 'pendaftaran'], true),
            'stats' => [
                'total' => (clone $summaryQuery)->count(),
                'waiting' => (clone $summaryQuery)->whereDate('tanggal', '<=', today())->where('status', 'menunggu')->count(),
                'examination' => (clone $summaryQuery)->where('status', 'pemeriksaan')->count(),
                'completedToday' => (clone $summaryQuery)->whereDate('tanggal', today())->where('status', 'selesai')->count(),
            ],
            'visits' => $this->visitPagination($kunjungan, $mapVisit),
            'todayVisits' => $this->visitPagination($todayVisits, $mapVisit),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'date' => $filters['tanggal'] ?? '',
                'status' => $request->string('status')->toString(),
                'clinicId' => $assignedClinicId ? (string) $assignedClinicId : $request->string('poliklinik_id')->toString(),
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic) => ['id' => $clinic->id, 'name' => $clinic->nama, 'type' => $clinic->jenis]),
        ]);
    }

    private function visitPagination(LengthAwarePaginator $pagination, callable $mapVisit): array
    {
        return [
            'data' => $pagination->getCollection()->map($mapVisit)->values(),
            'currentPage' => $pagination->currentPage(),
            'lastPage' => $pagination->lastPage(),
            'total' => $pagination->total(),
            'perPage' => $pagination->perPage(),
            'from' => $pagination->firstItem(),
            'to' => $pagination->lastItem(),
            'previousUrl' => $pagination->previousPageUrl(),
            'nextUrl' => $pagination->nextPageUrl(),
        ];
    }

    public function antrian(Request $request, VisitQueryService $visits): Response
    {
        $filters = $visits->filters($request);
        $query = $visits->filtered($filters)->with(['pasien', 'poliklinik', 'dokter', 'screening', 'pemeriksaan', 'farmasi'])
            ->withExists(['tindakanKunjungan as has_nurse_procedures' => fn ($query) => $query->where('execution_role', 'perawat')]);
        $todayQuery = Kunjungan::query()->whereDate('tanggal', today());
        $counts = (clone $todayQuery)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $todayVisits = (clone $todayQuery)->with(['pasien', 'poliklinik', 'dokter', 'screening', 'pemeriksaan', 'farmasi'])
            ->withExists(['tindakanKunjungan as has_nurse_procedures' => fn ($query) => $query->where('execution_role', 'perawat')])
            ->latest('created_at')->latest('id')
            ->paginate(10, ['*'], 'today_page')->withQueryString();
        $allVisits = $query->latest('tanggal')->latest('created_at')->latest('id')
            ->paginate(10, ['*'], 'page')->withQueryString();

        $serviceQueues = collect([
            'assessment' => ['menunggu', 'screening'],
            'examination' => ['pemeriksaan'],
            'procedures' => ['tindakan'],
            'pharmacy' => ['farmasi'],
        ])->map(function (array $statuses) use ($request): array {
            $query = Kunjungan::query()->whereDate('tanggal', today())->whereIn('status', $statuses);

            return [
                'total' => (clone $query)->count(),
                'data' => $query->with(['pasien', 'poliklinik', 'dokter', 'screening', 'pemeriksaan', 'farmasi'])
                    ->withExists(['tindakanKunjungan as has_nurse_procedures' => fn ($query) => $query->where('execution_role', 'perawat')])
                    ->oldest('created_at')->oldest('id')->limit(5)->get()
                    ->map(fn (Kunjungan $visit): array => $this->queueVisit($visit, $request))->values(),
            ];
        });

        return Inertia::render('pelayanan/kunjungan/antrian', [
            'serviceQueues' => $serviceQueues,
            'todayVisits' => $this->queuePagination($todayVisits, $request),
            'visits' => $this->queuePagination($allVisits, $request),
            'stageCounts' => $counts,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'date' => $filters['tanggal'] ?? '',
                'status' => $filters['status'] ?? '',
                'clinicId' => (string) ($filters['poliklinik_id'] ?? ''),
            ],
            'clinics' => Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])->map(fn (Poliklinik $clinic): array => ['id' => $clinic->id, 'name' => $clinic->nama]),
            'queueUrl' => route('pelayanan.antrian'),
            'today' => today()->locale('id')->isoFormat('dddd, D MMMM Y'),
        ]);
    }

    private function queuePagination(LengthAwarePaginator $pagination, Request $request): array
    {
        return [
            'data' => $pagination->getCollection()->map(fn (Kunjungan $visit): array => $this->queueVisit($visit, $request))->values(),
            'currentPage' => $pagination->currentPage(),
            'lastPage' => $pagination->lastPage(),
            'from' => $pagination->firstItem(),
            'to' => $pagination->lastItem(),
            'total' => $pagination->total(),
            'previousUrl' => $pagination->previousPageUrl(),
            'nextUrl' => $pagination->nextPageUrl(),
        ];
    }

    private function queueVisit(Kunjungan $visit, Request $request): array
    {
        return [
            'id' => $visit->id,
            'number' => $visit->no_kunjungan,
            'date' => $visit->tanggal->format('d/m/Y'),
            'patient' => $visit->pasien?->nama ?? 'Belum diisi',
            'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
            'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $visit->dokter?->nama,
            'status' => $visit->status,
            'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
            'isReservation' => $visit->tanggal->isFuture() && $visit->status === 'menunggu',
            'actions' => $this->queueActions($visit, $request),
        ];
    }

    /** @return array<int, array{action: string, label: string, href: ?string, disabled: bool, disabledReason: ?string, method?: string}> */
    private function queueActions(Kunjungan $visit, Request $request): array
    {
        $action = static fn (string $type, string $label, string $href, bool $available = true, string $reason = ''): array => [
            'action' => $type, 'label' => $label,
            'href' => $available ? $href : null,
            'disabled' => ! $available,
            'disabledReason' => $available ? null : $reason,
        ];
        $actions = [$action('detail', 'Detail kunjungan', route('pelayanan.kunjungan.show', $visit))];
        if ($request->user()->role !== 'admin') {
            return $actions;
        }
        $supportedClinic = $visit->poliklinik?->is_active && in_array($visit->poliklinik->jenis, ['umum', 'gigi'], true);
        $canServe = $supportedClinic && ! $visit->tanggal->isFuture();
        $serviceReason = ! $supportedClinic ? 'Poliklinik tidak aktif atau tidak mendukung layanan ini.' : 'Reservasi baru dapat dilayani pada tanggal kunjungan.';
        $actions[] = $action('rme', 'Rekam medis pasien', route('pelayanan.pasien.rekam-medis', $visit->pasien_id), $visit->pasien !== null, 'Data pasien tidak tersedia.');
        $actions[] = $action('screening', $visit->screening ? 'Perbarui pengkajian' : 'Mulai pengkajian', route('pelayanan.screening.show', $visit),
            $canServe && $visit->canUpdateScreening(), ! $canServe ? $serviceReason : 'Pengkajian hanya dapat diubah sebelum dokter memulai pemeriksaan.');
        $actions[] = $action('print', 'Cetak pengkajian', route('pelayanan.screening.print', $visit),
            $canServe && $visit->screening?->submitted_at !== null, ! $canServe ? $serviceReason : 'Pengkajian belum dikirim.');
        $actions[] = $action('examination', $visit->status === 'pemeriksaan' ? 'Buka pemeriksaan' : 'Lihat pemeriksaan', route('pelayanan.pemeriksaan.show', $visit),
            $canServe && $visit->canOpenExamination(), ! $canServe ? $serviceReason : 'Pemeriksaan tersedia setelah pengkajian dikirim dan kunjungan tidak dibatalkan.');
        $actions[] = $action('procedure', $visit->status === 'tindakan' ? 'Proses tindakan perawat' : 'Lihat tindakan perawat', route('pelayanan.tindakan.show', $visit),
            $canServe && $visit->has_nurse_procedures && $visit->pemeriksaan?->signed_at !== null,
            ! $canServe ? $serviceReason : 'Memerlukan instruksi tindakan perawat dan pemeriksaan yang sudah ditandatangani dokter.');
        $actions[] = $action('pharmacy', $visit->farmasi?->status === 'selesai' ? 'Lihat penyerahan obat' : 'Proses penyerahan obat', route('pelayanan.farmasi.show', $visit),
            $canServe && ($visit->status === 'farmasi' || $visit->farmasi?->status === 'selesai'),
            ! $canServe ? $serviceReason : 'Penyerahan obat tersedia pada tahap farmasi atau untuk melihat penyerahan yang sudah selesai.');
        $actions[] = $action('issue', 'Persuratan kunjungan', route('persuratan.index', ['kunjungan_id' => $visit->id]));
        $actions[] = $action('edit', 'Ubah kunjungan', route('pelayanan.kunjungan.edit', $visit), $visit->status === 'menunggu', 'Hanya kunjungan yang belum dilayani yang dapat diubah.');
        $actions[] = [...$action('cancel', 'Batalkan kunjungan', route('pelayanan.kunjungan.batal', $visit), $visit->status === 'menunggu', 'Hanya kunjungan yang belum dilayani yang dapat dibatalkan.'), 'method' => 'post'];

        return $actions;
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
        $canScreen = $role === 'perawat' && $kunjungan->canUpdateScreening();
        $fallbackBackUrl = $isRegistrationPage
            ? route('pendaftaran.kunjungan-per-poli')
            : ($role === 'perawat' ? route('perawat.dashboard') : route('pelayanan.kunjungan.index'));

        return Inertia::render('pelayanan/kunjungan/show', [
            'backUrl' => $this->contextualVisitBackUrl($request, $fallbackBackUrl),
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
                'examinationStarted' => $kunjungan->pemeriksaan?->started_at !== null,
                'screeningSubmitted' => $kunjungan->screening?->submitted_at !== null,
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

    private function contextualVisitBackUrl(Request $request, string $fallbackUrl): string
    {
        $referer = $request->headers->get('referer');
        $parts = $referer ? parse_url($referer) : false;
        if (! is_array($parts)) {
            return $fallbackUrl;
        }

        $scheme = $parts['scheme'] ?? null;
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $parts['path'] ?? '/';
        if (! in_array($scheme, ['http', 'https'], true)
            || ($parts['host'] ?? null) !== $request->getHost()
            || $port !== $request->getPort()
            || $path === parse_url($request->url(), PHP_URL_PATH)
            || ! $this->isAllowedVisitReferrer($request->user()->role, $path)) {
            return $fallbackUrl;
        }

        parse_str($parts['query'] ?? '', $query);
        $safeQuery = array_intersect_key($query, array_flip([
            'search', 'q', 'tanggal', 'status', 'poliklinik_id', 'dari', 'sampai',
            'page', 'recent_page', 'today_page', 'scope',
        ]));
        $queryString = http_build_query($safeQuery);

        return $request->getSchemeAndHttpHost().$path.($queryString !== '' ? '?'.$queryString : '');
    }

    private function isAllowedVisitReferrer(string $role, string $path): bool
    {
        $allowedPaths = match ($role) {
            'admin' => [
                '/pendaftaran/kunjungan-per-poli', '/pelayanan/kunjungan', '/pelayanan/antrian',
                '/pelayanan/pasien', '/laporan/kunjungan', '/pendaftaran/laporan-kunjungan', '/admin/laporan/pengkajian',
                '/admin/laporan/tindakan-perawat', '/admin/laporan/pemeriksaan', '/admin/laporan/dokter/tindakan', '/admin/laporan/dokter/obat',
            ],
            'perawat' => [
                '/pendaftaran/kunjungan-per-poli', '/pelayanan/kunjungan', '/pelayanan/pasien',
                '/perawat/dashboard', '/perawat/laporan-pengkajian', '/perawat/laporan-tindakan',
                '/pelayanan/screening/riwayat',
            ],
            'pendaftaran' => [
                '/pendaftaran', '/pendaftaran/kunjungan-per-poli', '/pendaftaran/laporan-kunjungan',
                '/pendaftaran/database-pasien',
            ],
            default => [],
        };

        if (in_array($path, $allowedPaths, true)) {
            return true;
        }

        return in_array($role, ['admin', 'perawat'], true)
            && preg_match('/^\/pelayanan\/pasien\/\d+(?:\/rekam-medis)?$/', $path) === 1;
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
