<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningRequest;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\PatientAllergyHistory;
use App\PerawatClinicResolver;
use App\VisitSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    public function __construct(private PerawatClinicResolver $perawatClinicResolver, private VisitSchedulingService $scheduling, private PatientAllergyHistory $allergies) {}

    public function index(Request $request): Response
    {
        $isNurse = $request->user()->role === 'perawat';
        $assignedClinicId = $isNurse ? $this->perawatClinicResolver->clinicId($request->user()) : null;
        $selectedClinicId = $assignedClinicId;
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening'])
            ->when($selectedClinicId, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->whereDate('tanggal', today())
            ->whereIn('status', ['menunggu', 'screening'])
            ->orderBy('created_at')->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $clinics = $assignedClinicId
            ? Poliklinik::registrable()->whereKey($assignedClinicId)->orderBy('nama')->get(['id', 'nama'])
            : Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama']);

        return Inertia::render('pelayanan/screening/index', [
            'filters' => [
                'search' => '',
                'tanggal' => today()->toDateString(),
                'poliklinikId' => $selectedClinicId ? (int) $selectedClinicId : '',
            ],
            'canSelectClinic' => ! $isNurse,
            'clinics' => $clinics->map(fn (Poliklinik $clinic): array => [
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
                    'status' => $visit->status,
                    'hasScreening' => $visit->screening !== null,
                    'screeningUrl' => route('pelayanan.screening.show', $visit),
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
        $query = Screening::query()
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'petugas'])
            ->whereHas('kunjungan', fn ($visits) => $visits
                ->when($selectedClinicId, fn ($scopedVisits, int $clinicId) => $scopedVisits->where('poliklinik_id', $clinicId))
                ->when($filters['tanggal'] ?? null, fn ($scopedVisits, string $date) => $scopedVisits->whereDate('tanggal', $date)))
            ->when($nurse, fn ($screenings) => $screenings->where('petugas_id', $nurse->id))
            ->when($filters['search'] ?? null, fn ($screenings, string $search) => $screenings->whereHas('kunjungan.pasien', fn ($patients) => $patients
                ->where(fn ($patientQuery) => $patientQuery
                    ->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%"))))
            ->latest('created_at')->latest('id')
            ->paginate(10)
            ->withQueryString();
        $clinics = $assignedClinicId
            ? Poliklinik::registrable()->whereKey($assignedClinicId)->orderBy('nama')->get(['id', 'nama'])
            : Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama']);

        return Inertia::render('pelayanan/screening/history', [
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
            abort_unless(in_array($visit->status, ['menunggu', 'screening'], true), 422, 'Kunjungan tidak lagi berada di antrean skrining.');
            if ($visit->status === 'menunggu') {
                Screening::updateOrCreate(['kunjungan_id' => $visit->id], [
                    'petugas_id' => $nurse?->id ?? $data['petugas_id'],
                    'started_at' => now(),
                ]);
                $visit->update(['status' => 'screening']);
                $auditRecorder->record($request, 'screening.started', $visit->pasien_id, $visit->id);
            }
        });

        return redirect()->route('pelayanan.screening.show', $kunjungan)->with('success', 'Skrining dimulai. Pastikan identitas pasien sesuai sebelum melakukan pengkajian.');
    }

    public function print(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): HttpResponse
    {
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'screening.petugas']);
        if ($request->user()->role === 'perawat') {
            abort_unless((int) $kunjungan->poliklinik_id === $this->perawatClinicResolver->clinicId($request->user()), 404);
        }
        abort_unless($kunjungan->screening && in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $auditRecorder->record($request, 'screening.print', $kunjungan->pasien_id, $kunjungan->id);

        return response()->view('screening.print', [
            'visit' => $kunjungan,
            'screening' => $kunjungan->screening,
            'clinic' => KlinikSetting::first(),
            'isDraft' => $kunjungan->screening->submitted_at === null && ($kunjungan->screening->started_at !== null || in_array($kunjungan->status, ['menunggu', 'screening'], true)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): Response
    {
        $this->scheduling->ensureServiceDate($kunjungan);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'screening.petugas']);
        abort_unless(in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        abort_if($nurse && (int) $kunjungan->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
        abort_unless(in_array($kunjungan->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');
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
        foreach ($this->allergies->forVisit($kunjungan) as $field => $value) {
            if (blank($screeningValues[$field])) {
                $screeningValues[$field] = $value;
            }
        }

        return Inertia::render('pelayanan/screening/show', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
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
        abort_unless(in_array($kunjungan->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

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
                'Petugas skrining harus ditugaskan pada poliklinik kunjungan ini.'
            );
        }

        $data['kunjungan_id'] = $kunjungan->id;
        $data['triase_perawat'] = null;
        [$data['kesimpulan_triase'], $data['prioritas_layanan']] = $this->determineTriage($data);

        DB::transaction(function () use ($data, $kunjungan, $request, $auditRecorder, $isDraft): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedVisit->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

            $this->scheduling->ensureServiceDate($lockedVisit);
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
            Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);
            if (! $isDraft) {
                $this->allergies->remember($lockedVisit, $data['riwayat_alergi'] ?? null);
            }
            $lockedVisit->update(['status' => $isDraft ? 'screening' : 'pemeriksaan']);
            $auditRecorder->record($request, $isDraft ? 'screening.draft_saved' : 'screening.create_or_update', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return redirect()->route($isDraft ? 'pelayanan.screening.show' : 'pelayanan.kunjungan.show', $kunjungan)
            ->with('success', $isDraft ? 'Draf asesmen tersimpan. Pasien masih dalam proses skrining.' : 'Asesmen awal tersimpan dan pasien diteruskan ke dokter.');
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
