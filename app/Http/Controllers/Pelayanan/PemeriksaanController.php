<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAnswer;
use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\CorrespondenceService;
use App\DoctorAccessService;
use App\Http\Controllers\Controller;
use App\Models\ClinicalTerminology;
use App\Models\CorrespondenceTemplate;
use App\Models\Diagnosa;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\OdontogramFinding;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\ResepObat;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\PatientAllergyHistory;
use App\PatientClinicalHistory;
use App\Rules\BodyMapPayload;
use App\VisitCareWorkflow;
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

class PemeriksaanController extends Controller
{
    public function __construct(private DoctorAccessService $doctorAccess, private PatientAllergyHistory $allergies, private PatientClinicalHistory $clinicalHistory) {}

    public function index(Request $request): Response
    {
        $filters = (new VisitQueryService)->filters($request);
        $doctorId = auth()->user()->role === 'dokter' ? $this->currentDoctorId() : null;
        $date = $filters['tanggal'] ?? '';
        $claimDate = $date ?: today()->toDateString();
        $kunjungan = $this->doctorAccess->visibleVisits($request->user(), $claimDate)
            ->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan.diagnosa'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->whereHas('pasien', fn ($patients) => $patients
                ->where(fn ($names) => $names->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->when($date, fn ($query) => $query->whereDate('tanggal', $date))
            ->whereIn('status', ['pemeriksaan', 'tindakan', 'farmasi', 'kasir', 'selesai'])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $recordPatientIds = Kunjungan::query()->where('dokter_id', $doctorId)
            ->whereIn('pasien_id', $kunjungan->getCollection()->pluck('pasien_id'))
            ->distinct()->pluck('pasien_id')->map(fn ($id): int => (int) $id)->all();

        $todayVisits = $this->doctorAccess->visibleVisits($request->user(), today()->toDateString())->whereDate('tanggal', today());

        return Inertia::render('pelayanan/pemeriksaan/index', [
            'stats' => [
                'total' => (clone $todayVisits)->whereIn('status', ['pemeriksaan', 'tindakan', 'farmasi', 'kasir', 'selesai'])->count(),
                'waiting' => (clone $todayVisits)->where('status', 'pemeriksaan')->whereDoesntHave('pemeriksaan')->count(),
                'inProgress' => (clone $todayVisits)->where('status', 'pemeriksaan')->whereHas('pemeriksaan', fn ($query) => $query->whereNull('signed_at'))->count(),
                'completed' => (clone $todayVisits)->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))->count(),
            ],
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
                    'canClaim' => $doctorId !== null && $visit->dokter_id === null,
                    'status' => $visit->status,
                    'examinationStarted' => $visit->pemeriksaan !== null,
                    'diagnosisCount' => $visit->pemeriksaan?->diagnosa->count() ?? 0,
                    'recordUrl' => in_array((int) $visit->pasien_id, $recordPatientIds, true) ? route('pelayanan.pasien.rekam-medis', $visit->pasien_id) : null,
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'date' => $date,
        ]);
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder, ClinicalNoteRecorder $noteRecorder): Response
    {
        $this->authorizeVisit($kunjungan);
        $auditRecorder->record($request, 'clinical_examination.view', $kunjungan->pasien_id, $kunjungan->id);
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'screening',
            'pemeriksaan.diagnosa', 'pemeriksaan.signedBy', 'pemeriksaan.clinicalNoteVersions.actor', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'suratMedis.template',
            'labHasil.laboratorium', 'rujukanInternal.dariPoli', 'rujukanInternal.kePoli',
            'informedConsent', 'odontogramFindings', 'rujukanInternal.template',
        ]);

        $dokterList = auth()->user()->role === 'dokter'
            ? Nakes::whereKey($this->currentDoctorId())->get()
            : Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $tindakanList = Tindakan::where('is_active', true)->where('poliklinik_id', $kunjungan->poliklinik_id)
            ->when(! config('clinic.modules.laboratory', false), fn ($query) => $query->where('kategori', 'medis'))
            ->orderBy('nama')->get();
        $diagnosisCodeSystems = collect([
            ['value' => 'icd10_who', 'label' => 'ICD-10 WHO'],
            ['value' => 'icd10_cm', 'label' => 'ICD-10-CM'],
            ['value' => 'icd9cm_diagnosis', 'label' => 'ICD-9-CM diagnosis'],
        ])->merge(ClinicalTerminology::query()->where('is_active', true)->where('code_type', 'diagnosis')
            ->select('code_system')->distinct()->orderBy('code_system')->get()
            ->map(fn (ClinicalTerminology $terminology): array => [
                'value' => $terminology->code_system,
                'label' => $terminology->code_system,
            ]))
            ->unique('value')->values();
        $obatList = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')->where('is_active', true)->where('jenis', 'obat')->orderBy('nama')->get();
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $latestNote = $kunjungan->pemeriksaan?->clinicalNoteVersions->sortBy('version')->last();
        $noteIntegrityValid = $kunjungan->pemeriksaan?->signed_at ? $noteRecorder->verify($kunjungan->pemeriksaan) : null;
        $effectiveNote = $noteIntegrityValid ? $latestNote->payload : null;
        $allergyHistory = $this->allergies->forVisit($kunjungan)['riwayat_alergi'];
        $clinicalHistory = $this->clinicalHistory->forVisit($kunjungan);
        $clinicalAnswer = static fn (?string $value, string $fallback): string => ClinicalAnswer::normalize($value) ?? $fallback;
        $firstRecordedValue = static function (?string ...$values): ?string {
            foreach ($values as $value) {
                $recordedValue = ClinicalAnswer::normalize($value);
                if ($recordedValue !== null) {
                    return $recordedValue;
                }
            }

            return null;
        };
        $examinationFormDefaults = [
            'anamnesis' => is_array($effectiveNote)
                ? ClinicalAnswer::normalize($effectiveNote['anamnesis'] ?? null)
                : $firstRecordedValue($kunjungan->pemeriksaan?->anamnesis, $kunjungan->screening?->keluhan),
            'currentHistory' => ClinicalAnswer::normalize(is_array($effectiveNote)
                ? ($effectiveNote['riwayat_penyakit_sekarang'] ?? null)
                : $kunjungan->pemeriksaan?->riwayat_penyakit_sekarang),
            'pastHistory' => is_array($effectiveNote)
                ? ClinicalAnswer::normalize($effectiveNote['riwayat_penyakit_dahulu'] ?? null)
                : $clinicalHistory['riwayat_penyakit'],
            'familyHistory' => is_array($effectiveNote)
                ? ClinicalAnswer::normalize($effectiveNote['riwayat_penyakit_keluarga'] ?? null)
                : $clinicalHistory['riwayat_penyakit_keluarga'],
            'allergyHistory' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_alergi'] ?? null) : $allergyHistory),
        ];
        $oralHygieneIndex = is_array($effectiveNote) ? ($effectiveNote['oral_hygiene_index'] ?? null) : $kunjungan->pemeriksaan?->oral_hygiene_index;
        $odontogram = is_array($effectiveNote)
            ? ($effectiveNote['odontogram'] ?? [])
            : ($noteIntegrityValid === false ? [] : $kunjungan->odontogramFindings->map(fn (OdontogramFinding $finding): array => [
                'tooth_fdi' => $finding->tooth_fdi,
                'surface' => $finding->surface,
                'finding_code' => $finding->finding_code,
                'notes' => $finding->notes,
            ])->all());

        return Inertia::render('pelayanan/pemeriksaan/show', [
            'initialTab' => is_string($request->query('tab')) ? $request->query('tab') : 'anamnesis',
            'visit' => [
                'id' => $kunjungan->id,
                'attachments' => $kunjungan->attachmentSummaries(),
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'examinationStarted' => $kunjungan->pemeriksaan !== null,
                'doctorId' => $kunjungan->dokter_id,
                'canClaim' => auth()->user()->role === 'dokter' && $kunjungan->dokter_id === null,
                'isReservation' => $kunjungan->status === 'menunggu' && $kunjungan->tanggal?->isAfter(today()),
                'recordUrl' => (auth()->user()->role !== 'dokter' || $kunjungan->pasien->kunjungan()->where('dokter_id', $this->currentDoctorId())->exists()) ? route('pelayanan.pasien.rekam-medis', $kunjungan->pasien_id) : null,
                'clinicId' => $kunjungan->poliklinik_id,
                'clinicType' => $kunjungan->poliklinik?->jenis,
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'patient' => [
                    'id' => $kunjungan->pasien->id,
                    'name' => $kunjungan->pasien->nama,
                    'medicalRecordNumber' => $kunjungan->pasien->no_rm,
                    'gender' => $kunjungan->pasien->jenis_kelamin,
                    'age' => $kunjungan->pasien->umur,
                    'bloodType' => $kunjungan->pasien->golongan_darah,
                    'allergies' => $allergyHistory,
                ],
                'examinationFormDefaults' => $examinationFormDefaults,
                'screening' => $kunjungan->screening ? [
                    'summary' => $kunjungan->screening->handoverSummary(),
                    'systolic' => $kunjungan->screening->td_sistole,
                    'diastolic' => $kunjungan->screening->td_diastole,
                    'pulse' => $kunjungan->screening->nadi,
                    'temperature' => $kunjungan->screening->suhu,
                    'oxygenSaturation' => $kunjungan->screening->spo2,
                    'weight' => $kunjungan->screening->berat_badan,
                    'height' => $kunjungan->screening->tinggi_badan,
                    'bmi' => $kunjungan->screening->imt,
                    'respiration' => $kunjungan->screening->respirasi,
                    'complaint' => $kunjungan->screening->keluhan,
                    'medicalHistory' => $clinicalAnswer($kunjungan->screening->riwayat_penyakit, 'Belum dicatat'),
                    'familyHistory' => $clinicalAnswer($kunjungan->screening->riwayat_penyakit_keluarga, 'Belum dicatat'),
                    'allergyHistory' => $clinicalAnswer($kunjungan->screening->riwayat_alergi ?: $kunjungan->pasien->riwayat_alergi, 'Belum dicatat'),
                    'painScale' => $kunjungan->screening->skala_nyeri,
                    'fallRisk' => $kunjungan->screening->risiko_jatuh,
                    'triage' => $kunjungan->screening->kesimpulan_triase,
                    'priority' => $kunjungan->screening->prioritas_layanan,
                    'dentalPainLocation' => $clinicalAnswer($kunjungan->screening->lokasi_nyeri_gigi, 'Belum dicatat'),
                    'dentalPainTriggers' => $kunjungan->screening->pemicu_nyeri_gigi ?? [],
                    'dentalPainDuration' => $clinicalAnswer($kunjungan->screening->durasi_keluhan_gigi, 'Belum dicatat'),
                    'dentalMedicalRisks' => $kunjungan->screening->risiko_medis_gigi ?? [],
                    'dentalInfectionHistory' => $kunjungan->screening->riwayat_infeksi_gigi ?? [],
                    'dentalNotes' => $clinicalAnswer($kunjungan->screening->catatan_medis_gigi, 'Belum dicatat'),
                ] : null,
                'examination' => $kunjungan->pemeriksaan ? [
                    'doctorId' => $kunjungan->pemeriksaan->dokter_id,
                    'startedAt' => ($kunjungan->pemeriksaan->started_at ?? $kunjungan->pemeriksaan->created_at)?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                    'anamnesis' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['anamnesis'] ?? null) : $kunjungan->pemeriksaan->anamnesis),
                    'currentHistory' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_sekarang'] ?? null) : $kunjungan->pemeriksaan->riwayat_penyakit_sekarang),
                    'pastHistory' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_dahulu'] ?? null) : $kunjungan->pemeriksaan->riwayat_penyakit_dahulu),
                    'familyHistory' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_keluarga'] ?? null) : $kunjungan->pemeriksaan->riwayat_penyakit_keluarga),
                    'allergyHistory' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['riwayat_alergi'] ?? null) : $kunjungan->pemeriksaan->riwayat_alergi),
                    'physicalExam' => ClinicalAnswer::normalize(is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik'] ?? null) : $kunjungan->pemeriksaan->pemeriksaan_fisik),
                    'physicalSystems' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik_terstruktur'] ?? []) : ($kunjungan->pemeriksaan->pemeriksaan_fisik_terstruktur ?? []),
                    'bodyMap' => is_array($effectiveNote) ? ($effectiveNote['body_map'] ?? null) : $kunjungan->pemeriksaan->body_map,
                    'extraoralExam' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_ekstraoral'] ?? null) : $kunjungan->pemeriksaan->pemeriksaan_ekstraoral,
                    'oralHygieneIndex' => is_numeric($oralHygieneIndex) ? (float) $oralHygieneIndex : null,
                    'differentialDiagnosis' => is_array($effectiveNote) ? ($effectiveNote['diagnosis_banding'] ?? null) : $kunjungan->pemeriksaan->diagnosis_banding,
                    'followUpDate' => is_array($effectiveNote) ? ($effectiveNote['kontrol_berikutnya'] ?? null) : $kunjungan->pemeriksaan->kontrol_berikutnya?->toDateString(),
                    'notes' => is_array($effectiveNote) ? ($effectiveNote['catatan'] ?? null) : $kunjungan->pemeriksaan->catatan,
                    'nonPharmacological' => is_array($effectiveNote) ? ($effectiveNote['non_farmakologis'] ?? null) : $kunjungan->pemeriksaan->non_farmakologis,
                    'education' => is_array($effectiveNote) ? ($effectiveNote['edukasi'] ?? null) : $kunjungan->pemeriksaan->edukasi,
                    'status' => $kunjungan->pemeriksaan->status,
                    'integrityValid' => $noteIntegrityValid,
                    'signedAt' => $kunjungan->pemeriksaan->signed_at?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                    'signedBy' => $kunjungan->pemeriksaan->signedBy?->name,
                    'versions' => $kunjungan->pemeriksaan->clinicalNoteVersions->sortBy('version')->map(fn ($version): array => [
                        'version' => $version->version,
                        'kind' => $version->kind,
                        'reason' => $version->reason,
                        'actor' => $version->actor?->name,
                        'recordedAt' => $version->recorded_at?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                        'anamnesis' => $version->payload['anamnesis'] ?? null,
                        'physicalExam' => $version->payload['pemeriksaan_fisik'] ?? null,
                        'bodyMap' => $version->payload['body_map'] ?? null,
                        'notes' => $version->payload['catatan'] ?? null,
                        'education' => $version->payload['edukasi'] ?? null,
                        'nonPharmacological' => $version->payload['non_farmakologis'] ?? null,
                        'odontogram' => collect($version->payload['odontogram'] ?? [])->map(fn (array $finding): array => [
                            'toothFdi' => $finding['tooth_fdi'],
                            'surface' => $finding['surface'],
                            'findingCode' => $finding['finding_code'],
                            'notes' => $finding['notes'] ?? null,
                        ])->values()->all(),
                    ])->values(),
                ] : null,
                'diagnoses' => collect(is_array($effectiveNote) ? ($effectiveNote['diagnoses'] ?? []) : ($noteIntegrityValid === false ? [] : $kunjungan->pemeriksaan?->diagnosa))->values()->map(fn ($diagnosis, int $index): array => [
                    'id' => is_array($diagnosis) ? ($diagnosis['id'] ?? $index + 1) : $diagnosis->id,
                    'code' => is_array($diagnosis) ? ($diagnosis['code'] ?? null) : $diagnosis->kode_icd10,
                    'codeSystem' => is_array($diagnosis) ? ($diagnosis['code_system'] ?? null) : $diagnosis->code_system,
                    'codeRelease' => is_array($diagnosis) ? ($diagnosis['code_release'] ?? null) : $diagnosis->code_release,
                    'name' => is_array($diagnosis) ? $diagnosis['name'] : $diagnosis->nama_diagnosa,
                    'note' => is_array($diagnosis) ? ($diagnosis['note'] ?? null) : $diagnosis->keterangan,
                    'type' => is_array($diagnosis) ? $diagnosis['type'] : $diagnosis->jenis,
                ]),
                'treatments' => collect(is_array($effectiveNote) ? ($effectiveNote['treatments'] ?? []) : ($noteIntegrityValid === false ? [] : $kunjungan->tindakanKunjungan))->values()->map(fn ($item, int $index): array => [
                    'id' => is_array($item) ? ($item['id'] ?? $index + 1) : $item->id,
                    'name' => is_array($item) ? $item['name'] : ($item->nama_tindakan ?? $item->nama_tindakan_manual ?? $item->tindakan?->nama ?? 'Tindakan dihapus'),
                    'code' => is_array($item) ? ($item['code'] ?? null) : $item->kode_icd9cm,
                    'codeSystem' => is_array($item) ? ($item['code_system'] ?? null) : $item->code_system,
                    'codeRelease' => is_array($item) ? ($item['code_release'] ?? null) : $item->code_release,
                    'quantity' => is_array($item) ? $item['quantity'] : $item->jumlah,
                    'toothFdi' => is_array($item) ? ($item['tooth_fdi'] ?? null) : $item->tooth_fdi,
                    'note' => is_array($item) ? ($item['note'] ?? null) : $item->catatan,
                    'executionRole' => is_array($item) ? ($item['execution_role'] ?? 'dokter') : $item->execution_role,
                ])->values(),
                'odontogram' => collect($odontogram)->map(fn (array $finding): array => [
                    'toothFdi' => $finding['tooth_fdi'],
                    'surface' => $finding['surface'],
                    'findingCode' => $finding['finding_code'],
                    'notes' => $finding['notes'] ?? null,
                ])->values(),
                'prescription' => [
                    'status' => $kunjungan->resep?->status,
                    'items' => $kunjungan->resep?->resepObat->map(fn (ResepObat $item) => [
                        'id' => $item->id,
                        'name' => $item->nama_obat,
                        'type' => $item->jenis,
                        'quantity' => $item->jumlah,
                        'unit' => $item->satuan,
                        'instructions' => $item->aturan_pakai,
                        'external' => $item->is_resep_luar,
                    ])->values() ?? collect(),
                    'printUrl' => $kunjungan->resep?->resepObat->contains('is_resep_luar', true) ? route('persuratan.resep.cetak', $kunjungan).'?asal=pemeriksaan' : null,
                ],
                'letters' => $kunjungan->suratMedis->map(fn ($letter) => [
                    'id' => $letter->id,
                    'type' => $letter->jenis,
                    'number' => $letter->nomor_surat,
                    'content' => $letter->konten,
                    'date' => $letter->tanggal?->format('d/m/Y'),
                    'printUrl' => route('persuratan.surat.cetak', $letter).'?asal=pemeriksaan',
                ])->values(),
                'referrals' => $kunjungan->rujukanInternal->map(fn ($referral) => [
                    'id' => $referral->id,
                    'fromClinic' => $referral->dariPoli?->nama,
                    'toClinic' => $referral->kePoli?->nama,
                    'notes' => $referral->catatan,
                    'status' => $referral->status,
                    'number' => $referral->nomor_surat,
                    'content' => $referral->konten_surat,
                    'printUrl' => route('persuratan.rujukan.cetak', $referral).'?asal=pemeriksaan',
                ])->values(),
            ],
            'doctors' => $dokterList->map(fn (Nakes $doctor) => ['id' => $doctor->id, 'name' => $doctor->nama]),
            'diagnosisCodeSystems' => $diagnosisCodeSystems,
            'procedureCatalogAvailable' => ClinicalTerminology::query()->where('code_system', 'icd9cm_procedure')
                ->where('code_type', 'procedure')->where('is_active', true)->exists(),
            'procedureSearchUrl' => route('pelayanan.icd9cm.search'),
            'treatments' => $tindakanList->map(fn (Tindakan $treatment) => [
                'id' => $treatment->id,
                'name' => $treatment->nama,
            ]),
            'medicines' => $obatList->map(fn (Obat $medicine) => [
                'id' => $medicine->id,
                'name' => $medicine->nama,
                'stock' => (float) $medicine->usable_stock,
                'unit' => $medicine->satuan_kecil,
            ]),
            'clinics' => $poliklinikList->where('id', '!=', $kunjungan->poliklinik_id)->map(fn (Poliklinik $clinic) => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'dentalOptions' => [
                'teeth' => OdontogramFinding::toothCodes(),
                'surfaces' => OdontogramFinding::surfaceLabels(),
                'findings' => OdontogramFinding::findingLabels(),
            ],
            'today' => today()->toDateString(),
            'correspondenceTemplates' => CorrespondenceTemplate::query()->where('is_active', true)->orderBy('nama')->get()
                ->map(fn (CorrespondenceTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->nama,
                    'type' => $template->jenis,
                    'content' => $template->isi,
                ])->values(),
        ]);
    }

    public function start(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        DB::transaction(function () use ($request, $kunjungan, $auditRecorder): void {
            $visit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            app(VisitSchedulingService::class)->ensureServiceDate($visit);
            $this->authorizeVisit($visit);
            $doctorId = $this->currentDoctorId();

            if ($visit->dokter_id === null) {
                $visit->update(['dokter_id' => $doctorId]);
                $visit->pemeriksaan()->update(['dokter_id' => $doctorId]);
                $auditRecorder->record($request, 'clinical_examination.visit_claim', $visit->pasien_id, $visit->id);
            }

            $this->authorizeMutableVisit($visit);
            $examination = $visit->pemeriksaan()->firstOrCreate(
                ['kunjungan_id' => $visit->id],
                ['dokter_id' => $doctorId, 'status' => 'draft', 'started_at' => now()],
            );

            if ($examination->wasRecentlyCreated || $examination->started_at === null) {
                if ($examination->started_at === null) {
                    $examination->update(['started_at' => now()]);
                }
                $auditRecorder->record($request, 'clinical_examination.started', $visit->pasien_id, $visit->id);
            }
        });

        return redirect()->route('pelayanan.pemeriksaan.show', $kunjungan)->with('success', 'Pemeriksaan telah dimulai. Silakan lengkapi catatan pemeriksaan pasien.');
    }

    public function claim(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $doctorId = $this->currentDoctorId();

        DB::transaction(function () use ($request, $kunjungan, $auditRecorder, $doctorId): void {
            $lockedVisit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            app(VisitSchedulingService::class)->ensureServiceDate($lockedVisit);
            abort_unless($lockedVisit->status === 'pemeriksaan', 422, 'Kunjungan belum berada pada tahap pemeriksaan dokter.');

            if ($lockedVisit->dokter_id === $doctorId) {
                return;
            }

            abort_if($lockedVisit->dokter_id !== null, 409, 'Kunjungan sudah diambil oleh dokter lain.');
            abort_unless($this->isDoctorScheduledForVisit($doctorId, $lockedVisit), 403, 'Anda tidak memiliki jadwal pada poli dan tanggal kunjungan ini.');

            $lockedVisit->update(['dokter_id' => $doctorId]);
            $lockedVisit->pemeriksaan()->update(['dokter_id' => $doctorId]);
            $auditRecorder->record($request, 'clinical_examination.visit_claim', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Kunjungan berhasil dimasukkan ke antrean pemeriksaan Anda.');
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.store', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $data = $this->validateExamination($request, $kunjungan);

            $data['kunjungan_id'] = $kunjungan->id;
            $data['dokter_id'] = auth()->user()->role === 'dokter'
                ? $this->currentDoctorId()
                : ($data['dokter_id'] ?? $kunjungan->dokter_id);
            $data['status'] = 'draft';

            Pemeriksaan::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

            return back()->with('success', 'Data pemeriksaan berhasil disimpan.');
        });
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.update', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $data = $this->validateExamination($request, $kunjungan);

            $data['dokter_id'] = auth()->user()->role === 'dokter'
                ? $this->currentDoctorId()
                : ($data['dokter_id'] ?? $kunjungan->dokter_id);
            $kunjungan->pemeriksaan()->updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

            return back()->with('success', 'Data pemeriksaan berhasil diperbarui.');
        });
    }

    public function storeDiagnosa(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.storeDiagnosa', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $manual = $request->boolean('is_manual');
            $data = $request->validate([
                'is_manual' => ['sometimes', 'boolean'],
                'kode_icd10' => [$manual ? 'prohibited' : 'required', 'nullable', 'string', 'max:100'],
                'code_system' => ['sometimes', 'nullable', 'string', 'max:100'],
                'code_release' => ['sometimes', 'nullable', 'string', 'max:100'],
                'nama_diagnosa' => ['required', 'string', 'max:255'],
                'keterangan' => ['nullable', 'string', 'max:2000'],
                'jenis' => ['required', 'in:utama,tambahan'],
            ]);
            $data['nama_diagnosa'] = ClinicalAnswer::normalize(preg_replace('/\s+/u', ' ', trim($data['nama_diagnosa'])));
            if ($data['nama_diagnosa'] === null) {
                throw ValidationException::withMessages(['nama_diagnosa' => 'Tuliskan diagnosis yang ditetapkan dokter.']);
            }
            $codeSystem = $manual ? 'manual' : ($data['code_system'] ?? 'icd10_who');
            if ($manual) {
                $data['kode_icd10'] = null;
                $data['code_release'] = null;
            } else {
                if ($codeSystem === 'manual') {
                    throw ValidationException::withMessages(['code_system' => 'Gunakan pilihan input diagnosis manual untuk diagnosis tanpa kode.']);
                }
                $catalog = ClinicalTerminology::query()->where('code_system', $codeSystem)->where('code_type', 'diagnosis')->where('is_active', true);
                if ($codeSystem === 'icd9cm_procedure') {
                    throw ValidationException::withMessages(['code_system' => 'Kode prosedur ICD-9-CM harus dicatat pada tindakan, bukan diagnosis.']);
                }
                if (in_array($codeSystem, ['icd10_who', 'icd10_cm'], true) && preg_match('/^[A-Z]/i', $data['kode_icd10']) !== 1) {
                    throw ValidationException::withMessages(['kode_icd10' => 'Kode diagnosis ICD-10 harus diawali huruf. Catat kode prosedur ICD-9-CM pada tindakan.']);
                }
                if (! in_array($codeSystem, ['icd10_who', 'icd10_cm', 'icd9cm_diagnosis'], true) && ! $catalog->exists()) {
                    throw ValidationException::withMessages(['code_system' => 'Sistem kode belum memiliki katalog diagnosis yang dapat digunakan.']);
                }
                if ($catalog->exists()) {
                    $canonical = (clone $catalog)->where('code', $data['kode_icd10'])
                        ->when($data['code_release'] ?? null, fn ($query, string $release) => $query->where('release', $release))
                        ->orderByDesc('updated_at')->first();
                    if (! $canonical) {
                        throw ValidationException::withMessages(['kode_icd10' => 'Kode tidak ditemukan pada katalog terminologi yang dipilih.']);
                    }
                    $data['nama_diagnosa'] = $canonical->display;
                    $data['code_release'] = $canonical->release;
                }
            }

            $pemeriksaan = $kunjungan->pemeriksaan()->firstOrCreate(['kunjungan_id' => $kunjungan->id], ['status' => 'draft', 'dokter_id' => $kunjungan->dokter_id]);

            $duplicate = $pemeriksaan->diagnosa()->where('code_system', $codeSystem);
            if ($manual) {
                $duplicate->whereRaw('LOWER(nama_diagnosa) = ?', [mb_strtolower($data['nama_diagnosa'])]);
            } else {
                $duplicate->where('kode_icd10', $data['kode_icd10']);
            }
            if ($duplicate->exists()) {
                throw ValidationException::withMessages([$manual ? 'nama_diagnosa' : 'kode_icd10' => 'Diagnosis ini sudah tercatat. Hapus entri sebelumnya jika perlu dikoreksi.']);
            }
            if ($data['jenis'] === 'utama') {
                $pemeriksaan->diagnosa()->where('jenis', 'utama')->update(['jenis' => 'tambahan']);
            }

            $pemeriksaan->diagnosa()->create([
                'kode_icd10' => $data['kode_icd10'],
                'code_system' => $codeSystem,
                'code_release' => $data['code_release'] ?? null,
                'nama_diagnosa' => $data['nama_diagnosa'],
                'keterangan' => ClinicalAnswer::normalize($data['keterangan'] ?? null),
                'jenis' => $data['jenis'],
            ]);

            return back()->with('success', 'Diagnosa berhasil ditambahkan.');
        });
    }

    public function destroyDiagnosa(Diagnosa $diagnosa): RedirectResponse
    {
        return DB::transaction(function () use ($diagnosa): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($diagnosa->pemeriksaan->kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record(request(), 'clinical_draft.destroyDiagnosa', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($diagnosa->load('pemeriksaan.kunjungan')->pemeriksaan->kunjungan);
            $diagnosa->delete();

            return back()->with('success', 'Diagnosa berhasil dihapus.');
        });
    }

    public function storeResep(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.storeResep', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $isExternal = $request->boolean('is_resep_luar');
            $data = $request->validate([
                'obat_id' => [$isExternal ? 'nullable' : 'required', 'exists:obat,id'],
                'nama_obat' => [$isExternal ? 'required' : 'nullable', 'string', 'max:255'],
                'jumlah' => $isExternal ? ['required', 'numeric', 'min:0.01'] : ['required', 'integer', 'min:1'],
                'satuan' => ['nullable', 'string'],
                'aturan_pakai' => ['nullable', 'string'],
                'catatan' => ['nullable', 'string'],
                'jenis' => ['required', 'in:jadi,racikan'],
                'is_resep_luar' => ['sometimes', 'boolean'],
            ]);
            $data['aturan_pakai'] = filled($data['aturan_pakai'] ?? null) ? trim($data['aturan_pakai']) : null;
            $data['catatan'] = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;
            $data['is_resep_luar'] = $isExternal;
            if ($data['is_resep_luar']) {
                $data['obat_id'] = null;
            }

            DB::transaction(function () use ($data, $kunjungan): void {
                $resep = $kunjungan->resep()->firstOrCreate(
                    ['kunjungan_id' => $kunjungan->id],
                    [
                        'no_resep' => 'RSP-'.now()->format('Ymd').'-'.str_pad($kunjungan->id, 4, '0', STR_PAD_LEFT),
                        'dokter_id' => auth()->user()->role === 'dokter' ? $this->currentDoctorId() : $kunjungan->dokter_id,
                        'status' => 'menunggu',
                    ]
                );

                abort_unless($resep->status === 'menunggu', 422, 'Resep yang sudah diproses tidak dapat diubah.');

                $obat = ! empty($data['obat_id']) ? Obat::whereKey($data['obat_id'])->lockForUpdate()->firstOrFail() : null;
                $isResepLuar = (bool) ($data['is_resep_luar'] ?? false);
                if (! $isResepLuar && (! $obat || ! $obat->is_active || $obat->jenis !== 'obat')) {
                    throw ValidationException::withMessages(['obat_id' => 'Pilih obat aktif dari persediaan klinik.']);
                }

                $item = $resep->resepObat()->create([
                    'obat_id' => $data['obat_id'] ?? null,
                    'nama_obat' => $isResepLuar ? $data['nama_obat'] : $obat->nama,
                    'jumlah' => $data['jumlah'],
                    'satuan' => $isResepLuar ? ($data['satuan'] ?? null) : $obat->satuan_kecil,
                    'aturan_pakai' => $data['aturan_pakai'],
                    'catatan' => $data['catatan'] ?? null,
                    'jenis' => $data['jenis'],
                    'is_resep_luar' => $isResepLuar,
                    'stok_dikurangi' => false,
                ]);

            });

            return back()->with('success', 'Obat berhasil ditambahkan ke resep.');
        });
    }

    public function destroyResep(ResepObat $resepObat): RedirectResponse
    {
        return DB::transaction(function () use ($resepObat): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($resepObat->resep->kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record(request(), 'clinical_draft.destroyResep', $kunjungan->pasien_id, $kunjungan->id);
            $resepObat->load('resep.kunjungan', 'obat');
            $this->authorizeMutableVisit($resepObat->resep->kunjungan);
            abort_unless($resepObat->resep->status === 'menunggu', 422, 'Resep yang sudah diproses tidak dapat diubah.');

            DB::transaction(function () use ($resepObat): void {
                abort_if($resepObat->stok_dikurangi, 422, 'Rekonsiliasi reservasi stok lama sebelum menghapus resep.');
                $resepObat->delete();
            });

            return back()->with('success', 'Obat berhasil dihapus dari resep.');
        });
    }

    public function storeTindakan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.storeTindakan', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $data = $request->validate([
                'terminology_id' => ['nullable', 'integer'],
                'tindakan_id' => ['nullable', 'integer', 'exists:tindakan,id'],
                'nama_tindakan_manual' => ['nullable', 'string', 'min:3', 'max:255'],
                'execution_role' => ['sometimes', 'required', Rule::in(['perawat', 'dokter'])],
                'jumlah' => ['required', 'integer', 'min:1'],
                'catatan' => ['nullable', 'string', 'max:2000'],
                'tooth_fdi' => ['nullable', Rule::in(OdontogramFinding::toothCodes())],
                'tooth_fdis' => ['nullable', 'array', 'max:32'],
                'tooth_fdis.*' => ['required', 'distinct', Rule::in(OdontogramFinding::toothCodes())],
            ]);
            $data['catatan'] = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;

            $hasTerminology = filled($data['terminology_id'] ?? null);
            $hasClinicTreatment = filled($data['tindakan_id'] ?? null);
            $hasManualName = filled($data['nama_tindakan_manual'] ?? null);
            if ((! $hasTerminology && ! $hasClinicTreatment && ! $hasManualName)
                || ($hasManualName && ($hasTerminology || $hasClinicTreatment))) {
                throw ValidationException::withMessages(['tindakan_id' => 'Pilih tindakan ICD-9-CM, tindakan klinik, atau isi nama tindakan manual. Nama manual tidak dapat digabung dengan pilihan katalog.']);
            }

            $terminology = null;
            if ($hasTerminology) {
                $terminology = ClinicalTerminology::query()->where('code_system', 'icd9cm_procedure')
                    ->where('code_type', 'procedure')->where('is_active', true)->find($data['terminology_id']);
                if (! $terminology) {
                    throw ValidationException::withMessages(['terminology_id' => 'Pilih kode prosedur ICD-9-CM yang masih aktif dari hasil pencarian.']);
                }
            }

            $toothFdis = collect($data['tooth_fdis'] ?? [])
                ->when(filled($data['tooth_fdi'] ?? null), fn ($teeth) => $teeth->push($data['tooth_fdi']))
                ->unique()
                ->values();

            abort_if($toothFdis->isNotEmpty() && $kunjungan->poliklinik?->jenis !== 'gigi', 422, 'Lokasi gigi hanya untuk kunjungan Poli Gigi.');
            if (! empty($data['tooth_fdis'])) {
                $recordedTeeth = $kunjungan->odontogramFindings()
                    ->whereIn('tooth_fdi', $data['tooth_fdis'])
                    ->distinct()
                    ->pluck('tooth_fdi');
                $unrecordedTeeth = collect($data['tooth_fdis'])->diff($recordedTeeth);

                if ($unrecordedTeeth->isNotEmpty()) {
                    throw ValidationException::withMessages(['tooth_fdis' => 'Tindakan hanya dapat ditautkan ke gigi yang memiliki temuan odontogram.']);
                }
            }

            $tindakanId = null;
            $manualName = null;
            $treatmentName = $terminology?->display;
            if ($hasClinicTreatment) {
                $tindakan = Tindakan::query()->where('is_active', true)->findOrFail($data['tindakan_id']);
                if ($tindakan->kategori === 'lab' && ! config('clinic.modules.laboratory', false)) {
                    throw ValidationException::withMessages(['tindakan_id' => 'Layanan laboratorium tidak tersedia. Pilih tindakan klinik yang aktif.']);
                }
                abort_unless($tindakan->poliklinik_id === $kunjungan->poliklinik_id, 422, 'Tindakan tidak tersedia di poliklinik kunjungan ini.');
                $tindakanId = $tindakan->id;
                $treatmentName ??= $tindakan->nama;
            } elseif ($hasManualName) {
                $manualName = ClinicalAnswer::normalize(preg_replace('/\s+/u', ' ', trim($data['nama_tindakan_manual'])));
                if ($manualName === null || mb_strlen($manualName) < 3) {
                    throw ValidationException::withMessages(['nama_tindakan_manual' => 'Tuliskan tindakan yang dilakukan, minimal 3 karakter.']);
                }
            }

            foreach ($toothFdis->isNotEmpty() ? $toothFdis : collect([null]) as $toothFdi) {
                $kunjungan->tindakanKunjungan()->create([
                    'tindakan_id' => $tindakanId,
                    'nama_tindakan_manual' => $manualName,
                    'execution_role' => $data['execution_role'] ?? 'perawat',
                    'execution_status' => 'pending',
                    'nama_tindakan' => $treatmentName ?? $manualName,
                    'kode_icd9cm' => $terminology?->code,
                    'code_system' => $terminology?->code_system,
                    'code_release' => $terminology?->release,
                    'dokter_id' => $kunjungan->dokter_id,
                    'jumlah' => $data['jumlah'],
                    'tooth_fdi' => $toothFdi,
                    'catatan' => $data['catatan'] ?? null,
                ]);
            }

            $message = $toothFdis->count() > 1
                ? 'Tindakan berhasil ditambahkan ke '.$toothFdis->count().' gigi.'
                : 'Tindakan berhasil ditambahkan.';

            return back()->with('success', $message);
        });
    }

    public function destroyTindakan(TindakanKunjungan $tindakanKunjungan): RedirectResponse
    {
        return DB::transaction(function () use ($tindakanKunjungan): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($tindakanKunjungan->kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record(request(), 'clinical_draft.destroyTindakan', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($tindakanKunjungan->load('kunjungan')->kunjungan);
            $tindakanKunjungan->delete();

            return back()->with('success', 'Tindakan berhasil dihapus.');
        });
    }

    public function storeSurat(Request $request, Kunjungan $kunjungan, CorrespondenceService $correspondence): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan, $correspondence): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.storeSurat', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $data = $request->validate([
                'jenis' => ['required', 'in:sakit,sehat,rujukan,lainnya'],
                'konten' => ['nullable', 'string', 'max:10000'],
                'catatan' => ['nullable', 'string', 'max:2000'],
                'nomor_surat' => ['nullable', 'string', 'max:120'],
                'tanggal' => ['required', 'date'],
                'surat_template_id' => ['nullable', 'exists:correspondence_templates,id'],
            ]);
            $data['catatan'] = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;

            $template = filled($data['surat_template_id'] ?? null)
                ? CorrespondenceTemplate::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['surat_template_id'])
                : null;
            if ($template && $template->jenis !== $data['jenis']) {
                throw ValidationException::withMessages(['surat_template_id' => 'Template tidak sesuai dengan jenis surat.']);
            }

            $kunjungan->loadMissing(['pasien', 'dokter']);
            $date = Carbon::parse($data['tanggal']);
            $number = $template ? $correspondence->nextNumber($template, $date) : ($data['nomor_surat'] ?? null);
            $content = filled($data['konten'] ?? null) ? $data['konten'] : $template?->isi;
            if ($content !== null) {
                $content = $correspondence->renderText($content, [
                    '[nomor]' => (string) ($number ?? ''),
                    '[pasien]' => $kunjungan->pasien->nama,
                    '[no_rm]' => $kunjungan->pasien->no_rm,
                    '[dokter]' => $kunjungan->dokter?->nama ?? 'Dokter klinik',
                    '[poli]' => $kunjungan->poliklinik?->nama ?? '',
                    '[tanggal]' => $date->locale('id')->isoFormat('D MMMM Y'),
                ]);
            }

            $kunjungan->suratMedis()->create([
                'dokter_id' => $kunjungan->dokter_id,
                'surat_template_id' => $template?->id,
                'jenis' => $data['jenis'],
                'konten' => $content,
                'nomor_surat' => $number,
                'tanggal' => $date,
            ]);

            return back()->with('success', 'Surat medis berhasil dibuat.');
        });
    }

    public function storeRujukan(Request $request, Kunjungan $kunjungan, CorrespondenceService $correspondence): RedirectResponse
    {
        return DB::transaction(function () use ($request, $kunjungan, $correspondence): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($kunjungan);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.storeRujukan', $kunjungan->pasien_id, $kunjungan->id);
            $this->authorizeMutableVisit($kunjungan);
            $data = $request->validate([
                'ke_poli_id' => ['required', 'exists:poliklinik,id'],
                'catatan' => ['nullable', 'string', 'max:5000'],
                'konten_surat' => ['nullable', 'string', 'max:10000'],
                'surat_template_id' => ['nullable', 'exists:correspondence_templates,id'],
            ]);

            $targetClinic = Poliklinik::query()->where('is_active', true)->findOrFail($data['ke_poli_id']);
            abort_unless($targetClinic->id !== $kunjungan->poliklinik_id, 422, 'Poliklinik tujuan harus berbeda dengan poli asal.');
            $template = filled($data['surat_template_id'] ?? null)
                ? CorrespondenceTemplate::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['surat_template_id'])
                : null;
            if ($template && $template->jenis !== 'rujukan_internal') {
                throw ValidationException::withMessages(['surat_template_id' => 'Pilih template rujukan internal.']);
            }

            $kunjungan->loadMissing(['pasien', 'dokter', 'poliklinik', 'pemeriksaan']);
            $date = Carbon::parse($kunjungan->tanggal);
            $number = $template ? $correspondence->nextNumber($template, $date) : null;
            $content = filled($data['konten_surat'] ?? null) ? $data['konten_surat'] : $template?->isi;
            if ($content !== null) {
                $content = $correspondence->renderText($content, [
                    '[nomor]' => (string) ($number ?? ''),
                    '[pasien]' => $kunjungan->pasien->nama,
                    '[no_rm]' => $kunjungan->pasien->no_rm,
                    '[dokter]' => $kunjungan->dokter?->nama ?? 'Dokter klinik',
                    '[poli]' => $kunjungan->poliklinik->nama,
                    '[poli_tujuan]' => $targetClinic->nama,
                    '[tanggal]' => $date->locale('id')->isoFormat('D MMMM Y'),
                ]);
            }

            $kunjungan->rujukanInternal()->create([
                'dari_poli_id' => $kunjungan->poliklinik_id,
                'ke_poli_id' => $targetClinic->id,
                'surat_template_id' => $template?->id,
                'nomor_surat' => $number,
                'konten_surat' => $content,
                'catatan' => $data['catatan'] ?? null,
                'status' => 'menunggu',
            ]);

            return back()->with('success', 'Rujukan internal berhasil dibuat.');
        });
    }

    public function selesai(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $request->validate([
            'signature_password' => ['required', 'current_password'],
        ], [
            'signature_password.current_password' => 'Kata sandi akun tidak sesuai; pemeriksaan belum ditandatangani.',
        ]);

        DB::transaction(function () use ($request, $kunjungan, $noteRecorder, $auditRecorder): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($lockedVisit);
            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination, 422, 'Simpan hasil pemeriksaan sebelum menyelesaikan kunjungan.');
            abort_unless(
                ClinicalAnswer::normalize($examination->anamnesis) !== null && ClinicalAnswer::normalize($examination->pemeriksaan_fisik) !== null,
                422,
                'Anamnesis dan pemeriksaan fisik wajib diisi sebelum finalisasi.'
            );
            abort_unless($examination->diagnosa()->where('jenis', 'utama')->count() === 1, 422, 'Pastikan tepat satu diagnosis utama sebelum finalisasi.');
            if ($lockedVisit->poliklinik?->jenis === 'gigi') {
                abort_unless($lockedVisit->odontogramFindings()->exists(), 422, 'Catat temuan odontogram sebelum finalisasi kunjungan gigi.');
            }

            $procedures = $lockedVisit->tindakanKunjungan()->with('tindakan.bhp.obat')->where('execution_role', 'dokter')->orderBy('id')->lockForUpdate()->get();
            $supplyIds = $procedures->flatMap(fn (TindakanKunjungan $procedure) => $procedure->tindakan?->bhp->pluck('obat_id') ?? collect())->unique()->sort()->values();
            Obat::whereIn('id', $supplyIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($procedures as $procedure) {
                app(VisitCareWorkflow::class)->consumeSupplies($procedure, $lockedVisit, $request->user());
                $procedure->update(['execution_status' => 'completed', 'performed_by' => $request->user()->id, 'performed_at' => now(), 'execution_result' => $procedure->catatan]);
            }

            $noteRecorder->recordFinal($examination, $noteRecorder->snapshot($lockedVisit, $examination), $request->user()->id);
            $examination->update([
                'status' => 'selesai',
                'signed_by_user_id' => $request->user()->id,
                'signed_at' => now(),
            ]);
            $this->allergies->remember($lockedVisit, $examination->riwayat_alergi);

            app(VisitCareWorkflow::class)->advance($lockedVisit);

            $auditRecorder->record($request, 'clinical_examination.finalize', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return redirect()->route('pelayanan.pemeriksaan.index')
            ->with('success', 'Pemeriksaan selesai. Pasien diteruskan ke tahap berikutnya.');
    }

    public function addendum(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $isGeneralClinic = $kunjungan->poliklinik?->jenis === 'umum';
        $isDentalClinic = $kunjungan->poliklinik?->jenis === 'gigi';
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'anamnesis' => ['sometimes', 'nullable', 'string'],
            'riwayat_penyakit_sekarang' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'riwayat_penyakit_dahulu' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'riwayat_penyakit_keluarga' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'riwayat_alergi' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'pemeriksaan_fisik' => ['sometimes', 'nullable', 'string'],
            'pemeriksaan_fisik_terstruktur' => ['sometimes', Rule::excludeIf(! $isGeneralClinic), 'nullable', 'array'],
            'pemeriksaan_fisik_terstruktur.*' => ['nullable', 'string', 'max:3000'],
            'body_map' => ['sometimes', Rule::excludeIf(! $isGeneralClinic), 'nullable', new BodyMapPayload],
            'pemeriksaan_ekstraoral' => ['sometimes', Rule::excludeIf(! $isDentalClinic), 'nullable', 'string', 'max:5000'],
            'diagnosis_banding' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'catatan' => ['sometimes', 'nullable', 'string'],
            'edukasi' => ['sometimes', 'nullable', 'string'],
            'non_farmakologis' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'kontrol_berikutnya' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $fields = array_intersect_key($data, array_flip([
            'anamnesis', 'riwayat_penyakit_sekarang', 'riwayat_penyakit_dahulu',
            'riwayat_penyakit_keluarga', 'riwayat_alergi', 'pemeriksaan_fisik',
            'pemeriksaan_fisik_terstruktur', 'pemeriksaan_ekstraoral', 'body_map',
            'diagnosis_banding', 'catatan', 'edukasi', 'non_farmakologis', 'kontrol_berikutnya',
        ]));
        if ($fields === []) {
            throw ValidationException::withMessages(['reason' => 'Isi setidaknya satu bagian catatan yang dikoreksi.']);
        }
        if (mb_strlen(trim($data['reason'])) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan koreksi minimal 10 karakter setelah spasi dihapus.']);
        }

        DB::transaction(function () use ($request, $kunjungan, $noteRecorder, $auditRecorder, $data, $fields): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($lockedVisit);
            abort_unless(in_array($lockedVisit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true), 422, 'Kunjungan tidak dalam status yang dapat dikoreksi.');
            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at, 422, 'Catatan lama belum difinalisasi dengan versi yang dapat dikoreksi.');
            abort_unless($noteRecorder->verify($examination), 422, 'Integritas riwayat catatan tidak valid; koreksi dihentikan.');
            $latestVersion = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
            $payload = $latestVersion->payload;

            foreach ($fields as $field => $value) {
                $normalized = is_string($value) ? ClinicalAnswer::normalize($value) : $value;
                if ($field === 'pemeriksaan_fisik_terstruktur' && is_array($normalized)) {
                    $normalized = collect($normalized)
                        ->map(fn (mixed $entry): ?string => filled($entry) ? trim((string) $entry) : null)
                        ->filter(fn (?string $entry): bool => $entry !== null)
                        ->all();
                }
                if (in_array($field, ['anamnesis', 'pemeriksaan_fisik'], true) && blank($normalized)) {
                    throw ValidationException::withMessages([$field => 'Bagian klinis utama tidak boleh dikosongkan.']);
                }

                if ($field === 'body_map' && is_array($normalized)) {
                    $normalized = BodyMapPayload::normalize($normalized);
                }
                $payload[$field] = blank($normalized) ? null : $normalized;
            }

            if ($payload === $latestVersion->payload) {
                throw ValidationException::withMessages(['reason' => 'Tidak ada perubahan catatan untuk dibuatkan addendum.']);
            }

            $noteRecorder->recordAddendum($examination, $payload, $request->user()->id, trim($data['reason']));
            if (array_key_exists('riwayat_alergi', $fields)) {
                $this->allergies->remember($lockedVisit, $payload['riwayat_alergi']);
            }
            $auditRecorder->record($request, 'clinical_examination.addendum', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Addendum tersimpan tanpa mengubah catatan final sebelumnya.');
    }

    public function storeOdontogram(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $data = $this->validateDentalFinding($request);

        DB::transaction(function () use ($request, $kunjungan, $data, $auditRecorder): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($lockedVisit);
            abort_unless($lockedVisit->poliklinik?->jenis === 'gigi', 422, 'Odontogram hanya untuk kunjungan Poli Gigi.');

            $lockedVisit->odontogramFindings()->updateOrCreate(
                ['tooth_fdi' => $data['tooth_fdi'], 'surface' => $data['surface']],
                ['finding_code' => $data['finding_code'], 'notes' => $data['notes'] ?? null, 'recorded_by_user_id' => $request->user()->id]
            );
            $auditRecorder->record($request, 'odontogram.draft_update', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Temuan odontogram tersimpan.');
    }

    public function destroyOdontogram(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $request->validate([
            'tooth_fdi' => ['required', Rule::in(OdontogramFinding::toothCodes())],
            'surface' => ['required', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $kunjungan, $noteRecorder, $auditRecorder, $data): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($lockedVisit);
            abort_unless($lockedVisit->poliklinik?->jenis === 'gigi', 422, 'Odontogram hanya untuk kunjungan Poli Gigi.');

            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            if (! $examination?->signed_at) {
                $this->authorizeMutableVisit($lockedVisit);
                $finding = $lockedVisit->odontogramFindings()
                    ->where('tooth_fdi', $data['tooth_fdi'])
                    ->where('surface', $data['surface'])
                    ->firstOrFail();
                $finding->delete();
                $auditRecorder->record($request, 'odontogram.draft_undo', $lockedVisit->pasien_id, $lockedVisit->id);

                return;
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if (mb_strlen($reason) < 10) {
                throw ValidationException::withMessages(['reason' => 'Alasan pembatalan temuan final minimal 10 karakter.']);
            }

            abort_unless(in_array($lockedVisit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true), 422, 'Kunjungan tidak dalam status yang dapat dikoreksi.');
            abort_unless($noteRecorder->verify($examination), 422, 'Integritas riwayat catatan tidak valid; koreksi dihentikan.');

            $latestVersion = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
            $payload = $latestVersion->payload;
            $findings = collect($payload['odontogram'] ?? []);
            $matchingFinding = $findings->contains(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === $data['surface']);
            abort_unless($matchingFinding, 404, 'Temuan tidak ditemukan pada versi RME terbaru.');

            $payload['odontogram'] = $findings
                ->reject(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === $data['surface'])
                ->values()->all();
            $noteRecorder->recordAddendum($examination, $payload, $request->user()->id, $reason);
            $auditRecorder->record($request, 'odontogram.addendum_undo', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Temuan dibatalkan; riwayat versi sebelumnya tetap tersimpan.');
    }

    public function addendumOdontogram(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $this->validateDentalFinding($request);
        $reason = trim($request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']])['reason']);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan koreksi minimal 10 karakter setelah spasi dihapus.']);
        }

        DB::transaction(function () use ($request, $kunjungan, $noteRecorder, $auditRecorder, $data, $reason): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($lockedVisit);
            abort_unless($lockedVisit->poliklinik?->jenis === 'gigi', 422, 'Odontogram hanya untuk kunjungan Poli Gigi.');
            abort_unless(in_array($lockedVisit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true), 422, 'Kunjungan tidak dalam status yang dapat dikoreksi.');
            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at, 422, 'Odontogram belum difinalisasi.');
            abort_unless($noteRecorder->verify($examination), 422, 'Integritas riwayat catatan tidak valid; koreksi dihentikan.');

            $latestVersion = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
            $payload = $latestVersion->payload;
            $findings = collect($payload['odontogram'] ?? []);
            $replacement = [
                'tooth_fdi' => $data['tooth_fdi'],
                'surface' => $data['surface'],
                'finding_code' => $data['finding_code'],
                'notes' => $data['notes'] ?? null,
            ];
            $existing = $findings->first(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === $data['surface']);
            if ($existing === $replacement) {
                throw ValidationException::withMessages(['finding_code' => 'Temuan sama dengan versi terakhir.']);
            }

            $payload['odontogram'] = $findings
                ->reject(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === $data['surface'])
                ->push($replacement)
                ->sortBy(fn (array $finding): string => $finding['tooth_fdi'].$finding['surface'])
                ->values()->all();
            $noteRecorder->recordAddendum($examination, $payload, $request->user()->id, $reason);
            $auditRecorder->record($request, 'odontogram.addendum', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Addendum odontogram tersimpan; temuan final awal tetap ada.');
    }

    public function searchIcd9cm(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $search = trim($filters['q']);
        $codeSearch = preg_match('/^\d{3,4}$/', $search) === 1
            ? substr($search, 0, 2).'.'.substr($search, 2)
            : $search;
        $keywords = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $results = ClinicalTerminology::query()
            ->where('code_system', 'icd9cm_procedure')
            ->where('code_type', 'procedure')
            ->where('is_active', true)
            ->where(function ($query) use ($codeSearch, $keywords): void {
                $query->where('code', 'like', '%'.addcslashes($codeSearch, '\\%_').'%')
                    ->orWhere(function ($query) use ($keywords): void {
                        foreach ($keywords as $keyword) {
                            $query->where('display', 'like', '%'.addcslashes($keyword, '\\%_').'%');
                        }
                    });
            })
            ->orderBy('code')->orderByDesc('updated_at')->orderByDesc('id')
            ->limit(20)->get(['id', 'code', 'display', 'release']);

        return response()->json($results);
    }

    public function searchIcd10(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'code_system' => ['nullable', 'string', 'max:100'],
        ]);
        $query = $filters['q'];
        $system = $filters['code_system'] ?? 'icd10_who';
        $keywords = collect(preg_split('/[^\pL\pN.-]+/u', $query) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()->take(6)->values();
        $catalog = ClinicalTerminology::query()
            ->where('code_system', $system)
            ->where('is_active', true)
            ->where('code_type', 'diagnosis')
            ->where(function ($builder) use ($query, $keywords): void {
                $builder->where('code', 'like', '%'.addcslashes($query, '\\%_').'%');
                foreach ($keywords as $keyword) {
                    $builder->orWhere('display', 'like', '%'.addcslashes($keyword, '\\%_').'%');
                }
            })
            ->orderByDesc('updated_at')
            ->orderBy('code')
            ->limit(50)
            ->get(['code as kode', 'display as nama', 'release'])
            ->map(fn ($item): array => ['kode' => $item->kode, 'nama' => $item->nama, 'code_system' => $system, 'release' => $item->release])
            ->unique('kode')->take(10)->values();

        $results = $catalog->isNotEmpty() || $system !== 'icd10_who'
            ? $catalog
            : Icd10::query()->where(function ($builder) use ($query, $keywords): void {
                $builder->where('kode', 'like', '%'.addcslashes($query, '\\%_').'%');
                foreach ($keywords as $keyword) {
                    $builder->orWhere('nama', 'like', '%'.addcslashes($keyword, '\\%_').'%');
                }
            })
                ->orderBy('kode')->limit(10)->get(['kode', 'nama'])
                ->map(fn (Icd10 $item): array => ['kode' => $item->kode, 'nama' => $item->nama, 'code_system' => 'icd10_who', 'release' => '']);

        return response()->json($results);
    }

    private function currentDoctorId(): int
    {
        return $this->doctorAccess->doctorId(auth()->user());
    }

    /**
     * @return array<int, int>
     */
    private function scheduledClinicIds(int $doctorId, string $date): array
    {
        return $this->doctorAccess->scheduledClinicIds($doctorId, $date);
    }

    private function isDoctorScheduledForVisit(int $doctorId, Kunjungan $kunjungan): bool
    {
        return in_array($kunjungan->poliklinik_id, $this->scheduledClinicIds($doctorId, $kunjungan->tanggal->toDateString()), true);
    }

    private function authorizeVisit(Kunjungan $kunjungan): void
    {
        if (auth()->user()->role === 'dokter') {
            $doctorId = $this->currentDoctorId();
            $isAssigned = $kunjungan->dokter_id === $doctorId;
            $canClaim = $kunjungan->dokter_id === null
                && $kunjungan->status === 'pemeriksaan'
                && $this->isDoctorScheduledForVisit($doctorId, $kunjungan);

            abort_unless($isAssigned || $canClaim, 403, 'Kunjungan bukan tanggung jawab atau jadwal dokter ini.');
        }
    }

    private function authorizeMutableVisit(Kunjungan $kunjungan): void
    {
        app(VisitSchedulingService::class)->ensureServiceDate($kunjungan);
        $this->authorizeVisit($kunjungan);

        if (auth()->user()->role === 'dokter') {
            abort_unless($kunjungan->dokter_id === $this->currentDoctorId(), 409, 'Mulai pemeriksaan terlebih dahulu sebelum mencatat pemeriksaan.');
        }

        abort_unless(
            $kunjungan->status === 'pemeriksaan' && $kunjungan->pemeriksaan?->status !== 'selesai' && ! $kunjungan->pemeriksaan?->signed_at,
            422,
            match (true) {
                (bool) $kunjungan->pemeriksaan?->signed_at, $kunjungan->pemeriksaan?->status === 'selesai' => 'Pemeriksaan telah difinalisasi dan tidak dapat diubah langsung.',
                $kunjungan->status === 'menunggu', $kunjungan->status === 'screening' => 'Selesaikan pengkajian awal sebelum mencatat pemeriksaan dokter.',
                $kunjungan->status === 'batal' => 'Kunjungan dibatalkan dan tidak dapat dilanjutkan.',
                default => 'Kunjungan tidak berada pada tahap pemeriksaan dokter.',
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExamination(Request $request, Kunjungan $kunjungan): array
    {
        $isGeneralClinic = $kunjungan->poliklinik?->jenis === 'umum';
        $isDentalClinic = $kunjungan->poliklinik?->jenis === 'gigi';

        $data = $request->validate([
            'section' => ['sometimes', 'required', Rule::in(['anamnesis', 'pemeriksaan', 'diagnosis', 'rencana'])],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string', 'max:10000'],
            'riwayat_penyakit_sekarang' => ['nullable', 'string', 'max:10000'],
            'riwayat_penyakit_dahulu' => ['nullable', 'string', 'max:10000'],
            'riwayat_penyakit_keluarga' => ['nullable', 'string', 'max:10000'],
            'riwayat_alergi' => ['nullable', 'string', 'max:5000'],
            'pemeriksaan_fisik' => ['nullable', 'string', 'max:10000'],
            'pemeriksaan_fisik_terstruktur' => [Rule::excludeIf(! $isGeneralClinic), 'nullable', 'array'],
            'pemeriksaan_fisik_terstruktur.*' => ['nullable', 'string', 'max:3000'],
            'body_map' => ['sometimes', Rule::excludeIf(! $isGeneralClinic), 'nullable', new BodyMapPayload],
            'pemeriksaan_ekstraoral' => [Rule::excludeIf(! $isDentalClinic), 'nullable', 'string', 'max:5000'],
            'diagnosis_banding' => ['nullable', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'edukasi' => ['nullable', 'string', 'max:5000'],
            'non_farmakologis' => ['nullable', 'string', 'max:5000'],
            'kontrol_berikutnya' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $section = $data['section'] ?? null;
        unset($data['section']);
        if ($section !== null) {
            $sectionFields = match ($section) {
                'anamnesis' => ['dokter_id', 'anamnesis', 'riwayat_penyakit_sekarang', 'riwayat_penyakit_dahulu', 'riwayat_penyakit_keluarga', 'riwayat_alergi'],
                'pemeriksaan' => ['pemeriksaan_fisik', 'pemeriksaan_fisik_terstruktur', 'pemeriksaan_ekstraoral', 'body_map'],
                'diagnosis' => ['diagnosis_banding'],
                'rencana' => ['kontrol_berikutnya', 'catatan', 'edukasi', 'non_farmakologis'],
            };
            $data = array_intersect_key($data, array_flip($sectionFields));
        }

        foreach ($data as $field => $value) {
            if ($field === 'body_map' && is_array($value)) {
                $data[$field] = BodyMapPayload::normalize($value);
            }
            if (is_string($value)) {
                $data[$field] = ClinicalAnswer::normalize($value);
            }

            if ($field === 'pemeriksaan_fisik_terstruktur' && is_array($value)) {
                $systems = collect($value)
                    ->map(fn (mixed $entry): ?string => filled($entry) ? trim((string) $entry) : null)
                    ->filter(fn (?string $entry): bool => $entry !== null)
                    ->all();
                $data[$field] = $systems === [] ? null : $systems;
            }
        }

        if ($section === null || $section === 'anamnesis') {
            $history = $this->clinicalHistory->forVisit($kunjungan);
            foreach (['riwayat_penyakit_dahulu' => 'riwayat_penyakit', 'riwayat_penyakit_keluarga' => 'riwayat_penyakit_keluarga'] as $field => $source) {
                if (blank($data[$field] ?? null)) {
                    $data[$field] = $history[$source];
                }
            }
        }
        if (($section === null || $section === 'anamnesis') && blank($data['riwayat_alergi'] ?? null)) {
            $data['riwayat_alergi'] = $this->allergies->forVisit($kunjungan)['riwayat_alergi'];
        }

        return $data;
    }

    /**
     * @return array{tooth_fdi: string, surface: string, finding_code: string, notes?: string|null}
     */
    private function validateDentalFinding(Request $request): array
    {
        $data = $request->validate([
            'tooth_fdi' => ['required', Rule::in(OdontogramFinding::toothCodes())],
            'surface' => ['required', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
            'finding_code' => ['required', Rule::in(array_keys(OdontogramFinding::findingLabels()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['notes'] = filled($data['notes'] ?? null) ? trim($data['notes']) : null;

        if (in_array($data['finding_code'], ['missing', 'unerupted'], true) && $data['surface'] !== 'W') {
            throw ValidationException::withMessages(['surface' => 'Temuan gigi hilang atau belum erupsi berlaku untuk seluruh gigi.']);
        }

        return $data;
    }
}
