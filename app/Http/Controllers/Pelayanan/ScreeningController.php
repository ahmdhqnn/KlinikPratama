<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\PerawatClinicResolver;
use App\VisitSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    public function __construct(private PerawatClinicResolver $perawatClinicResolver, private VisitSchedulingService $scheduling) {}

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
        $petugasList = Nakes::whereIn('jabatan', ['perawat', 'bidan'])->where('is_active', true)
            ->where('poliklinik_id', $kunjungan->poliklinik_id)->orderBy('nama')->get();

        $screeningFields = [
            'petugas_id', 'keluhan', 'td_sistole', 'td_diastole', 'nadi', 'suhu', 'berat_badan', 'tinggi_badan',
            'lingkar_perut', 'spo2', 'respirasi', 'riwayat_penyakit', 'riwayat_alergi', 'risiko_jatuh',
            'risiko_nyeri', 'skrining_gizi', 'alergi_jenis', 'alergi_reaksi', 'penyakit_nama',
            'penyakit_keterangan', 'nyeri_dada', 'kondisi_psikiatri', 'nadi_teraba', 'kejang',
            'pola_pernapasan', 'kesadaran', 'risiko_jatuh_visual',
            'riwayat_penyakit_keluarga', 'skala_nyeri', 'lokasi_nyeri_gigi', 'pemicu_nyeri_gigi',
            'durasi_keluhan_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi', 'catatan_medis_gigi',
        ];
        $screeningValues = array_combine(
            $screeningFields,
            array_map(fn (string $field): mixed => $kunjungan->screening?->getAttribute($field), $screeningFields),
        );

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
                    'allergyHistory' => $kunjungan->pasien?->riwayat_alergi,
                ],
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'clinicType' => $kunjungan->poliklinik?->jenis,
                'doctor' => $kunjungan->dokter?->nama ?? 'Belum ditentukan',
                'screening' => $screeningValues,
                'triage' => $kunjungan->screening?->kesimpulan_triase,
                'priority' => $kunjungan->screening?->prioritas_layanan,
            ],
            'staff' => $petugasList->map(fn (Nakes $staff): array => [
                'id' => $staff->id,
                'name' => $staff->nama,
                'position' => ucfirst($staff->jabatan),
            ])->values(),
            'canSelectStaff' => ! $isNurse,
            'currentStaff' => $nurse?->nama ?? $kunjungan->screening?->petugas?->nama,
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->scheduling->ensureServiceDate($kunjungan);
        $kunjungan->loadMissing(['poliklinik', 'pasien']);
        $isDental = $kunjungan->poliklinik?->jenis === 'gigi';
        abort_unless(in_array($kunjungan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $isNurse = $request->user()->role === 'perawat';
        $nurse = $isNurse ? $this->perawatClinicResolver->nakes($request->user()) : null;
        abort_if($nurse && (int) $kunjungan->poliklinik_id !== (int) $nurse->poliklinik_id, 404);
        abort_unless(in_array($kunjungan->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

        $data = $request->validate([
            'petugas_id' => [Rule::excludeIf($isNurse), 'nullable', Rule::exists('nakes', 'id')->whereIn('jabatan', ['perawat', 'bidan'])->where('poliklinik_id', $kunjungan->poliklinik_id)->where('is_active', true)->whereNull('deleted_at')],
            'keluhan' => ['nullable', 'string'],
            'td_sistole' => ['nullable', 'integer', 'min:0', 'max:300'],
            'td_diastole' => ['nullable', 'integer', 'min:0', 'max:200'],
            'nadi' => ['nullable', 'integer', 'min:0', 'max:300'],
            'suhu' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'berat_badan' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'tinggi_badan' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'lingkar_perut' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'respirasi' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fungsi_penciuman' => ['nullable', 'in:normal,menurun,tidak_ada'],
            'tingkat_kesadaran' => ['nullable', 'in:composmentis,apatis,somnolen,sopor,koma'],
            'riwayat_penyakit' => ['nullable', 'string'],
            'penyakit_nama' => ['nullable', 'string', 'max:255'],
            'penyakit_keterangan' => ['nullable', 'string'],
            'riwayat_alergi' => ['nullable', 'string'],
            'alergi_jenis' => ['nullable', 'string', 'max:255'],
            'alergi_reaksi' => ['nullable', 'string'],
            'risiko_jatuh' => ['nullable', 'string'],
            'risiko_nyeri' => ['nullable', 'string'],
            'skala_nyeri' => ['nullable', 'integer', 'between:0,10'],
            'skrining_gizi' => ['nullable', 'string'],
            'riwayat_penyakit_keluarga' => ['nullable', 'string', 'max:5000'],
            'lokasi_nyeri_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:255'],
            'pemicu_nyeri_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'array'],
            'pemicu_nyeri_gigi.*' => ['in:dingin,manis,mengunyah,panas,spontan,lainnya,tidak_ada'],
            'durasi_keluhan_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:255'],
            'risiko_medis_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'array'],
            'risiko_medis_gigi.*' => ['in:hipertensi,diabetes,penyakit_jantung,gangguan_pembekuan,antikoagulan,lainnya,tidak_ada'],
            'riwayat_infeksi_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'array'],
            'riwayat_infeksi_gigi.*' => ['in:hepatitis,hiv,infeksi_lain,tidak_ada'],
            'catatan_medis_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:5000'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'nyeri_dada' => ['required', 'in:ya,tidak'],
            'kondisi_psikiatri' => ['required', 'in:normal,terganggu'],
            'nadi_teraba' => ['required', 'in:teraba,tidak_teraba'],
            'kejang' => ['required', 'in:ya,tidak'],
            'pola_pernapasan' => ['required', 'in:normal,tidak_normal'],
            'kesadaran' => ['required', 'in:sadar,menurun,tidak_sadar'],
            'risiko_jatuh_visual' => ['required', 'in:rendah,sedang,tinggi'],
        ]);

        foreach (['pemicu_nyeri_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi'] as $field) {
            if (in_array('tidak_ada', $data[$field] ?? [], true) && count($data[$field] ?? []) > 1) {
                return back()->withErrors([$field => 'Pilihan Tidak ada tidak dapat digabung dengan pilihan lain.'])->withInput();
            }
        }

        foreach ([
            'keluhan', 'riwayat_penyakit', 'penyakit_nama', 'penyakit_keterangan',
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
        [$data['kesimpulan_triase'], $data['prioritas_layanan']] = $this->determineTriage($data);

        DB::transaction(function () use ($data, $kunjungan, $request, $auditRecorder): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedVisit->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

            $this->scheduling->ensureServiceDate($lockedVisit);
            Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

            if (! empty($data['riwayat_alergi']) && $lockedVisit->pasien) {
                $lockedVisit->pasien->update(['riwayat_alergi' => $data['riwayat_alergi']]);
            }

            $lockedVisit->update(['status' => 'pemeriksaan']);
            $auditRecorder->record($request, 'screening.create_or_update', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)
            ->with('success', 'Screening berhasil disimpan.');
    }

    public function update(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        return $this->store($request, $kunjungan, $auditRecorder);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     */
    private function determineTriage(array $data): array
    {
        $emergency = $data['nyeri_dada'] === 'ya'
            || $data['kejang'] === 'ya'
            || $data['nadi_teraba'] === 'tidak_teraba'
            || $data['kesadaran'] === 'tidak_sadar'
            || $data['pola_pernapasan'] === 'tidak_normal';

        if ($emergency) {
            return ['merah', 'darurat'];
        }

        $priority = $data['kondisi_psikiatri'] === 'terganggu'
            || $data['kesadaran'] === 'menurun'
            || $data['risiko_jatuh_visual'] !== 'rendah';

        return $priority ? ['kuning', 'segera'] : ['hijau', 'normal'];
    }
}
