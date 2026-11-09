<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\ClinicDocumentProfile;
use App\CorrespondenceService;
use App\Exports\ScreeningExport;
use App\GeneralAssessment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningRequest;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\PatientAllergyHistory;
use App\PatientClinicalHistory;
use App\PerawatClinicResolver;
use App\ScreeningReportQuery;
use App\VisitSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ScreeningController extends Controller
{
    public function __construct(private PerawatClinicResolver $perawatClinicResolver, private VisitSchedulingService $scheduling, private PatientAllergyHistory $allergies, private PatientClinicalHistory $clinicalHistory) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $isNurse = $request->user()->role === 'perawat';
        $assignedClinicId = $isNurse ? $this->perawatClinicResolver->clinicId($request->user()) : null;
        $selectedClinicId = $assignedClinicId ?? ($filters['poliklinik_id'] ?? null);
        $clinicVisits = Kunjungan::query()
            ->when($selectedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId));
        $allVisits = (clone $clinicVisits)
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($matches) => $matches
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn ($patients) => $patients->where(fn ($identity) => $identity
                    ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))))
            ->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan'])
            ->orderByDesc('tanggal')->latest('created_at')->latest('id')
            ->paginate(10)->withQueryString();
        $todayVisits = (clone $clinicVisits)->whereDate('tanggal', today())
            ->whereIn('status', ['menunggu', 'screening'])
            ->whereDoesntHave('screening', fn ($query) => $query->whereNotNull('submitted_at'))
            ->with(['pasien', 'poliklinik', 'screening', 'pemeriksaan'])
            ->orderBy('created_at')->orderBy('id')
            ->paginate(10, ['*'], 'today_page')->withQueryString();

        $clinics = $assignedClinicId
            ? Poliklinik::registrable()->whereKey($assignedClinicId)->orderBy('nama')->get(['id', 'nama'])
            : Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama']);

        $todaySummary = (clone $clinicVisits)->whereDate('tanggal', today());
        $mapVisit = function (Kunjungan $visit) use ($isNurse): array {
            $canScreen = $visit->tanggal?->isToday() && ! $visit->screening?->submitted_at
                && in_array($visit->status, ['menunggu', 'screening'], true);
            $showVisitDetails = ! $isNurse || in_array($visit->status, ['menunggu', 'batal'], true) || $visit->tanggal?->isFuture();

            return [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'date' => $visit->tanggal?->format('d/m/Y') ?? 'Belum diisi',
                'patient' => $visit->pasien?->nama ?? 'Belum diisi',
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi',
                'status' => $visit->status,
                'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
                'hasScreening' => $visit->screening !== null,
                'screeningState' => $visit->screening?->submitted_at ? 'Terkirim' : ($visit->screening || $visit->status === 'screening' ? 'Dalam proses' : 'Belum dikaji'),
                'screeningUrl' => route('pelayanan.screening.show', $visit),
                'canUpdateScreening' => $visit->canUpdateScreening(),
                'visitUrl' => route('pelayanan.kunjungan.show', $visit),
                'recordUrl' => $visit->pasien_id ? route('pelayanan.pasien.rekam-medis', $visit->pasien_id) : null,
                'actionUrl' => $visit->canUpdateScreening() ? route('pelayanan.screening.show', $visit)
                    : ($showVisitDetails ? route('pelayanan.kunjungan.show', $visit) : route('pelayanan.pasien.rekam-medis', $visit->pasien_id)),
                'actionLabel' => $visit->status === 'pemeriksaan' && $visit->canUpdateScreening() ? 'Perbarui pengkajian'
                    : ($canScreen ? ($visit->status === 'screening' ? 'Lanjutkan pengkajian' : 'Mulai pengkajian')
                        : ($showVisitDetails ? 'Detail kunjungan' : 'Lihat RME')),
                'printUrl' => $visit->screening ? route('pelayanan.screening.print', $visit) : null,
            ];
        };

        return Inertia::render('pelayanan/screening/index', [
            'serviceName' => $assignedClinicId ? $clinics->first()?->nama : 'Semua poli',
            'stats' => [
                'total' => (clone $todaySummary)->count(),
                'waiting' => (clone $todaySummary)->whereIn('status', ['menunggu', 'screening'])
                    ->whereDoesntHave('screening', fn ($query) => $query->whereNotNull('submitted_at'))->count(),
                'submitted' => (clone $todaySummary)->whereHas('screening', fn ($query) => $query->whereDate('submitted_at', today()))->count(),
                'allPending' => (clone $clinicVisits)->whereIn('status', ['menunggu', 'screening'])
                    ->whereDoesntHave('screening', fn ($query) => $query->whereNotNull('submitted_at'))->count(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tanggal' => $filters['tanggal'] ?? '',
                'poliklinikId' => $selectedClinicId ? (int) $selectedClinicId : '',
            ],
            'canSelectClinic' => ! $isNurse,
            'clinics' => $clinics->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'todayVisits' => $this->pageData($todayVisits->through($mapVisit)),
            'visits' => $this->pageData($allVisits->through($mapVisit)),
        ]);
    }

    public function history(Request $request, ClinicalAuditRecorder $auditRecorder): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        $assignedClinicId = $nurse?->poliklinik_id;
        $selectedClinicId = $assignedClinicId ?? ($filters['poliklinik_id'] ?? null);
        $query = (new ScreeningReportQuery)->query($filters, $nurse?->id, $selectedClinicId)
            ->paginate(10)
            ->withQueryString();
        $clinics = $assignedClinicId
            ? Poliklinik::registrable()->whereKey($assignedClinicId)->orderBy('nama')->get(['id', 'nama'])
            : Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama']);

        $isReport = $request->routeIs('perawat.laporan-pengkajian', 'admin.laporan.pengkajian');
        $reportRoute = $request->user()->isAdmin() ? 'admin.laporan.pengkajian' : 'perawat.laporan-pengkajian';
        $exportRoute = $request->user()->isAdmin() ? 'admin.laporan.pengkajian.export' : 'perawat.laporan-pengkajian.export';
        if ($request->user()->isAdmin() && $isReport) {
            $auditRecorder->record($request, 'admin.report.screening.view', metadata: ['clinic_id' => $selectedClinicId, 'filters' => $filters]);
        }

        return Inertia::render('pelayanan/screening/history', [
            'isReport' => $isReport,
            'serviceName' => $nurse?->poliklinik?->nama ?? 'Semua poli',
            'exportUrl' => $isReport ? route($exportRoute, $filters) : null,
            'baseUrl' => route($reportRoute),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tanggal' => $filters['tanggal'] ?? '',
                'poliklinikId' => $selectedClinicId ? (int) $selectedClinicId : '',
            ],
            'canSelectClinic' => ! $isNurse,
            'clinics' => $clinics->map(fn (Poliklinik $clinic): array => ['id' => $clinic->id, 'name' => $clinic->nama])->values(),
            'screenings' => [
                'data' => $query->getCollection()->map(fn (Screening $screening): array => [
                    'id' => $screening->id,
                    'date' => $screening->kunjungan?->tanggal?->format('d/m/Y') ?? 'Belum diisi',
                    'visitNumber' => $screening->kunjungan?->no_kunjungan ?? 'Belum diisi',
                    'patient' => $screening->kunjungan?->pasien?->nama ?? 'Belum diisi',
                    'medicalRecordNumber' => $screening->kunjungan?->pasien?->no_rm ?? 'Belum diisi',
                    'clinic' => $screening->kunjungan?->poliklinik?->nama ?? 'Belum diisi',
                    'staff' => $screening->petugas?->nama ?? 'Belum diisi',
                    'triage' => $screening->kesimpulan_triase,
                    'priority' => $screening->prioritas_layanan,
                    'visitUrl' => $screening->kunjungan ? route('pelayanan.kunjungan.show', $screening->kunjungan) : null,
                    'printUrl' => $screening->kunjungan ? route('pelayanan.screening.print', $screening->kunjungan) : null,
                    'isDraft' => $screening->submitted_at === null && ($screening->started_at !== null || in_array($screening->kunjungan?->status, ['menunggu', 'screening'], true)),
                    'editUrl' => $screening->kunjungan?->canUpdateScreening() ? route('pelayanan.screening.show', $screening->kunjungan) : null,
                ])->values(),
                'currentPage' => $query->currentPage(),
                'lastPage' => $query->lastPage(),
                'from' => $query->firstItem(),
                'to' => $query->lastItem(),
                'total' => $query->total(),
                'previousUrl' => $query->previousPageUrl(),
                'nextUrl' => $query->nextPageUrl(),
            ],
        ]);
    }

    public function exportReport(Request $request, ClinicalAuditRecorder $auditRecorder): BinaryFileResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $nurse = $request->user()->isAdmin() ? null : $this->perawatClinicResolver->nakes($request->user());

        $clinicId = $nurse?->poliklinik_id ?? ($filters['poliklinik_id'] ?? null);
        $auditRecorder->record($request, 'screening.report_export', metadata: ['clinic_id' => $clinicId, ...($nurse ? ['staff_id' => $nurse->id] : []), 'filters' => $filters]);

        return Excel::download(new ScreeningExport($filters, $nurse), 'laporan-pengkajian-'.($nurse?->poliklinik?->jenis ?? 'semua-poli').'-'.today()->format('Y-m-d').'.xlsx');
    }

    public function start(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $kunjungan->loadMissing('poliklinik');
        abort_unless(in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        abort_if($nurse && (int) $kunjungan->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
        if (is_string($request->input('assistance_reason'))) {
            $request->merge(['assistance_reason' => trim($request->input('assistance_reason'))]);
        }
        $data = $request->validate([
            'assistance_reason' => [Rule::excludeIf($isNurse), 'required', 'string', 'min:10', 'max:1000'],
            'petugas_id' => [Rule::excludeIf($isNurse), 'required', Rule::exists('nakes', 'id')->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('poliklinik_id', $kunjungan->poliklinik_id)->where('is_active', true)->whereNull('deleted_at')],
        ]);
        DB::transaction(function () use ($kunjungan, $nurse, $data, $request, $auditRecorder): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_if($nurse && (int) $visit->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
            $this->scheduling->ensureServiceDate($visit);
            abort_unless(in_array($visit->status, ['menunggu', 'screening'], true), 422, 'Kunjungan tidak lagi berada di antrean pengkajian awal.');
            if (! $nurse) {
                abort_unless(Nakes::whereKey($data['petugas_id'])->where('poliklinik_id', $visit->poliklinik_id)->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('is_active', true)->exists(), 422);
                $auditRecorder->record($request, 'admin_assistance.screening_start', $visit->pasien_id, $visit->id, ['reason' => $data['assistance_reason'], 'staff_id' => (int) $data['petugas_id'], 'clinic_id' => $visit->poliklinik_id]);
            }
            if ($visit->status === 'menunggu' || $visit->screening?->started_at === null) {
                Screening::updateOrCreate(['kunjungan_id' => $visit->id], [
                    'petugas_id' => $nurse?->id ?? $data['petugas_id'],
                    'started_at' => now(),
                    'recorded_by_user_id' => $request->user()->id,
                ]);
                $visit->update(['status' => 'screening']);
                $auditRecorder->record($request, 'screening.started', $visit->pasien_id, $visit->id);
            }
        });

        return redirect()->route('pelayanan.screening.show', $kunjungan)->with('success', 'Pengkajian awal dimulai. Pastikan identitas pasien sesuai sebelum melakukan pemeriksaan.');
    }

    public function print(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder, CorrespondenceService $correspondence): HttpResponse
    {
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'screening.petugas.user', 'screening.recordedBy']);
        if ($request->user()->role === 'perawat') {
            abort_unless((int) $kunjungan->poliklinik_id === $this->perawatClinicResolver->clinicId($request->user()), 404);
        }
        abort_unless($kunjungan->screening && in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $auditRecorder->record($request, 'screening.print', $kunjungan->pasien_id, $kunjungan->id);

        $isDraft = $kunjungan->screening->submitted_at === null && ($kunjungan->screening->started_at !== null || in_array($kunjungan->status, ['menunggu', 'screening'], true));

        $screening = $kunjungan->screening;
        $choiceLabels = [
            'ya' => 'Ya', 'tidak' => 'Tidak', 'teraba' => 'Teraba', 'tidak_teraba' => 'Tidak teraba',
            'normal' => 'Normal', 'tidak_normal' => 'Tidak normal', 'sadar' => 'Sadar', 'menurun' => 'Menurun',
            'tidak_sadar' => 'Tidak sadar', 'terganggu' => 'Terganggu', 'rendah' => 'Rendah', 'sedang' => 'Sedang',
            'tinggi' => 'Tinggi', 'ada' => 'Ada riwayat', 'tidak_ada' => 'Tidak ada (telah dikaji)',
            'belum_diketahui' => 'Belum dapat diketahui', 'pasien' => 'Pasien sendiri', 'keluarga' => 'Keluarga',
            'pendamping' => 'Pendamping', 'tidak_dapat_diperoleh' => 'Belum dapat diperoleh',
            'hipertensi' => 'Hipertensi', 'diabetes' => 'Diabetes melitus', 'penyakit_jantung' => 'Penyakit jantung',
            'gangguan_pembekuan' => 'Gangguan pembekuan darah', 'antikoagulan' => 'Obat antikoagulan',
            'lainnya' => 'Lainnya', 'hepatitis' => 'Hepatitis', 'hiv' => 'HIV', 'infeksi_lain' => 'Infeksi lain',
            'dingin' => 'Dingin', 'manis' => 'Manis', 'panas' => 'Panas', 'spontan' => 'Spontan', 'mengunyah' => 'Mengunyah',
            'kurang' => 'Gizi kurang', 'lebih' => 'Gizi lebih',
            'merah' => 'Merah - perlu respons segera', 'kuning' => 'Kuning - perlu penilaian lanjut', 'hijau' => 'Hijau - prioritas rutin',
            'darurat' => 'Respons darurat', 'segera' => 'Penilaian segera',
        ];
        $choice = static fn (?string $value): string => $choiceLabels[$value] ?? (filled($value) ? $value : 'Belum dikaji');
        $choices = static fn (?array $values): string => $values ? implode(', ', array_map($choice, $values)) : 'Belum dikaji';
        $presentRows = static fn (array $rows): array => array_map(static fn (mixed $value): mixed => filled($value) ? $value : 'Belum dikaji / belum dicatat', $rows);
        $joinValues = static fn (?string ...$values): ?string => ($joined = implode(' — ', array_filter($values, static fn (?string $value): bool => filled($value)))) !== '' ? $joined : null;

        $generalRows = $kunjungan->poliklinik?->jenis === 'umum' ? $screening->generalAssessmentSummary() : GeneralAssessment::handoverSummary($screening->general_assessment);
        $generalPrintRows = [];
        foreach ($generalRows as $label => $value) {
            $legacy = str_contains($label, '(catatan sebelumnya)');
            $generalPrintRows[str_replace('(catatan sebelumnya)', '(catatan lama)', $label)] = filled($value) ? $value : ($legacy ? 'Tidak tercatat pada catatan lama' : 'Belum dikaji / belum dicatat');
        }

        return Inertia::render('pelayanan/screening/print', [
            'document' => [
                'clinic' => app(ClinicDocumentProfile::class)->data(),
                'patient' => [
                    'name' => $kunjungan->pasien?->nama,
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm,
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                    'address' => $kunjungan->pasien?->alamat,
                ],
                'visitNumber' => $kunjungan->no_kunjungan,
                'clinicName' => $kunjungan->poliklinik?->nama ?? 'Poliklinik',
                'clinicType' => $kunjungan->poliklinik?->jenis,
                'doctorName' => $kunjungan->dokter?->nama,
                'nurseName' => $screening->petugas?->nama,
                'versions' => $screening->versionSummaries(),
                'visitStatus' => $kunjungan->status,
                'isDraft' => $isDraft,
                'startedAt' => $screening->started_at?->format('d/m/Y H:i'),
                'submittedAt' => $screening->submitted_at?->format('d/m/Y H:i'),
                'printDate' => $screening->submitted_at?->locale('id')->isoFormat('D MMMM Y') ?? now()->locale('id')->isoFormat('D MMMM Y'),
                'printedAt' => now()->format('d/m/Y H:i'),
                'recordedBy' => $screening->recordedBy?->name,
                'signatureDataUrl' => $isDraft || ($screening->recorded_by_user_id !== null && $screening->recorded_by_user_id !== $screening->petugas?->user_id) ? null : $correspondence->signatureDataUrl($screening->petugas?->user),
                'backUrl' => route('pelayanan.screening.index'),
                'sections' => [
                    'history' => $presentRows([
                        'Sumber informasi' => filled($screening->sumber_informasi) ? $choice($screening->sumber_informasi) : null,
                        'Keluhan utama' => $screening->keluhan,
                        'Lama keluhan' => $screening->lama_keluhan,
                        'Keluhan tambahan' => $screening->keluhan_tambahan,
                        'Status riwayat penyakit' => filled($screening->status_riwayat_penyakit) ? $choice($screening->status_riwayat_penyakit) : null,
                        'Riwayat penyakit' => $screening->status_riwayat_penyakit === 'belum_diketahui' ? null : $joinValues(
                            $screening->reportedMedicalHistory(),
                            $screening->handoverSummary()['Nama penyakit'],
                            $screening->handoverSummary()['Keterangan riwayat penyakit'],
                        ),
                        'Obat yang digunakan' => $screening->riwayat_obat,
                        'Kategori obat yang dilaporkan' => $screening->handoverSummary()['Kategori obat yang dilaporkan'] ?? null,
                        'Riwayat penyakit keluarga' => $screening->riwayat_penyakit_keluarga,
                        'Status alergi' => filled($screening->status_alergi) ? $choice($screening->status_alergi) : null,
                        'Riwayat alergi' => $screening->riwayat_alergi,
                        'Jenis / reaksi alergi' => $joinValues($screening->alergi_jenis, $screening->alergi_reaksi),
                    ]),
                    'vitals' => $presentRows([
                        'Waktu pencatatan TTV' => $screening->vitals_measured_at?->format('d/m/Y H:i'),
                        'Petugas pengukur' => $screening->vitals_measured_at ? $screening->petugas?->nama : null,
                        'Tekanan sistolik' => $screening->td_sistole !== null ? $screening->td_sistole.' mmHg' : null,
                        'Tekanan diastolik' => $screening->td_diastole !== null ? $screening->td_diastole.' mmHg' : null,
                        'Nadi' => $screening->nadi !== null ? $screening->nadi.' kali/menit' : null,
                        'Suhu tubuh' => $screening->suhu !== null ? $screening->suhu.' °C' : null,
                        'Frekuensi napas' => $screening->respirasi !== null ? $screening->respirasi.' kali/menit' : null,
                        'Saturasi oksigen' => $screening->spo2 !== null ? $screening->spo2.'%' : null,
                    ]),
                    'measurements' => $presentRows([
                        'Berat badan' => $screening->berat_badan !== null ? $screening->berat_badan.' kg' : null,
                        'Tinggi badan' => $screening->tinggi_badan !== null ? $screening->tinggi_badan.' cm' : null,
                        'Indeks massa tubuh' => $screening->imt !== null ? $screening->imt.' kg/m²' : null,
                        'Lingkar perut' => $screening->lingkar_perut !== null ? $screening->lingkar_perut.' cm' : null,
                    ]),
                    'measurementNote' => $screening->alasan_pengukuran_tidak_lengkap,
                    'triage' => $presentRows([
                        'Nyeri dada' => filled($screening->nyeri_dada) ? $choice($screening->nyeri_dada) : null,
                        'Kejang' => filled($screening->kejang) ? $choice($screening->kejang) : null,
                        'Nadi teraba' => filled($screening->nadi_teraba) ? $choice($screening->nadi_teraba) : null,
                        'Pola pernapasan' => filled($screening->pola_pernapasan) ? $choice($screening->pola_pernapasan) : null,
                        'Kesadaran' => filled($screening->kesadaran) ? $choice($screening->kesadaran) : null,
                        'Kondisi psikiatri' => filled($screening->kondisi_psikiatri) ? $choice($screening->kondisi_psikiatri) : null,
                        'Risiko jatuh' => filled($screening->risiko_jatuh_visual) ? $choice($screening->risiko_jatuh_visual) : null,
                        'Skala nyeri' => $screening->skala_nyeri !== null ? $screening->skala_nyeri.' / 10' : null,
                        'Status pengukuran TTV' => $screening->vitalMeasurementSummary(),
                        'Kebutuhan respons awal' => filled($screening->kesimpulan_triase) ? $choice($screening->kesimpulan_triase).' · '.$choice($screening->prioritas_layanan) : null,
                        'Catatan penapisan' => $screening->catatan_triase,
                    ]),
                    'dental' => $kunjungan->poliklinik?->jenis === 'gigi' ? $presentRows([
                        'Kondisi kesehatan yang perlu diketahui dokter gigi' => $screening->risiko_medis_gigi ? $choices($screening->risiko_medis_gigi) : null,
                        ...$screening->dentalObservationSummary(),
                        ...array_filter($screening->handoverSummary(), fn ($value, $key): bool => str_starts_with($key, 'Observasi mukosa') || in_array($key, ['Kelainan mukosa yang ditemukan', 'Kebiasaan gigi dan mulut yang dilaporkan', 'Status penggunaan obat'], true), ARRAY_FILTER_USE_BOTH),
                        ...array_intersect_key($screening->handoverSummary(), array_flip(['Keterangan tanda bahaya lainnya', 'Keterangan kondisi sistemik lainnya', 'Keterangan pemicu lainnya', 'Keterangan infeksi lainnya', 'Keterangan obat lainnya', 'Tingkat keparahan alergi jika diketahui', 'Catatan verifikasi alergi', 'Karakter dan penjalaran nyeri', 'Hal yang mengurangi nyeri', 'Dampak nyeri pada aktivitas atau tidur', 'Kondisi khusus, operasi, atau rawat inap yang relevan', 'Observasi awal mulut dan rahang', 'Perawatan mulut dan kebiasaan sehari-hari'])),
                        'Riwayat perawatan gigi' => $screening->riwayat_perawatan_gigi,
                        'Kebiasaan terkait gigi dan mulut' => $screening->kebiasaan_gigi,
                        'Lokasi keluhan / nyeri' => $screening->lokasi_nyeri_gigi,
                        'Pemicu keluhan' => $screening->pemicu_nyeri_gigi ? $choices($screening->pemicu_nyeri_gigi) : null,
                        'Pola / lama keluhan' => $screening->durasi_keluhan_gigi,
                        'Riwayat infeksi atau bengkak' => $screening->riwayat_infeksi_gigi ? $choices($screening->riwayat_infeksi_gigi) : null,
                        'Catatan untuk dokter gigi' => $screening->catatan_medis_gigi,
                    ]) : [],
                    'general' => $generalPrintRows,
                    'additional' => $presentRows([
                        'Pengkajian gizi' => filled($screening->skrining_gizi) ? $choice($screening->skrining_gizi) : null,
                        'Observasi awal' => $screening->pemeriksaan_fisik,
                    ]),
                ],
            ],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): Response
    {
        $this->scheduling->ensureServiceDate($kunjungan);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'screening.petugas', 'pemeriksaan']);
        abort_unless(in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        abort_if($nurse && (int) $kunjungan->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
        abort_unless($kunjungan->canUpdateScreening(), 422, 'Pengkajian hanya dapat diperbarui sebelum dokter memulai pemeriksaan.');
        $auditRecorder->record($request, 'screening.view', $kunjungan->pasien_id, $kunjungan->id);
        $petugasList = Nakes::whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('is_active', true)
            ->where('poliklinik_id', $kunjungan->poliklinik_id)->orderBy('nama')->get();

        $screeningFields = [
            'vitals_unmeasurable', 'general_assessment', 'vitals_measured_at', 'triaged_at',
            'status_riwayat_obat', 'mukosa_mulut', 'kelainan_mukosa', 'kebiasaan_gigi_opsi',
            ...Screening::SAFETY_FIELDS,
            'extraoral_symmetry', 'extraoral_swelling', 'extraoral_notes', 'dental_red_flags', 'dental_medication_categories', 'dental_vitals_unmeasurable',
            'petugas_id', 'keluhan', 'td_sistole', 'td_diastole', 'nadi', 'suhu', 'berat_badan', 'tinggi_badan',
            'lingkar_perut', 'spo2', 'respirasi', 'riwayat_penyakit', 'riwayat_alergi', 'risiko_jatuh',
            'risiko_nyeri', 'skrining_gizi', 'alergi_jenis', 'alergi_reaksi', 'penyakit_nama',
            'penyakit_keterangan', 'nyeri_dada', 'kondisi_psikiatri', 'nadi_teraba', 'kejang',
            'pola_pernapasan', 'kesadaran', 'risiko_jatuh_visual',
            'riwayat_penyakit_keluarga', 'skala_nyeri', 'lokasi_nyeri_gigi', 'pemicu_nyeri_gigi',
            'durasi_keluhan_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi', 'catatan_medis_gigi',
            'riwayat_perawatan_gigi', 'kebiasaan_gigi', 'keluhan_tambahan', 'lama_keluhan', 'riwayat_obat', 'status_alergi',
            'status_riwayat_penyakit', 'sumber_informasi', 'alasan_pengukuran_tidak_lengkap',
            'triase_perawat', 'catatan_triase', 'pemeriksaan_fisik',
        ];
        $screeningValues = array_combine(
            $screeningFields,
            array_map(fn (string $field): mixed => $kunjungan->screening?->getAttribute($field), $screeningFields),
        );
        foreach ([...$this->allergies->forVisit($kunjungan), ...$this->clinicalHistory->forVisit($kunjungan)] as $field => $value) {
            if ($field === 'riwayat_obat' && ($kunjungan->poliklinik?->jenis === 'gigi' || in_array($screeningValues['status_riwayat_obat'] ?? null, ['tidak_ada', 'belum_diketahui'], true))) {
                continue;
            }
            if ($field === 'riwayat_penyakit' && $screeningValues['status_riwayat_penyakit'] === 'belum_diketahui') {
                $screeningValues[$field] = null;

                continue;
            }
            if (blank($screeningValues[$field])) {
                $screeningValues[$field] = $value;
            }
        }

        return Inertia::render('pelayanan/screening/show', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'examinationStarted' => $kunjungan->pemeriksaan?->started_at !== null,
                'editable' => $kunjungan->canUpdateScreening(),
                'submitted' => $kunjungan->screening?->submitted_at !== null,
                'patient' => [
                    'name' => $kunjungan->pasien?->nama ?? 'Pasien',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? 'Belum diisi',
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                    'bloodType' => $kunjungan->pasien?->golongan_darah,
                    'allergyHistory' => $this->allergies->recordedBeforeVisit($kunjungan)['riwayat_alergi'] ?? $screeningValues['riwayat_alergi'],
                ],
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'clinicType' => $kunjungan->poliklinik?->jenis,
                'doctor' => $kunjungan->dokter?->nama ?? 'Belum ditentukan',
                'screening' => [...$screeningValues, 'vitals_measured_at' => $kunjungan->screening?->vitals_measured_at?->format('Y-m-d\\TH:i'), 'triaged_at' => $kunjungan->screening?->triaged_at?->format('Y-m-d\\TH:i')],
                'versions' => $kunjungan->screening?->versionSummaries() ?? [],
                'screeningRevision' => $kunjungan->screening?->revisionToken() ?? 'new',
                'medicationCategories' => Screening::dentalMedicationCategories(),
                'oralHabitOptions' => Screening::ORAL_HABITS,
                'triage' => $kunjungan->screening?->kesimpulan_triase,
                'priority' => $kunjungan->screening?->prioritas_layanan,
                'printUrl' => $kunjungan->screening ? route('pelayanan.screening.print', $kunjungan) : null,
                'startedAt' => $kunjungan->screening?->started_at?->format('d/m/Y H:i'),
                'vitalsRecordedAt' => $kunjungan->screening?->vitals_measured_at?->format('d/m/Y H:i'),
                'triageRecordedAt' => $kunjungan->screening?->triaged_at?->format('d/m/Y H:i'),
                'submittedAt' => $kunjungan->screening?->submitted_at?->format('d/m/Y H:i'),
                'submittedBy' => $kunjungan->screening?->submittedBy?->name,
                'reviewedAt' => $kunjungan->pemeriksaan?->signed_at?->format('d/m/Y H:i'),

                'attachments' => $kunjungan->attachmentSummaries(),
                'attachmentUploadUrl' => route('pelayanan.kunjungan.attachments.store', $kunjungan),
            ],
            'staff' => $petugasList->map(fn (Nakes $staff): array => [
                'id' => $staff->id,
                'name' => $staff->nama,
                'position' => $staff->jabatan === 'terapis_gigi' ? 'Terapis gigi dan mulut' : ucfirst($staff->jabatan),
            ])->values(),
            'canSelectStaff' => ! $isNurse,
            'currentStaff' => $nurse?->nama ?? $kunjungan->screening?->petugas?->nama,
        ]);
    }

    public function store(StoreScreeningRequest $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->scheduling->ensureServiceDate($kunjungan);
        $kunjungan->loadMissing(['poliklinik', 'pasien']);
        $isDental = $kunjungan->poliklinik?->jenis === 'gigi';
        abort_unless(in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        abort_if($nurse && (int) $kunjungan->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
        abort_unless($kunjungan->canUpdateScreening(), 422, 'Pengkajian hanya dapat diperbarui sebelum dokter memulai pemeriksaan.');

        $data = $request->validated();
        $isDraft = ($data['tindakan'] ?? 'kirim') === 'draf';
        $revision = $data['screening_revision'] ?? null;
        $revisionReason = $data['revision_reason'] ?? null;
        $requestId = $data['request_id'] ?? null;
        $requestData = $request->validated();
        ksort($requestData);
        $requestHash = hash('sha256', json_encode($requestData, JSON_THROW_ON_ERROR));
        unset($data['request_id']);
        unset($data['screening_revision'], $data['revision_reason']);
        unset($data['tindakan'], $data['konfirmasi_riwayat'], $data['assistance_reason']);
        foreach (Screening::SAFETY_FIELDS as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        foreach (Screening::OTHER_DETAILS as $field => [$option, $detail]) {
            if (! $isDental || ! in_array($option, $data[$field] ?? [], true)) {
                $data[$detail] = null;
            }
        }
        $data['dental_medication_categories'] = $isDental ? ($data['dental_medication_categories'] ?? null) : null;
        foreach (['mukosa_mulut', 'kelainan_mukosa', 'kebiasaan_gigi_opsi'] as $field) {
            $data[$field] = $isDental && filled($data[$field] ?? null) ? $data[$field] : null;
        }
        if (in_array($data['status_riwayat_obat'] ?? null, ['tidak_ada', 'belum_diketahui'], true)) {
            $data['riwayat_obat'] = null;
            $data['dental_medication_categories'] = $isDental ? [($data['status_riwayat_obat'] === 'tidak_ada' ? 'none' : 'unknown')] : null;
            $data['dental_medication_categories_other'] = null;
        }
        $data['recorded_by_user_id'] = $request->user()->id;

        foreach (['pemicu_nyeri_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi', 'dental_red_flags'] as $field) {
            if (in_array('tidak_ada', $data[$field] ?? [], true) && count($data[$field] ?? []) > 1) {
                return back()->withErrors([$field => 'Pilihan Tidak ada tidak dapat digabung dengan pilihan lain.'])->withInput();
            }
        }

        foreach ([
            'keluhan', 'riwayat_perawatan_gigi', 'kebiasaan_gigi', 'keluhan_tambahan', 'lama_keluhan', 'riwayat_obat', 'riwayat_penyakit', 'penyakit_nama', 'penyakit_keterangan',
            'riwayat_alergi', 'alergi_jenis', 'alergi_reaksi', 'riwayat_penyakit_keluarga',
            'lokasi_nyeri_gigi', 'durasi_keluhan_gigi', 'catatan_medis_gigi', 'extraoral_notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim($data[$field]) : null;
            }
        }
        foreach (['pemicu_nyeri_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi', 'dental_red_flags'] as $field) {
            $data[$field] = $isDental && filled($data[$field] ?? null) ? $data[$field] : null;
        }

        if ($nurse) {
            $data['petugas_id'] = $nurse->id;
        } elseif (! empty($data['petugas_id'])) {
            abort_unless(
                Nakes::whereKey($data['petugas_id'])->where('poliklinik_id', $kunjungan->poliklinik_id)->exists(),
                422,
                'Petugas pengkajian harus ditugaskan pada poliklinik kunjungan ini.'
            );
        }

        $data['kunjungan_id'] = $kunjungan->id;
        $data['triase_perawat'] = null;
        [$data['kesimpulan_triase'], $data['prioritas_layanan']] = $this->determineTriage($data, $isDental);

        $wasSubmitted = false;
        DB::transaction(function () use ($data, $kunjungan, $request, $auditRecorder, $isDental, $isDraft, $nurse, $revision, $revisionReason, $requestId, $requestHash, &$wasSubmitted): void {
            $lockedVisit = Kunjungan::with(['screening', 'pemeriksaan'])->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_if($nurse && (int) $lockedVisit->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
            abort_unless($lockedVisit->canUpdateScreening(), 422, 'Pengkajian hanya dapat diperbarui sebelum dokter memulai pemeriksaan.');

            abort_unless($lockedVisit->screening?->started_at !== null || $lockedVisit->screening?->submitted_at !== null, 422, 'Klik Mulai pengkajian sebelum menyimpan hasil.');
            $this->scheduling->ensureServiceDate($lockedVisit);
            if ($request->user()->isAdmin()) {
                abort_unless(Nakes::whereKey($data['petugas_id'])->where('poliklinik_id', $lockedVisit->poliklinik_id)->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('is_active', true)->exists(), 422);
                $auditRecorder->record($request, 'admin_assistance.screening_write', $lockedVisit->pasien_id, $lockedVisit->id, ['reason' => $request->validated('assistance_reason'), 'staff_id' => (int) $data['petugas_id'], 'clinic_id' => $lockedVisit->poliklinik_id]);
            }
            if ($requestId && ($retry = $lockedVisit->screening?->versions()->where('request_id', $requestId)->first())) {
                if ($retry->actor_id !== $request->user()->id || $retry->request_hash !== $requestHash) {
                    throw ValidationException::withMessages(['request_id' => 'Permintaan simpan telah dipakai untuk isi berbeda. Muat ulang pengkajian.']);
                }
                $wasSubmitted = true;

                return;
            }
            if ($lockedVisit->screening?->submitted_at && (! filled($revisionReason) || mb_strlen(trim($revisionReason)) < 10)) {
                throw ValidationException::withMessages(['revision_reason' => 'Catat alasan revisi pengkajian terkirim, minimal 10 karakter.']);
            }
            if ($lockedVisit->screening?->submitted_at && $revision === null) {
                throw ValidationException::withMessages(['screening_revision' => 'Muat ulang pengkajian sebelum menyimpan revisi.']);
            }
            if ($revision !== null && $revision !== ($lockedVisit->screening?->revisionToken() ?? 'new')) {
                throw ValidationException::withMessages([
                    'screening_revision' => 'Pengkajian berubah sejak halaman dibuka. Salin isian yang diperlukan lalu muat ulang halaman.',
                ]);
            }
            $wasSubmitted = $lockedVisit->screening?->submitted_at !== null;
            abort_if($wasSubmitted && $isDraft, 409, 'Pengkajian sudah dikirim. Muat ulang sebelum memperbarui catatan.');
            // Keep historical entries from the previous form on revision.
            if ($isDental && isset($lockedVisit->screening->general_assessment['systems']) && array_key_exists('general_assessment', $data)) {
                $data['general_assessment']['systems'] = $lockedVisit->screening->general_assessment['systems'];
            }
            $data['started_at'] = $lockedVisit->screening?->started_at ?? now();
            $data['submitted_at'] = $isDraft ? null : ($lockedVisit->screening?->submitted_at ?? now());
            if (! $isDraft) {
                $data['last_submitted_at'] = now();
                if (! $wasSubmitted) {
                    $data['submitted_by_user_id'] = $request->user()->id;
                    $data['submitted_petugas_id'] = $data['petugas_id'] ?? null;
                }
            }
            // These are server recording times, not inferred historical measurement times.
            // Revisions of unrelated history must not change an observation's timestamp.
            foreach (['vitals_measured_at' => ['td_sistole', 'td_diastole', 'nadi', 'suhu', 'respirasi', 'spo2'], 'triaged_at' => ['nyeri_dada', 'kejang', 'nadi_teraba', 'pola_pernapasan', 'kesadaran', 'kondisi_psikiatri', 'risiko_jatuh_visual', 'general_assessment.general_condition', 'general_assessment.airway', 'general_assessment.circulation', 'dental_red_flags', 'extraoral_swelling']] as $timestamp => $fields) {
                $recorded = false;
                $changed = false;
                foreach ($fields as $field) {
                    if (! Arr::has($data, $field)) {
                        $oldValue = data_get($lockedVisit->screening?->attributesToArray() ?? [], $field);
                        $recorded = $recorded || ($oldValue !== null && $oldValue !== '' && $oldValue !== []);

                        continue;
                    }
                    $value = data_get($data, $field);
                    $previous = data_get($lockedVisit->screening?->attributesToArray() ?? [], $field);
                    $recorded = $recorded || ($value !== null && $value !== '' && $value !== []);
                    $same = is_numeric($value) && is_numeric($previous) ? (float) $value === (float) $previous : $value === $previous;
                    $changed = $changed || ! $same;
                }
                if ($changed && ! $recorded) {
                    $data[$timestamp] = null;
                }
                if ($recorded && ($changed || (! $wasSubmitted && $lockedVisit->screening?->getAttribute($timestamp) === null))) {
                    $data[$timestamp] = now();
                }
            }
            $data['risiko_jatuh'] = $data['risiko_jatuh_visual'] ?? null;
            $medicalHistoryStatus = array_key_exists('status_riwayat_penyakit', $data)
                ? $data['status_riwayat_penyakit']
                : $lockedVisit->screening?->status_riwayat_penyakit;
            if (in_array($medicalHistoryStatus, ['tidak_ada', 'belum_diketahui'], true)) {
                $data['riwayat_penyakit'] = $medicalHistoryStatus === 'tidak_ada' ? 'Pasien menyangkal riwayat penyakit' : null;
                $data['penyakit_nama'] = null;
                $data['penyakit_keterangan'] = null;
            }
            if (($data['status_alergi'] ?? null) === 'tidak_ada') {
                $data['riwayat_alergi'] = 'Tidak ada riwayat alergi menurut pasien';
                $data['alergi_jenis'] = null;
                $data['alergi_reaksi'] = null;
            }
            $history = $this->allergies->forVisit($lockedVisit);
            if (blank($data['riwayat_alergi'] ?? null) && filled($data['alergi_jenis'] ?? null)) {
                $data['riwayat_alergi'] = implode(' - ', array_filter([$data['alergi_jenis'], $data['alergi_reaksi'] ?? null]));
            }
            if (blank($data['riwayat_alergi'] ?? null)) {
                foreach ($history as $field => $value) {
                    $data[$field] = filled($data[$field] ?? null) ? $data[$field] : $value;
                }
            }
            foreach ($this->clinicalHistory->forVisit($lockedVisit) as $field => $value) {
                if ($field === 'riwayat_obat' && ($lockedVisit->poliklinik?->jenis === 'gigi' || in_array($data['status_riwayat_obat'] ?? null, ['tidak_ada', 'belum_diketahui'], true))) {
                    continue;
                }
                if ($field === 'riwayat_penyakit' && $medicalHistoryStatus === 'belum_diketahui') {
                    continue;
                }
                if (blank($data[$field] ?? null)) {
                    $data[$field] = $value;
                }
            }
            $recordedAllergy = $this->allergies->forVisit($lockedVisit)['riwayat_alergi'];
            if (filled($recordedAllergy) && (in_array($data['status_alergi'] ?? null, ['belum_diketahui', 'belum_dikonfirmasi', 'tidak_ada'], true) || ($data['riwayat_alergi'] ?? null) !== $recordedAllergy)) {
                $data['alergi_verifikasi'] = $data['alergi_verifikasi'] ?: 'Perlu verifikasi: riwayat alergi berbeda atau belum dikonfirmasi. Riwayat tercatat: '.mb_substr($recordedAllergy, 0, 1000);
            }
            $previous = $lockedVisit->screening;
            if ($previous?->submitted_at && ! $previous->versions()->exists()) {
                // Preserve legacy content without inventing its author or recording time.
                $previous->versions()->create(['kind' => 'legacy_snapshot', 'petugas_id' => $previous->petugas_id, 'version' => 1, 'actor_id' => null, 'reason' => 'Salinan catatan lama sebelum revisi; metadata historis tidak tersedia.', 'payload' => $previous->attributesToArray(), 'recorded_at' => now()]);
            }
            $screening = Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);
            if (! $isDraft) {
                $screening->versions()->create(['kind' => $wasSubmitted ? 'revision' : 'submitted', 'petugas_id' => $screening->petugas_id, 'request_id' => $requestId, 'request_hash' => $requestHash, 'version' => ($screening->versions()->max('version') ?? 0) + 1, 'actor_id' => $request->user()->id, 'reason' => $revisionReason, 'payload' => $screening->fresh()->attributesToArray(), 'recorded_at' => now()]);
            }
            if (! $isDraft) {
                $this->allergies->remember($lockedVisit, ($data['status_alergi'] ?? null) !== 'ada' && filled($data['alergi_verifikasi'] ?? null) ? $recordedAllergy : ($data['riwayat_alergi'] ?? null));
            }
            $lockedVisit->update(['status' => $wasSubmitted ? 'pemeriksaan' : ($isDraft ? 'screening' : 'pemeriksaan')]);
            $auditRecorder->record($request, $isDraft ? 'screening.draft_saved' : 'screening.create_or_update', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return redirect()->route($wasSubmitted || $isDraft ? 'pelayanan.screening.show' : 'pelayanan.kunjungan.show', $kunjungan)
            ->with('success', $wasSubmitted ? 'Perubahan pengkajian tersimpan. Catatan tetap tersedia untuk dokter.' : ($isDraft ? 'Draf pengkajian tersimpan. Pasien masih dalam proses pengkajian awal.' : 'Pengkajian awal tersimpan dan pasien diteruskan ke dokter.'));
    }

    public function update(StoreScreeningRequest $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        return $this->store($request, $kunjungan, $auditRecorder);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    private function determineTriage(array $data, bool $isDental): array
    {
        $emergency = (bool) array_intersect($data['dental_red_flags'] ?? [], ['breathing', 'swallowing', 'rapid_swelling', 'severe_allergy', 'heavy_bleeding', 'critical_vitals'])
            || ($data['nyeri_dada'] ?? null) === 'ya'
            || ($data['kejang'] ?? null) === 'ya'
            || ($data['nadi_teraba'] ?? null) === 'tidak_teraba'
            || ($data['kesadaran'] ?? null) === 'tidak_sadar'
            || ($data['pola_pernapasan'] ?? null) === 'tidak_normal';

        if ($emergency) {
            return ['merah', 'darurat'];
        }

        $generalNeedsReview = ! $isDental && (($data['general_assessment']['general_condition'] ?? null) === 'lemah' || in_array('perlu_penilaian', [$data['general_assessment']['airway'] ?? null, $data['general_assessment']['circulation'] ?? null], true));

        $priority = $generalNeedsReview || (! $isDental && ! empty($data['vitals_unmeasurable'])) || ($isDental && ! empty($data['dental_vitals_unmeasurable'])) || (bool) array_intersect($data['dental_red_flags'] ?? [], ['trismus', 'fever', 'trauma'])
            || ($data['extraoral_swelling'] ?? null) === 'present'
            || ($data['kondisi_psikiatri'] ?? null) === 'terganggu'
            || ($data['kesadaran'] ?? null) === 'menurun'
            || in_array($data['risiko_jatuh_visual'] ?? null, ['sedang', 'tinggi'], true);

        if (! $priority && ! $isDental && in_array('belum_dinilai', [$data['general_assessment']['general_condition'] ?? null, $data['general_assessment']['airway'] ?? null, $data['general_assessment']['circulation'] ?? null], true)) {
            return [null, null];
        }

        if (! $priority && ($isDental && ($data['dental_red_flags'] ?? []) !== ['none'])) {
            return [null, null];
        }

        if (! $priority && ! collect(['nyeri_dada', 'kejang', 'nadi_teraba', 'pola_pernapasan', 'kesadaran', 'kondisi_psikiatri', 'risiko_jatuh_visual'])
            ->every(fn (string $field): bool => filled($data[$field] ?? null))) {
            return [null, null];
        }

        return $priority ? ['kuning', 'segera'] : ['hijau', 'normal'];
    }
}
