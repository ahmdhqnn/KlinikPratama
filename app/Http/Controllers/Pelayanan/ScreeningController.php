<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\CorrespondenceService;
use App\Exports\ScreeningExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningRequest;
use App\Models\KlinikSetting;
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
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function history(Request $request): Response
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

        $isReport = $request->routeIs('perawat.laporan-pengkajian');

        return Inertia::render('pelayanan/screening/history', [
            'isReport' => $isReport,
            'serviceName' => $nurse?->poliklinik?->nama ?? 'Semua poli',
            'exportUrl' => $isReport ? route('perawat.laporan-pengkajian.export', $filters) : null,
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
        $nurse = $this->perawatClinicResolver->nakes($request->user());

        $auditRecorder->record($request, 'screening.report_export', metadata: ['clinic_id' => $nurse->poliklinik_id, 'staff_id' => $nurse->id, 'filters' => $filters]);

        return Excel::download(new ScreeningExport($filters, $nurse), 'laporan-pengkajian-'.$nurse->poliklinik->jenis.'-'.today()->format('Y-m-d').'.xlsx');
    }

    public function start(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $kunjungan->loadMissing('poliklinik');
        abort_unless(in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        abort_if($nurse && (int) $kunjungan->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
        $data = $request->validate([
            'petugas_id' => [Rule::excludeIf($isNurse), 'required', Rule::exists('nakes', 'id')->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('poliklinik_id', $kunjungan->poliklinik_id)->where('is_active', true)->whereNull('deleted_at')],
        ]);
        DB::transaction(function () use ($kunjungan, $nurse, $data, $request, $auditRecorder): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->scheduling->ensureServiceDate($visit);
            abort_unless(in_array($visit->status, ['menunggu', 'screening'], true), 422, 'Kunjungan tidak lagi berada di antrean pengkajian awal.');
            if ($visit->status === 'menunggu') {
                Screening::updateOrCreate(['kunjungan_id' => $visit->id], [
                    'petugas_id' => $nurse?->id ?? $data['petugas_id'],
                    'started_at' => now(),
                ]);
                $visit->update(['status' => 'screening']);
                $auditRecorder->record($request, 'screening.started', $visit->pasien_id, $visit->id);
            }
        });

        return redirect()->route('pelayanan.screening.show', $kunjungan)->with('success', 'Pengkajian awal dimulai. Pastikan identitas pasien sesuai sebelum melakukan pemeriksaan.');
    }

    public function print(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder, CorrespondenceService $correspondence): HttpResponse
    {
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'screening.petugas.user']);
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
        $presentRows = static fn (array $rows): array => array_filter($rows, static fn (mixed $value): bool => filled($value));
        $joinValues = static fn (?string ...$values): ?string => ($joined = implode(' — ', array_filter($values, static fn (?string $value): bool => filled($value)))) !== '' ? $joined : null;
        $clinic = KlinikSetting::first();

        return Inertia::render('pelayanan/screening/print', [
            'document' => [
                'clinic' => [
                    'name' => $clinic?->nama_klinik ?? config('app.name'),
                    'address' => $clinic?->alamat,
                    'phone' => $clinic?->telepon,
                    'email' => $clinic?->email,
                    'logoUrl' => $clinic?->logo ? Storage::disk('public')->url($clinic->logo) : null,
                ],
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
                'visitStatus' => $kunjungan->status,
                'isDraft' => $isDraft,
                'startedAt' => $screening->started_at?->format('d/m/Y H:i'),
                'submittedAt' => $screening->submitted_at?->format('d/m/Y H:i'),
                'printDate' => $screening->submitted_at?->locale('id')->isoFormat('D MMMM Y') ?? now()->locale('id')->isoFormat('D MMMM Y'),
                'printedAt' => now()->format('d/m/Y H:i'),
                'signatureDataUrl' => $isDraft ? null : $correspondence->signatureDataUrl($screening->petugas?->user),
                'backUrl' => route('pelayanan.screening.index'),
                'sections' => [
                    'history' => $presentRows([
                        'Sumber informasi' => filled($screening->sumber_informasi) ? $choice($screening->sumber_informasi) : null,
                        'Keluhan utama' => $screening->keluhan,
                        'Lama keluhan' => $screening->lama_keluhan,
                        'Keluhan tambahan' => $screening->keluhan_tambahan,
                        'Status riwayat penyakit' => filled($screening->status_riwayat_penyakit) ? $choice($screening->status_riwayat_penyakit) : null,
                        'Riwayat penyakit' => $joinValues($screening->riwayat_penyakit, $screening->penyakit_nama, $screening->penyakit_keterangan),
                        'Obat yang digunakan' => $screening->riwayat_obat,
                        'Riwayat penyakit keluarga' => $screening->riwayat_penyakit_keluarga,
                        'Status alergi' => filled($screening->status_alergi) ? $choice($screening->status_alergi) : null,
                        'Riwayat alergi' => $screening->riwayat_alergi,
                        'Jenis / reaksi alergi' => $joinValues($screening->alergi_jenis, $screening->alergi_reaksi),
                    ]),
                    'vitals' => $presentRows([
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
                        'Kebutuhan respons awal' => filled($screening->kesimpulan_triase) ? $choice($screening->kesimpulan_triase).' · '.$choice($screening->prioritas_layanan) : null,
                        'Catatan penapisan' => $screening->catatan_triase,
                    ]),
                    'dental' => $kunjungan->poliklinik?->jenis === 'gigi' ? $presentRows([
                        'Kondisi kesehatan yang perlu diketahui dokter gigi' => $screening->risiko_medis_gigi ? $choices($screening->risiko_medis_gigi) : null,
                        'Lokasi keluhan / nyeri' => $screening->lokasi_nyeri_gigi,
                        'Pemicu keluhan' => $screening->pemicu_nyeri_gigi ? $choices($screening->pemicu_nyeri_gigi) : null,
                        'Pola / lama keluhan' => $screening->durasi_keluhan_gigi,
                        'Riwayat infeksi atau bengkak' => $screening->riwayat_infeksi_gigi ? $choices($screening->riwayat_infeksi_gigi) : null,
                        'Catatan untuk dokter gigi' => $screening->catatan_medis_gigi,
                    ]) : [],
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
            'petugas_id', 'keluhan', 'td_sistole', 'td_diastole', 'nadi', 'suhu', 'berat_badan', 'tinggi_badan',
            'lingkar_perut', 'spo2', 'respirasi', 'riwayat_penyakit', 'riwayat_alergi', 'risiko_jatuh',
            'risiko_nyeri', 'skrining_gizi', 'alergi_jenis', 'alergi_reaksi', 'penyakit_nama',
            'penyakit_keterangan', 'nyeri_dada', 'kondisi_psikiatri', 'nadi_teraba', 'kejang',
            'pola_pernapasan', 'kesadaran', 'risiko_jatuh_visual',
            'riwayat_penyakit_keluarga', 'skala_nyeri', 'lokasi_nyeri_gigi', 'pemicu_nyeri_gigi',
            'durasi_keluhan_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi', 'catatan_medis_gigi',
            'keluhan_tambahan', 'lama_keluhan', 'riwayat_obat', 'status_alergi',
            'status_riwayat_penyakit', 'sumber_informasi', 'alasan_pengukuran_tidak_lengkap',
            'triase_perawat', 'catatan_triase', 'pemeriksaan_fisik',
        ];
        $screeningValues = array_combine(
            $screeningFields,
            array_map(fn (string $field): mixed => $kunjungan->screening?->getAttribute($field), $screeningFields),
        );
        foreach ([...$this->allergies->forVisit($kunjungan), ...$this->clinicalHistory->forVisit($kunjungan)] as $field => $value) {
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
                    'allergyHistory' => $screeningValues['riwayat_alergi'],
                ],
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'clinicType' => $kunjungan->poliklinik?->jenis,
                'doctor' => $kunjungan->dokter?->nama ?? 'Belum ditentukan',
                'screening' => $screeningValues,
                'triage' => $kunjungan->screening?->kesimpulan_triase,
                'priority' => $kunjungan->screening?->prioritas_layanan,
                'printUrl' => $kunjungan->screening ? route('pelayanan.screening.print', $kunjungan) : null,
                'startedAt' => $kunjungan->screening?->started_at?->format('d/m/Y H:i'),
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
        unset($data['tindakan'], $data['konfirmasi_riwayat']);

        foreach (['pemicu_nyeri_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi'] as $field) {
            if (in_array('tidak_ada', $data[$field] ?? [], true) && count($data[$field] ?? []) > 1) {
                return back()->withErrors([$field => 'Pilihan Tidak ada tidak dapat digabung dengan pilihan lain.'])->withInput();
            }
        }

        foreach ([
            'keluhan', 'keluhan_tambahan', 'lama_keluhan', 'riwayat_obat', 'riwayat_penyakit', 'penyakit_nama', 'penyakit_keterangan',
            'riwayat_alergi', 'alergi_jenis', 'alergi_reaksi', 'riwayat_penyakit_keluarga',
            'lokasi_nyeri_gigi', 'durasi_keluhan_gigi', 'catatan_medis_gigi',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim($data[$field]) : null;
            }
        }
        foreach (['pemicu_nyeri_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi'] as $field) {
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
        [$data['kesimpulan_triase'], $data['prioritas_layanan']] = $this->determineTriage($data);

        $wasSubmitted = false;
        DB::transaction(function () use ($data, $kunjungan, $request, $auditRecorder, $isDraft, &$wasSubmitted): void {
            $lockedVisit = Kunjungan::with(['screening', 'pemeriksaan'])->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->canUpdateScreening(), 422, 'Pengkajian hanya dapat diperbarui sebelum dokter memulai pemeriksaan.');

            $this->scheduling->ensureServiceDate($lockedVisit);
            $wasSubmitted = $lockedVisit->screening?->submitted_at !== null;
            $isDraft = $wasSubmitted ? false : $isDraft;
            $data['started_at'] = $lockedVisit->screening?->started_at ?? now();
            $data['submitted_at'] = $isDraft ? null : now();
            $data['risiko_jatuh'] = $data['risiko_jatuh_visual'] ?? null;
            if (($data['status_riwayat_penyakit'] ?? null) === 'tidak_ada') {
                $data['riwayat_penyakit'] = 'Pasien menyangkal riwayat penyakit';
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
                if (blank($data[$field] ?? null)) {
                    $data[$field] = $value;
                }
            }
            Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);
            if (! $isDraft) {
                $this->allergies->remember($lockedVisit, $data['riwayat_alergi'] ?? null);
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
    private function determineTriage(array $data): array
    {
        $emergency = ($data['nyeri_dada'] ?? null) === 'ya'
            || ($data['kejang'] ?? null) === 'ya'
            || ($data['nadi_teraba'] ?? null) === 'tidak_teraba'
            || ($data['kesadaran'] ?? null) === 'tidak_sadar'
            || ($data['pola_pernapasan'] ?? null) === 'tidak_normal';

        if ($emergency) {
            return ['merah', 'darurat'];
        }

        $priority = ($data['kondisi_psikiatri'] ?? null) === 'terganggu'
            || ($data['kesadaran'] ?? null) === 'menurun'
            || in_array($data['risiko_jatuh_visual'] ?? null, ['sedang', 'tinggi'], true);

        if (! $priority && ! collect(['nyeri_dada', 'kejang', 'nadi_teraba', 'pola_pernapasan', 'kesadaran', 'kondisi_psikiatri', 'risiko_jatuh_visual'])
            ->every(fn (string $field): bool => filled($data[$field] ?? null))) {
            return [null, null];
        }

        return $priority ? ['kuning', 'segera'] : ['hijau', 'normal'];
    }
}
