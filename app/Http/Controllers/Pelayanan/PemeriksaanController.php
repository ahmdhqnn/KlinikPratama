<?php

namespace App\Http\Controllers\Pelayanan;

use App\AdminClinicalAssistance;
use App\ClinicalAnswer;
use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\CorrespondenceService;
use App\DentalAssessment;
use App\DoctorAccessService;
use App\Http\Controllers\Controller;
use App\MedicalLetterData;
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

    public function index(Request $request, ClinicalNoteRecorder $noteRecorder): Response
    {
        $filters = (new VisitQueryService)->filters($request);
        $doctorId = auth()->user()->role === 'dokter' ? $this->currentDoctorId() : null;
        $date = $filters['tanggal'] ?? '';
        $claimDate = $date ?: today()->toDateString();
        $visitQuery = $this->doctorAccess->visibleVisits($request->user(), $claimDate)
            ->whereIn('status', ['pemeriksaan', 'tindakan', 'farmasi', 'kasir', 'selesai'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
        $kunjungan = (clone $visitQuery)
            ->with(['pasien', 'poliklinik', 'dokter', 'screening', 'pemeriksaan.diagnosa', 'pemeriksaan.clinicalNoteVersions'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->whereHas('pasien', fn ($patients) => $patients
                ->where(fn ($names) => $names->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->when($date, fn ($query) => $query->whereDate('tanggal', $date))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $todayQueue = (clone $visitQuery)->with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan.diagnosa', 'pemeriksaan.clinicalNoteVersions'])
            ->whereDate('tanggal', today())->where('status', 'pemeriksaan')
            ->orderBy('created_at')->orderBy('id')->paginate(10, ['*'], 'today_page')->withQueryString();

        $patientIds = $kunjungan->getCollection()->pluck('pasien_id')->merge($todayQueue->getCollection()->pluck('pasien_id'))->unique();
        $recordPatientIds = Kunjungan::query()->where('dokter_id', $doctorId)
            ->whereIn('pasien_id', $patientIds)
            ->distinct()->pluck('pasien_id')->map(fn ($id): int => (int) $id)->all();

        $todayVisits = $this->doctorAccess->visibleVisits($request->user(), today()->toDateString())->whereDate('tanggal', today());
        $mapVisit = fn (Kunjungan $visit): array => [
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
            'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
            'canExamine' => $visit->canOpenExamination(),
            'diagnosisCount' => $visit->pemeriksaan?->diagnosa->count() ?? 0,
            'recordUrl' => ($request->user()->isAdmin() || ($doctorId !== null && in_array((int) $visit->pasien_id, $recordPatientIds, true))) ? route('pelayanan.pasien.rekam-medis', $visit->pasien_id) : null,
            'letterUrl' => $doctorId !== null && in_array($visit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true)
                && $visit->pemeriksaan?->signed_at && $noteRecorder->verify($visit->pemeriksaan)
                ? route('persuratan.surat.create', $visit)
                : null,
        ];

        return Inertia::render('pelayanan/pemeriksaan/index', [
            'canIssueLetters' => $request->user()->role === 'dokter',
            'stats' => [
                'total' => (clone $todayVisits)->whereIn('status', ['pemeriksaan', 'tindakan', 'farmasi', 'kasir', 'selesai'])->count(),
                'waiting' => (clone $todayVisits)->where('status', 'pemeriksaan')->whereDoesntHave('pemeriksaan')->count(),
                'inProgress' => (clone $todayVisits)->where('status', 'pemeriksaan')->whereHas('pemeriksaan', fn ($query) => $query->whereNull('signed_at'))->count(),
                'completed' => (clone $todayVisits)->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))->count(),
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
            'todayVisits' => [
                'data' => $todayQueue->getCollection()->map($mapVisit)->values(),
                'currentPage' => $todayQueue->currentPage(),
                'lastPage' => $todayQueue->lastPage(),
                'from' => $todayQueue->firstItem(),
                'to' => $todayQueue->lastItem(),
                'total' => $todayQueue->total(),
                'previousUrl' => $todayQueue->previousPageUrl(),
                'nextUrl' => $todayQueue->nextPageUrl(),
            ],
            'date' => $date,
            'search' => $filters['search'] ?? '',
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
            : Nakes::whereKey($kunjungan->dokter_id)->where('jabatan', 'dokter')->where('is_active', true)->get();
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
                'details' => $finding->details,
                'detailSummary' => DentalAssessment::detailSummary($finding->details),
            ])->all());

        $assistedWrite = app(AdminClinicalAssistance::class)->latestWrite($kunjungan);

        return Inertia::render('pelayanan/pemeriksaan/show', [
            'initialTab' => is_string($request->query('tab')) ? $request->query('tab') : 'anamnesis',
            'visit' => [
                'id' => $kunjungan->id,
                'attachments' => $kunjungan->attachmentSummaries(),
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'examinationStarted' => $kunjungan->pemeriksaan?->started_at !== null,
                'screeningSubmitted' => $kunjungan->screening?->submitted_at !== null,
                'doctorId' => $kunjungan->dokter_id,
                'canClaim' => auth()->user()->role === 'dokter' && $kunjungan->dokter_id === null,
                'isReservation' => $kunjungan->status === 'menunggu' && $kunjungan->tanggal?->isAfter(today()),
                'recordUrl' => (auth()->user()->isAdmin() || (auth()->user()->role === 'dokter' && $kunjungan->pasien->kunjungan()->where('dokter_id', $this->currentDoctorId())->exists())) ? route('pelayanan.pasien.rekam-medis', $kunjungan->pasien_id) : null,
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
                    'medicalHistory' => ClinicalAnswer::normalize($kunjungan->screening->riwayat_penyakit),
                    'familyHistory' => ClinicalAnswer::normalize($kunjungan->screening->riwayat_penyakit_keluarga),
                    'allergyHistory' => ClinicalAnswer::normalize($kunjungan->screening->riwayat_alergi),
                    'waistCircumference' => $kunjungan->screening->lingkar_perut,
                    'painScale' => $kunjungan->screening->skala_nyeri,
                    'fallRisk' => $kunjungan->screening->risiko_jatuh,
                    'triage' => $kunjungan->screening->kesimpulan_triase,
                    'priority' => $kunjungan->screening->prioritas_layanan,
                    'dentalPainLocation' => ClinicalAnswer::normalize($kunjungan->screening->lokasi_nyeri_gigi),
                    'dentalPainTriggers' => $kunjungan->screening->pemicu_nyeri_gigi ?? [],
                    'dentalPainDuration' => ClinicalAnswer::normalize($kunjungan->screening->durasi_keluhan_gigi),
                    'dentalMedicalRisks' => $kunjungan->screening->risiko_medis_gigi ?? [],
                    'dentalInfectionHistory' => $kunjungan->screening->riwayat_infeksi_gigi ?? [],
                    'dentalNotes' => ClinicalAnswer::normalize($kunjungan->screening->catatan_medis_gigi),
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
                    'dentalReview' => is_array($effectiveNote) ? ($effectiveNote['dental_review'] ?? null) : null,
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
                            'details' => $finding['details'] ?? null,
                            'detailSummary' => DentalAssessment::detailSummary($finding['details'] ?? null),
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
                    'toothFdi' => is_array($diagnosis) ? ($diagnosis['tooth_fdi'] ?? null) : $diagnosis->tooth_fdi,
                    'sourceFindingCode' => is_array($diagnosis) ? ($diagnosis['source_finding_code'] ?? null) : $diagnosis->source_finding_code,
                    'surfaces' => is_array($diagnosis) ? ($diagnosis['surfaces'] ?? []) : ($diagnosis->surfaces ?? []),
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
                    'details' => $finding['details'] ?? null,
                    'detailSummary' => DentalAssessment::detailSummary($finding['details'] ?? null),
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
            'assistanceReview' => $assistedWrite && ! $kunjungan->pemeriksaan?->signed_at ? ['auditId' => $assistedWrite->id, 'operator' => $assistedWrite->operator, 'lastEditedAt' => $assistedWrite->occurred_at, 'draftHash' => $kunjungan->pemeriksaan ? app(AdminClinicalAssistance::class)->draftHash($kunjungan, $kunjungan->pemeriksaan) : null] : null,
            'assistance' => ['isAdmin' => $request->user()->isAdmin(), 'active' => app(AdminClinicalAssistance::class)->active($request, $kunjungan)],
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
                'detailOptions' => DentalAssessment::detailOptions(),
                'teeth' => OdontogramFinding::toothCodes(),
                'surfaces' => OdontogramFinding::surfaceLabels(),
                'findings' => OdontogramFinding::findingLabels(),
                'findingGroups' => OdontogramFinding::findingGroups(),
                'wholeToothFindings' => OdontogramFinding::wholeToothFindings(),
                'diagnosisCatalog' => $kunjungan->poliklinik?->jenis === 'gigi' ? ClinicalTerminology::query()->where('is_active', true)->where('code_type', 'diagnosis')
                    ->whereIn('code', ['K02.0', 'K02.1', 'K02.9', 'K04.0', 'K04.01', 'K04.1', 'K08.3', 'S02.5'])
                    ->orderByDesc('updated_at')->get()->map(fn (ClinicalTerminology $term): array => ['code' => $term->code, 'name' => $term->display, 'codeSystem' => $term->code_system, 'release' => $term->release])->values()->all() : [],
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

    public function assist(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $audit): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if (is_string($request->input('reason'))) {
            $request->merge(['reason' => trim($request->input('reason'))]);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($request, $kunjungan, $audit, $data): void {
            $visit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            app(VisitSchedulingService::class)->ensureServiceDate($visit);
            abort_unless($visit->status === 'pemeriksaan' && $visit->canOpenExamination() && ! $visit->pemeriksaan?->signed_at && $visit->pemeriksaan?->status !== 'selesai', 422, 'Bantuan hanya untuk pemeriksaan yang masih dapat diisi.');
            abort_unless($visit->dokter?->is_active && $visit->dokter->jabatan === 'dokter', 422, 'Tetapkan dokter penanggung jawab aktif melalui kunjungan terlebih dahulu.');
            abort_unless($visit->poliklinik?->is_active && in_array($visit->poliklinik->jenis, ['umum', 'gigi'], true), 422);
            DB::table('admin_clinical_assistances')->updateOrInsert(
                ['actor_id' => $request->user()->id, 'kunjungan_id' => $visit->id],
                ['dokter_id' => $visit->dokter_id, 'poliklinik_id' => $visit->poliklinik_id, 'service_date' => $visit->tanggal->toDateString(), 'reason' => trim($data['reason']), 'expires_at' => now()->addHours(2), 'created_at' => now(), 'updated_at' => now()],
            );
            $audit->record($request, 'admin_assistance.started', $visit->pasien_id, $visit->id, ['doctor_id' => $visit->dokter_id, 'reason' => trim($data['reason'])]);
        });

        return back()->with('success', 'Bantuan draf aktif selama dua jam. Finalisasi dilakukan dokter penanggung jawab.');
    }

    public function start(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        DB::transaction(function () use ($request, $kunjungan, $auditRecorder): void {
            $visit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            app(VisitSchedulingService::class)->ensureServiceDate($visit);
            $this->authorizeVisit($visit);
            $doctorId = $request->user()->isAdmin() ? $visit->dokter_id : $this->currentDoctorId();

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
                'tooth_fdi' => ['nullable', Rule::prohibitedIf($kunjungan->poliklinik?->jenis !== 'gigi'), Rule::in(OdontogramFinding::toothCodes())],
                'surfaces' => ['sometimes', 'array', 'max:6', Rule::prohibitedIf($kunjungan->poliklinik?->jenis !== 'gigi')],
                'surfaces.*' => ['required', 'string', 'distinct', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
                'source_finding_code' => ['nullable', Rule::in([...array_keys(OdontogramFinding::findingLabels()), 'pulpal', 'periapical'])],
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

            $tooth = $data['tooth_fdi'] ?? null;
            $surfaces = $data['surfaces'] ?? [];
            if ((! $tooth && $surfaces !== []) || (in_array('W', $surfaces, true) && count($surfaces) > 1)) {
                throw ValidationException::withMessages(['surfaces' => 'Pilih gigi terlebih dahulu dan gunakan seluruh gigi atau permukaan spesifik secara terpisah.']);
            }
            if ($tooth) {
                $sourceFindings = $kunjungan->odontogramFindings()->where('tooth_fdi', $tooth)->get();
                if ($sourceFindings->isEmpty()) {
                    throw ValidationException::withMessages(['tooth_fdi' => 'Temuan gigi asal belum tersedia. Catat atau tinjau melalui Pemeriksaan & odontogram.']);
                }
                if (filled($data['source_finding_code'] ?? null) && ! $sourceFindings->contains(fn (OdontogramFinding $finding): bool => DentalAssessment::matchesDiagnosisSource($finding->toArray(), $data['source_finding_code']))) {
                    throw ValidationException::withMessages(['source_finding_code' => 'Temuan asal berubah. Tinjau pemeriksaan dan susun kembali diagnosis.']);
                }
                $specificSurfaces = array_values(array_diff($surfaces, ['W']));
                if (array_diff($specificSurfaces, $sourceFindings->pluck('surface')->all()) !== []) {
                    throw ValidationException::withMessages(['surfaces' => 'Permukaan diagnosis tidak sesuai temuan asal. Tinjau kembali Pemeriksaan & odontogram.']);
                }
            }
            if (! $tooth && filled($data['source_finding_code'] ?? null)) {
                throw ValidationException::withMessages(['source_finding_code' => 'Sumber temuan gigi memerlukan konteks gigi.']);
            }
            $duplicate = $pemeriksaan->diagnosa()->where('code_system', $codeSystem)->where('tooth_fdi', $tooth);
            if ($manual) {
                $duplicate->whereRaw('LOWER(nama_diagnosa) = ?', [mb_strtolower($data['nama_diagnosa'])]);
            } else {
                $duplicate->where('kode_icd10', $data['kode_icd10']);
            }
            $existing = $duplicate->first();
            if ($existing && ! $tooth) {
                throw ValidationException::withMessages([$manual ? 'nama_diagnosa' : 'kode_icd10' => 'Diagnosis ini sudah tercatat. Hapus entri sebelumnya jika perlu dikoreksi.']);
            }
            if ($data['jenis'] === 'utama') {
                $pemeriksaan->diagnosa()->where('jenis', 'utama')->update(['jenis' => 'tambahan']);
            }

            $surfaces = collect(['M', 'O', 'D', 'V', 'L', 'W'])->filter(fn (string $surface): bool => in_array($surface, [...($existing?->surfaces ?? []), ...$surfaces], true))->values()->all();
            if (count($surfaces) > 1) {
                $surfaces = array_values(array_diff($surfaces, ['W']));
            }
            $note = ClinicalAnswer::normalize($data['keterangan'] ?? null);
            if ($tooth) {
                $stripLocation = fn (?string $text): ?string => ClinicalAnswer::normalize(preg_replace('/^Gigi '.preg_quote($tooth, '/').'(?: \([^)]*\))?(?:[. —:]+)?/u', '', $text ?? ''));
                $notes = collect([$stripLocation($existing?->keterangan), $stripLocation($note)])
                    ->flatMap(fn (?string $text): array => explode("\n", $text ?? ''))->map(fn (string $text): string => trim($text))->filter()->unique()->implode("\n");
                $surfaceLabel = implode('', $surfaces);
                $surfaceLabel = $surfaceLabel === 'OD' ? 'DO' : $surfaceLabel;
                $note = 'Gigi '.$tooth.($surfaces ? ' ('.$surfaceLabel.')' : '').($notes ? ' — '.$notes : '');
                if (mb_strlen($note) > 2000) {
                    throw ValidationException::withMessages(['keterangan' => 'Gabungan catatan diagnosis melebihi 2000 karakter. Ringkas catatan sebelum disimpan.']);
                }
            }
            $attributes = [
                'kode_icd10' => $data['kode_icd10'],
                'code_system' => $codeSystem,
                'code_release' => $data['code_release'] ?? null,
                'nama_diagnosa' => $data['nama_diagnosa'],
                'keterangan' => $note,
                'jenis' => $data['jenis'] === 'utama' || $existing?->jenis === 'utama' ? 'utama' : 'tambahan',
                'tooth_fdi' => $tooth,
                'source_finding_code' => $data['source_finding_code'] ?? $existing?->source_finding_code,
                'surfaces' => $surfaces,
            ];
            if ($existing) {
                $existing->update($attributes);
            } else {
                $pemeriksaan->diagnosa()->create($attributes);
            }

            return back()->with('success', $existing ? 'Permukaan dan catatan digabung ke diagnosis gigi yang sudah tercatat.' : 'Diagnosis berhasil ditambahkan.');
        });
    }

    public function primaryDiagnosa(Request $request, Diagnosa $diagnosa): RedirectResponse
    {
        DB::transaction(function () use ($request, $diagnosa): void {
            $visit = Kunjungan::whereKey($diagnosa->pemeriksaan->kunjungan_id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($visit);
            $selected = $visit->pemeriksaan->diagnosa()->whereKey($diagnosa->id)->firstOrFail();
            $visit->pemeriksaan->diagnosa()->where('jenis', 'utama')->update(['jenis' => 'tambahan']);
            $selected->update(['jenis' => 'utama']);
            app(ClinicalAuditRecorder::class)->record($request, 'clinical_draft.primaryDiagnosa', $visit->pasien_id, $visit->id);
        });

        return back()->with('success', 'Diagnosis utama kunjungan diperbarui.');
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
                'tooth_fdis' => ['nullable', 'array', 'max:52'],
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
            if ($toothFdis->isNotEmpty()) {
                $recordedTeeth = $kunjungan->odontogramFindings()
                    ->whereIn('tooth_fdi', $toothFdis)
                    ->distinct()
                    ->pluck('tooth_fdi');
                $unrecordedTeeth = $toothFdis->diff($recordedTeeth);

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
                    'execution_role' => $data['execution_role'] ?? ($kunjungan->poliklinik?->jenis === 'gigi' ? 'dokter' : 'perawat'),
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

    public function storeSurat(Request $request, Kunjungan $kunjungan, CorrespondenceService $correspondence, ClinicalNoteRecorder $noteRecorder): RedirectResponse
    {
        abort_unless($request->user()->role === 'dokter', 403, 'Finalisasi, koreksi final dan penerbitan dokumen dilakukan dokter penanggung jawab.');

        return DB::transaction(function () use ($request, $kunjungan, $correspondence, $noteRecorder): RedirectResponse {
            $kunjungan = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($kunjungan);
            $signedExamination = $kunjungan->pemeriksaan?->signed_at !== null;
            if ($signedExamination) {
                abort_unless($request->user()->role === 'dokter' && in_array($kunjungan->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true), 422, 'Surat hanya dapat diterbitkan untuk pemeriksaan final yang tidak dibatalkan.');
                abort_unless($noteRecorder->verify($kunjungan->pemeriksaan), 422, 'Keutuhan catatan pemeriksaan perlu diverifikasi sebelum surat diterbitkan.');
            } else {
                $this->authorizeMutableVisit($kunjungan);
            }
            app(ClinicalAuditRecorder::class)->record($request, 'medical_letter.issue', $kunjungan->pasien_id, $kunjungan->id);
            $data = $request->validate([
                'jenis' => ['required', Rule::in(MedicalLetterData::TYPES)],
                'konten' => ['nullable', 'string', 'max:10000'],
                'catatan' => ['nullable', 'string', 'max:2000'],
                'tanggal' => ['required', 'date'],
                'surat_template_id' => ['nullable', 'exists:correspondence_templates,id'],
            ]);
            $details = app(MedicalLetterData::class)->validate($request);

            $template = filled($data['surat_template_id'] ?? null)
                ? CorrespondenceTemplate::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['surat_template_id'])
                : null;
            if ($template && $template->jenis !== $data['jenis']) {
                throw ValidationException::withMessages(['surat_template_id' => 'Template tidak sesuai dengan jenis surat.']);
            }

            $kunjungan->loadMissing(['pasien', 'dokter']);
            $date = Carbon::parse($data['tanggal']);
            $template ??= $correspondence->defaultTemplate($data['jenis']);
            $number = $correspondence->nextNumber($template, $date);
            $snapshot = app(MedicalLetterData::class)->snapshot($kunjungan, $kunjungan->dokter, $date);
            $content = filled($data['konten'] ?? null) ? $data['konten'] : $template?->isi;
            if ($content !== null) {
                $content = $correspondence->renderText($content, [
                    '[anamnesis]' => $snapshot['clinical']['anamnesis'] ?? '',
                    '[diagnosis]' => implode('; ', $snapshot['clinical']['diagnoses']),
                    '[terapi]' => $snapshot['clinical']['therapy'],
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
                'document_data' => $snapshot + ['details' => $details],
            ]);

            return back()->with('success', 'Surat medis berhasil dibuat.');
        }, attempts: 5);
    }

    public function storeRujukan(Request $request, Kunjungan $kunjungan, CorrespondenceService $correspondence): RedirectResponse
    {
        abort_unless($request->user()->role === 'dokter', 403, 'Finalisasi, koreksi final dan penerbitan dokumen dilakukan dokter penanggung jawab.');

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
            $template ??= $correspondence->defaultTemplate('rujukan_internal');
            $number = $correspondence->nextNumber($template, $date);
            $snapshot = app(MedicalLetterData::class)->snapshot($kunjungan, $kunjungan->dokter, $date);
            $content = filled($data['konten_surat'] ?? null) ? $data['konten_surat'] : $template?->isi;
            if ($content !== null) {
                $content = $correspondence->renderText($content, [
                    '[anamnesis]' => $snapshot['clinical']['anamnesis'] ?? '',
                    '[diagnosis]' => implode('; ', $snapshot['clinical']['diagnoses']),
                    '[terapi]' => $snapshot['clinical']['therapy'],
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
                'document_data' => $snapshot + ['fromClinic' => $kunjungan->poliklinik->nama, 'toClinic' => $targetClinic->nama],
                'catatan' => $data['catatan'] ?? null,
                'status' => 'menunggu',
            ]);

            return back()->with('success', 'Rujukan internal berhasil dibuat.');
        }, attempts: 5);
    }

    public function selesai(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        abort_unless($request->user()->role === 'dokter', 403, 'Finalisasi, koreksi final dan penerbitan dokumen dilakukan dokter penanggung jawab.');
        $request->validate([
            'signature_password' => ['required', 'current_password'],
        ], [
            'signature_password.current_password' => 'Kata sandi akun tidak sesuai; pemeriksaan belum ditandatangani.',
        ]);

        DB::transaction(function () use ($request, $kunjungan, $noteRecorder, $auditRecorder): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($lockedVisit);
            $assistedWrite = app(AdminClinicalAssistance::class)->latestWrite($lockedVisit);
            if ($assistedWrite) {
                $request->validate([
                    'review_admin_assistance' => ['required', 'accepted'],
                    'assistance_review_id' => ['required', 'integer', Rule::in([$assistedWrite->id])],
                ], [
                    'review_admin_assistance.required' => 'Tinjau dan konfirmasi draf bantuan admin sebelum menandatangani.',
                    'review_admin_assistance.accepted' => 'Tinjau dan konfirmasi draf bantuan admin sebelum menandatangani.',
                    'assistance_review_id.in' => 'Draf bantuan admin telah berubah. Muat ulang halaman dan tinjau kembali.',
                ]);
            }
            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination, 422, 'Simpan hasil pemeriksaan sebelum menyelesaikan kunjungan.');
            if ($assistedWrite) {
                $request->validate(['assistance_review_hash' => ['required', 'string', Rule::in([app(AdminClinicalAssistance::class)->draftHash($lockedVisit, $examination)])]], ['assistance_review_hash.in' => 'Isi draf telah berubah. Muat ulang halaman dan tinjau kembali.']);
            }
            abort_unless(ClinicalAnswer::normalize($examination->anamnesis) !== null, 422, 'Anamnesis wajib diisi sebelum finalisasi.');
            $hasPhysicalFinding = ClinicalAnswer::normalize($examination->pemeriksaan_fisik) !== null
                || filled($examination->pemeriksaan_fisik_terstruktur)
                || BodyMapPayload::hasContent($examination->body_map)
                || ClinicalAnswer::normalize($examination->pemeriksaan_ekstraoral) !== null
                || $lockedVisit->odontogramFindings()->exists();
            abort_unless($hasPhysicalFinding, 422, 'Catat setidaknya satu temuan pemeriksaan fisik sebelum finalisasi.');
            abort_unless($examination->diagnosa()->where('jenis', 'utama')->count() === 1, 422, 'Pastikan tepat satu diagnosis utama sebelum finalisasi.');
            if ($lockedVisit->poliklinik?->jenis === 'gigi') {
                abort_unless($lockedVisit->odontogramFindings()->exists(), 422, 'Catat temuan odontogram sebelum finalisasi kunjungan gigi.');
                abort_unless(ClinicalAnswer::normalize($examination->pemeriksaan_fisik) !== null && ClinicalAnswer::normalize($examination->pemeriksaan_ekstraoral) !== null, 422, 'Lengkapi pemeriksaan intraoral dan ekstraoral dokter sebelum finalisasi.');
                $request->validate(['review_dental_assessment' => ['required', 'accepted']], ['review_dental_assessment.required' => 'Konfirmasi pemeriksaan oral penuh dan tinjauan pengkajian perawat.', 'review_dental_assessment.accepted' => 'Konfirmasi pemeriksaan oral penuh dan tinjauan pengkajian perawat.']);
                $dentalFindings = $lockedVisit->odontogramFindings()->get();
                foreach ($examination->diagnosa()->whereNotNull('tooth_fdi')->get() as $diagnosis) {
                    $source = $dentalFindings->where('tooth_fdi', $diagnosis->tooth_fdi);
                    $specificSurfaces = array_diff($diagnosis->surfaces ?? [], ['W']);
                    if ($source->isEmpty() || array_diff($specificSurfaces, $source->pluck('surface')->all()) !== []
                        || (filled($diagnosis->source_finding_code) && ! $source->contains(fn (OdontogramFinding $finding): bool => DentalAssessment::matchesDiagnosisSource($finding->toArray(), $diagnosis->source_finding_code)))) {
                        throw ValidationException::withMessages(['dental_diagnoses' => 'Temuan asal diagnosis gigi '.$diagnosis->tooth_fdi.' sudah berubah. Tinjau pemeriksaan dan susun ulang diagnosis yang terkait.']);
                    }
                }
                foreach ($dentalFindings as $finding) {
                    $missing = DentalAssessment::missingCariesDetails($finding->toArray());
                    if ($missing !== []) {
                        throw ValidationException::withMessages(['dental_findings' => 'Gigi '.$finding->tooth_fdi.': lengkapi '.implode(', ', $missing).'.']);
                    }
                }

            }

            $procedures = $lockedVisit->tindakanKunjungan()->with('tindakan.bhp.obat')->where('execution_role', 'dokter')->orderBy('id')->lockForUpdate()->get();
            $supplyIds = $procedures->flatMap(fn (TindakanKunjungan $procedure) => $procedure->tindakan?->bhp->pluck('obat_id') ?? collect())->unique()->sort()->values();
            Obat::whereIn('id', $supplyIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($procedures as $procedure) {
                app(VisitCareWorkflow::class)->consumeSupplies($procedure, $lockedVisit, $request->user());
                $procedure->update(['execution_status' => 'completed', 'performed_by' => $request->user()->id, 'performed_at' => now(), 'execution_result' => $procedure->catatan]);
            }

            $lockedVisit->unsetRelation('tindakanKunjungan');
            $payload = $noteRecorder->snapshot($lockedVisit, $examination);
            if ($lockedVisit->poliklinik?->jenis === 'gigi') {
                $payload['dental_review'] = ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()->toIso8601String(), 'nurse_observation' => $lockedVisit->screening?->dentalObservationSummary() ?? []];
            }
            $finalVersion = $noteRecorder->recordFinal($examination, $payload, $request->user()->id);
            $examination->update([
                'status' => 'selesai',
                'signed_by_user_id' => $request->user()->id,
                'signed_at' => now(),
            ]);
            $this->allergies->remember($lockedVisit, $examination->riwayat_alergi);

            app(VisitCareWorkflow::class)->advance($lockedVisit);

            if ($assistedWrite) {
                $auditRecorder->record($request, 'admin_assistance.reviewed', $lockedVisit->pasien_id, $lockedVisit->id, [
                    'last_admin_write_id' => $assistedWrite->id,
                    'operator_id' => $assistedWrite->actor_id,
                    'clinical_note_version_id' => $finalVersion->id,
                    'content_hash' => $finalVersion->content_hash,
                ]);
            }
            $auditRecorder->record($request, 'clinical_examination.finalize', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return redirect()->route('pelayanan.pemeriksaan.index')
            ->with('success', 'Pemeriksaan selesai. Pasien diteruskan ke tahap berikutnya.');
    }

    public function addendum(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        abort_unless($request->user()->role === 'dokter', 403, 'Finalisasi, koreksi final dan penerbitan dokumen dilakukan dokter penanggung jawab.');
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
                if ($field === 'anamnesis' && blank($normalized)) {
                    throw ValidationException::withMessages([$field => 'Bagian klinis utama tidak boleh dikosongkan.']);
                }

                if ($field === 'body_map' && is_array($normalized)) {
                    $normalized = BodyMapPayload::hasContent($normalized) ? BodyMapPayload::normalize($normalized) : null;
                }
                $payload[$field] = blank($normalized) ? null : $normalized;
            }

            $hasPhysicalFinding = ClinicalAnswer::normalize($payload['pemeriksaan_fisik'] ?? null) !== null
                || filled($payload['pemeriksaan_fisik_terstruktur'] ?? null)
                || BodyMapPayload::hasContent($payload['body_map'] ?? null)
                || ClinicalAnswer::normalize($payload['pemeriksaan_ekstraoral'] ?? null) !== null
                || $lockedVisit->odontogramFindings()->exists();
            if ($lockedVisit->poliklinik?->jenis === 'gigi' && (array_key_exists('pemeriksaan_fisik', $fields) || array_key_exists('pemeriksaan_ekstraoral', $fields))) {
                if (ClinicalAnswer::normalize($payload['pemeriksaan_fisik'] ?? null) === null || ClinicalAnswer::normalize($payload['pemeriksaan_ekstraoral'] ?? null) === null) {
                    throw ValidationException::withMessages(['pemeriksaan_fisik' => 'Pemeriksaan intraoral dan ekstraoral dokter tidak boleh dikosongkan melalui addendum.']);
                }
            }
            if (! $hasPhysicalFinding) {
                throw ValidationException::withMessages(['pemeriksaan_fisik' => 'Catat setidaknya satu temuan pemeriksaan fisik.']);
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

    public function storeSoundTeeth(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $data = $request->validate([
            'teeth' => ['required', 'array', 'min:1', 'max:52'],
            'teeth.*' => ['required', 'string', 'distinct', Rule::in(OdontogramFinding::toothCodes())],
            'confirmed' => ['required', 'accepted'],
        ]);
        DB::transaction(function () use ($request, $kunjungan, $auditRecorder, $data): void {
            $visit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($visit);
            abort_unless($visit->poliklinik?->jenis === 'gigi', 422);
            $recorded = $visit->odontogramFindings()->pluck('tooth_fdi')->all();
            $teeth = array_diff($data['teeth'], $recorded);
            foreach ($teeth as $tooth) {
                $visit->odontogramFindings()->create(['tooth_fdi' => $tooth, 'surface' => 'W', 'finding_code' => 'sound', 'notes' => 'Sehat setelah diperiksa dokter.', 'recorded_by_user_id' => $request->user()->id]);
            }
            $auditRecorder->record($request, 'odontogram.sound_batch', $visit->pasien_id, $visit->id, ['teeth' => array_values($teeth)]);
        });

        return back()->with('success', 'Gigi yang telah diperiksa ditandai sehat. Temuan sebelumnya tetap dipertahankan.');
    }

    public function storeOdontogram(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $data = $this->validateDentalFinding($request);

        DB::transaction(function () use ($request, $kunjungan, $data, $auditRecorder): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutableVisit($lockedVisit);
            abort_unless($lockedVisit->poliklinik?->jenis === 'gigi', 422, 'Odontogram hanya untuk kunjungan Poli Gigi.');

            if ($data['finding_code'] === 'caries' && ! in_array('W', $data['surfaces'], true)) {
                $lockedVisit->odontogramFindings()->where('tooth_fdi', $data['tooth_fdi'])
                    ->where('surface', 'W')->where('finding_code', 'caries')->delete();
            }
            if ($data['finding_code'] !== 'sound' && ! in_array('W', $data['surfaces'], true)) {
                $lockedVisit->odontogramFindings()->where('tooth_fdi', $data['tooth_fdi'])
                    ->where('surface', 'W')->where('finding_code', 'sound')->delete();
            }
            foreach ($data['surfaces'] as $surface) {
                $data['surface'] = $surface;
                $otherFindings = $lockedVisit->odontogramFindings()->where('tooth_fdi', $data['tooth_fdi'])->where('surface', '!=', $data['surface'])->get();
                if ($otherFindings->contains(fn (OdontogramFinding $finding): bool => in_array($finding->finding_code, ['missing', 'unerupted'], true))
                    || (in_array($data['finding_code'], ['missing', 'unerupted'], true) && $otherFindings->isNotEmpty())) {
                    throw ValidationException::withMessages(['finding_code' => 'Gigi hilang / belum erupsi tidak dapat memiliki temuan permukaan lain. Koreksi temuan yang bertentangan dahulu.']);
                }
                $lockedVisit->odontogramFindings()->updateOrCreate(
                    ['tooth_fdi' => $data['tooth_fdi'], 'surface' => $data['surface']],
                    ['finding_code' => $data['finding_code'], 'notes' => $data['notes'] ?? null, 'details' => $data['details'] ?? null, 'recorded_by_user_id' => $request->user()->id]
                );
            }
            $auditRecorder->record($request, 'odontogram.draft_update', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Temuan odontogram tersimpan.');
    }

    public function destroyOdontogram(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $request->validate([
            'all' => ['prohibited'],
            'findings' => ['sometimes', 'required', 'array', 'min:1', 'max:312'],
            'findings.*' => ['required', 'array:tooth_fdi,surface'],
            'findings.*.tooth_fdi' => ['required', Rule::in(OdontogramFinding::toothCodes())],
            'findings.*.surface' => ['required', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
            'confirmed' => $request->has('findings') ? ['required', 'accepted'] : ['sometimes', 'boolean'],
            'tooth_fdi' => [Rule::requiredIf(! $request->has('findings')), 'nullable', Rule::in(OdontogramFinding::toothCodes())],
            'surface' => [Rule::requiredIf(! $request->has('findings')), 'nullable', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $targets = $data['findings'] ?? [['tooth_fdi' => $data['tooth_fdi'], 'surface' => $data['surface']]];
        $keys = collect($targets)->map(fn (array $finding): string => $finding['tooth_fdi'].'-'.$finding['surface'])->unique();
        if ($keys->count() !== count($targets)) {
            throw ValidationException::withMessages(['findings' => 'Temuan yang dipilih tidak boleh berulang.']);
        }

        DB::transaction(function () use ($request, $kunjungan, $noteRecorder, $auditRecorder, $data, $keys): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeVisit($lockedVisit);
            abort_unless($lockedVisit->poliklinik?->jenis === 'gigi', 422, 'Odontogram hanya untuk kunjungan Poli Gigi.');

            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            if (! $examination?->signed_at) {
                $this->authorizeMutableVisit($lockedVisit);
                $findings = $lockedVisit->odontogramFindings()->get()->filter(fn (OdontogramFinding $finding): bool => $keys->contains($finding->tooth_fdi.'-'.$finding->surface));
                if ($findings->count() !== $keys->count()) {
                    throw ValidationException::withMessages(['findings' => 'Sebagian temuan sudah berubah atau tidak ditemukan. Muat ulang pemeriksaan dan pilih kembali.']);
                }
                $lockedVisit->odontogramFindings()->whereIn('id', $findings->modelKeys())->delete();
                $auditRecorder->record($request, isset($data['findings']) ? 'odontogram.draft_undo_selected' : 'odontogram.draft_undo', $lockedVisit->pasien_id, $lockedVisit->id);

                return;
            }

            abort_unless($request->user()->role === 'dokter', 403);
            $reason = trim((string) ($data['reason'] ?? ''));
            if (mb_strlen($reason) < 10) {
                throw ValidationException::withMessages(['reason' => 'Alasan pembatalan temuan final minimal 10 karakter.']);
            }

            abort_unless(in_array($lockedVisit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true), 422, 'Kunjungan tidak dalam status yang dapat dikoreksi.');
            abort_unless($noteRecorder->verify($examination), 422, 'Integritas riwayat catatan tidak valid; koreksi dihentikan.');

            $latestVersion = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
            $payload = $latestVersion->payload;
            $findings = collect($payload['odontogram'] ?? []);
            $matchingFindings = $findings->filter(fn (array $finding): bool => $keys->contains($finding['tooth_fdi'].'-'.$finding['surface']));
            if ($matchingFindings->count() !== $keys->count()) {
                throw ValidationException::withMessages(['findings' => 'Sebagian temuan sudah berubah atau tidak ditemukan. Muat ulang pemeriksaan dan pilih kembali.']);
            }
            $payload['odontogram'] = $findings
                ->reject(fn (array $finding): bool => $keys->contains($finding['tooth_fdi'].'-'.$finding['surface']))
                ->values()->all();
            $noteRecorder->recordAddendum($examination, $payload, $request->user()->id, $reason);
            $auditRecorder->record($request, isset($data['findings']) ? 'odontogram.addendum_undo_selected' : 'odontogram.addendum_undo', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return back()->with('success', 'Temuan dibatalkan; riwayat versi sebelumnya tetap tersimpan.');
    }

    public function addendumOdontogram(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        abort_unless($request->user()->role === 'dokter', 403, 'Finalisasi, koreksi final dan penerbitan dokumen dilakukan dokter penanggung jawab.');
        $this->authorizeVisit($kunjungan);
        $data = $this->validateDentalFinding($request);
        if (! DentalAssessment::hasCompleteCariesDetails($data)) {
            throw ValidationException::withMessages(['details' => 'Lengkapi rincian karies dan permukaan spesifik sebelum menyimpan addendum.']);
        }
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
            foreach ($data['surfaces'] as $surface) {
                if ($data['finding_code'] !== 'sound' && $surface !== 'W') {
                    $findings = $findings->reject(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === 'W' && $finding['finding_code'] === 'sound');
                }
                $data['surface'] = $surface;
                $replacement = [
                    'tooth_fdi' => $data['tooth_fdi'],
                    'surface' => $data['surface'],
                    'finding_code' => $data['finding_code'],
                    'notes' => $data['notes'] ?? null,
                    'details' => $data['details'] ?? null,
                ];
                $otherFindings = $findings->filter(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] !== $data['surface']);
                if ($otherFindings->contains(fn (array $finding): bool => in_array($finding['finding_code'], ['missing', 'unerupted'], true))
                    || (in_array($data['finding_code'], ['missing', 'unerupted'], true) && $otherFindings->isNotEmpty())) {
                    throw ValidationException::withMessages(['finding_code' => 'Koreksi temuan permukaan yang bertentangan dengan status seluruh gigi terlebih dahulu.']);
                }
                $existing = $findings->first(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === $data['surface']);
                if ($existing === $replacement) {
                    continue;
                }

                $findings = $findings
                    ->reject(fn (array $finding): bool => $finding['tooth_fdi'] === $data['tooth_fdi'] && $finding['surface'] === $data['surface'])
                    ->push($replacement)
                    ->sortBy(fn (array $finding): string => $finding['tooth_fdi'].$finding['surface'])
                    ->values();
            }
            $payload['odontogram'] = $findings->all();
            if ($payload === $latestVersion->payload) {
                throw ValidationException::withMessages(['finding_code' => 'Temuan sama dengan versi terakhir.']);
            }
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
        if (auth()->user()->isAdmin()) {
            abort_unless($kunjungan->poliklinik?->is_active && in_array($kunjungan->poliklinik->jenis, ['umum', 'gigi'], true), 404);
        }
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
        app(AdminClinicalAssistance::class)->authorizeDraft(request(), $kunjungan);
        $this->authorizeVisit($kunjungan);

        if (auth()->user()->role === 'dokter') {
            abort_unless($kunjungan->dokter_id === $this->currentDoctorId(), 409, 'Mulai pemeriksaan terlebih dahulu sebelum mencatat pemeriksaan.');
        }

        abort_unless(
            $kunjungan->status === 'pemeriksaan' && $kunjungan->canOpenExamination()
                && $kunjungan->pemeriksaan?->status !== 'selesai' && ! $kunjungan->pemeriksaan?->signed_at,
            422,
            match (true) {
                (bool) $kunjungan->pemeriksaan?->signed_at, $kunjungan->pemeriksaan?->status === 'selesai' => 'Pemeriksaan telah difinalisasi dan tidak dapat diubah langsung.',
                $kunjungan->status === 'pemeriksaan' && ! $kunjungan->canOpenExamination() => 'Selesaikan pengkajian awal sebelum mencatat pemeriksaan dokter.',
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
                $data[$field] = BodyMapPayload::hasContent($value) ? BodyMapPayload::normalize($value) : null;
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
     * @return array{tooth_fdi: string, surface: string, finding_code: string, notes?: string|null, details?: array<string, mixed>}
     */
    private function validateDentalFinding(Request $request): array
    {
        $detailRules = [];
        foreach (['vitality', 'percussion', 'palpation'] as $test) {
            $detailRules['details.'.$test] = ['nullable', Rule::in(['positive', 'negative', 'unknown'])];
        }
        $detailRules['details.mobility'] = ['nullable', Rule::in(['M0', 'M1', 'M2', 'M3', 'unknown'])];
        foreach (DentalAssessment::detailOptions() as $key => $options) {
            $detailRules['details.'.$key] = ['nullable', Rule::in(array_keys($options))];
        }
        $data = $request->validate([
            ...$detailRules,
            'details' => ['nullable', 'array:icdas,activity,site,depth,root_severity,black_class,pulpal,periapical,assessment,vitality,percussion,palpation,mobility,eruption,position,morphology,endodontic,prosthesis,material,restoration_state,prosthesis_region,prosthesis_span'],
            'details.prosthesis_span' => ['nullable', 'string', 'max:200'],
            'details.assessment' => ['nullable', 'string', 'max:3000'],
            'tooth_fdi' => ['required', Rule::in(OdontogramFinding::toothCodes())],
            'surface' => ['required', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
            'surfaces' => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
            'surfaces.*' => ['required', 'string', 'distinct', Rule::in(array_keys(OdontogramFinding::surfaceLabels()))],
            'finding_code' => ['required', Rule::in(array_keys(OdontogramFinding::findingLabels()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['surfaces'] = $data['surfaces'] ?? [$data['surface']];
        if (! in_array($data['surface'], $data['surfaces'], true) || (in_array('W', $data['surfaces'], true) && count($data['surfaces']) > 1)) {
            throw ValidationException::withMessages(['surface' => 'Pilih permukaan spesifik atau seluruh gigi secara terpisah.']);
        }
        if (in_array($data['finding_code'], ['missing', 'unerupted'], true) && $data['surfaces'] !== ['W']) {
            throw ValidationException::withMessages(['surface' => 'Gigi hilang atau belum erupsi berlaku untuk seluruh gigi.']);
        }
        if (in_array($data['finding_code'], OdontogramFinding::wholeToothFindings(), true) && $data['surfaces'] !== ['W']) {
            throw ValidationException::withMessages(['surface' => 'Kondisi ini dicatat untuk seluruh gigi. Gunakan rincian penyerta bila terdapat lesi pada permukaan tertentu.']);
        }
        if ($data['finding_code'] === 'retained_primary' && (int) $data['tooth_fdi'][0] < 5) {
            throw ValidationException::withMessages(['tooth_fdi' => 'Persistensi dicatat pada nomor FDI gigi sulung (kuadran 5–8).']);
        }
        $data['notes'] = filled($data['notes'] ?? null) ? trim($data['notes']) : null;
        $data['details'] = array_filter($data['details'] ?? [], fn (mixed $value): bool => filled($value));
        if ($data['finding_code'] !== 'caries') {
            $data['details'] = array_intersect_key($data['details'], array_flip(['pulpal', 'periapical', 'assessment', 'vitality', 'percussion', 'palpation', 'mobility', 'eruption', 'position', 'morphology', 'endodontic', 'prosthesis', 'material', 'restoration_state', 'prosthesis_region', 'prosthesis_span']));
        }
        if (($data['details']['site'] ?? null) !== 'root') {
            unset($data['details']['root_severity']);
        }
        $defaults = [
            'partial_erupted' => ['eruption' => 'partial'], 'impacted' => ['eruption' => 'impacted'], 'retained_primary' => ['eruption' => 'retained_primary'],
            'non_vital' => ['endodontic' => 'non_vital'], 'root_canal' => ['endodontic' => 'treated'], 'crown' => ['prosthesis' => 'crown'],
            'bridge_pontic' => ['prosthesis' => 'pontic'], 'bridge_abutment' => ['prosthesis' => 'abutment'], 'implant' => ['prosthesis' => 'implant'],
            'sealant' => ['restoration_state' => 'sealant'], 'temporary_restoration' => ['restoration_state' => 'temporary'],
        ];
        foreach ($defaults[$data['finding_code']] ?? [] as $key => $value) {
            if (isset($data['details'][$key]) && $data['details'][$key] !== $value && ! ($data['finding_code'] === 'root_canal' && $data['details'][$key] === 'in_progress')) {
                throw ValidationException::withMessages(['details.'.$key => 'Rincian penyerta tidak sesuai dengan kondisi utama yang dipilih.']);
            }
            $data['details'][$key] ??= $value;
        }
        foreach (['malposition' => 'position', 'anomaly' => 'morphology', 'removable_denture' => 'prosthesis'] as $finding => $key) {
            if ($data['finding_code'] === $finding && ! isset($data['details'][$key])) {
                throw ValidationException::withMessages(['details.'.$key => 'Pilih rincian kondisi yang ditemukan sebelum menyimpan.']);
            }
        }
        if ($data['finding_code'] === 'removable_denture' && ! in_array($data['details']['prosthesis'] ?? '', ['partial_denture', 'complete_denture'], true)) {
            throw ValidationException::withMessages(['details.prosthesis' => 'Pilih gigi tiruan sebagian atau lengkap.']);
        }
        if (in_array($data['details']['prosthesis'] ?? '', ['implant', 'pontic', 'partial_denture', 'complete_denture'], true)
            && (in_array($data['finding_code'], ['caries', 'non_vital', 'root_canal'], true) || filled($data['details']['endodontic'] ?? null) || filled($data['details']['pulpal'] ?? null) || filled($data['details']['vitality'] ?? null))) {
            throw ValidationException::withMessages(['details.prosthesis' => 'Karies, pulpa, sensibilitas, dan PSA dicatat pada gigi alami, bukan implan, pontik, atau gigi tiruan lepasan. Pisahkan konteks gigi yang diperiksa.']);
        }
        if (($data['details']['endodontic'] ?? null) === 'non_vital' && isset($data['details']['pulpal']) && ! in_array($data['details']['pulpal'], ['necrosis', 'uncertain'], true)) {
            throw ValidationException::withMessages(['details.pulpal' => 'Tinjau diagnosis pulpa yang bertentangan dengan kondisi non-vital.']);
        }
        if (in_array($data['details']['endodontic'] ?? '', ['treated', 'in_progress'], true) && isset($data['details']['pulpal']) && ! in_array($data['details']['pulpal'], ['previously_treated', 'uncertain'], true)) {
            throw ValidationException::withMessages(['details.pulpal' => 'Tinjau status pulpa pada gigi yang memiliki riwayat PSA.']);
        }
        if (($data['details']['eruption'] ?? null) === 'retained_primary' && (int) $data['tooth_fdi'][0] < 5) {
            throw ValidationException::withMessages(['details.eruption' => 'Persistensi berlaku untuk gigi sulung.']);
        }
        $isUpper = in_array((int) $data['tooth_fdi'][0], [1, 2, 5, 6], true);
        if (isset($data['details']['prosthesis_region']) && $data['details']['prosthesis_region'] !== ($isUpper ? 'upper' : 'lower')) {
            throw ValidationException::withMessages(['details.prosthesis_region' => 'Regio prostesis harus sesuai rahang gigi yang dicatat.']);
        }
        if (filled($data['details']['prosthesis_span'] ?? null)) {
            $span = array_values(array_unique(preg_split('/[\s,;]+/', trim($data['details']['prosthesis_span']), -1, PREG_SPLIT_NO_EMPTY)));
            if (array_diff($span, OdontogramFinding::toothCodes()) !== [] || ! in_array($data['tooth_fdi'], $span, true)
                || collect($span)->contains(fn (string $tooth): bool => in_array((int) $tooth[0], [1, 2, 5, 6], true) !== $isUpper)) {
                throw ValidationException::withMessages(['details.prosthesis_span' => 'Gunakan nomor FDI dipisahkan koma pada rahang yang sama dan sertakan gigi aktif. Contoh: 14, 15, 16.']);
            }
            $data['details']['prosthesis_span'] = implode(', ', $span);
        }
        if (isset($data['details']['assessment'])) {
            $data['details']['assessment'] = trim($data['details']['assessment']);
        }

        if (in_array($data['finding_code'], ['missing', 'unerupted'], true) && $data['surface'] !== 'W') {
            throw ValidationException::withMessages(['surface' => 'Temuan gigi hilang atau belum erupsi berlaku untuk seluruh gigi.']);
        }

        return $data;
    }
}
