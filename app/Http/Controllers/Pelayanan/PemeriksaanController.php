<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\CorrespondenceService;
use App\Http\Controllers\Controller;
use App\Models\ClinicalTerminology;
use App\Models\CorrespondenceTemplate;
use App\Models\Diagnosa;
use App\Models\Farmasi;
use App\Models\Icd10;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\OdontogramFinding;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\ResepObat;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\PersediaanRecorder;
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
    public function index(Request $request): Response
    {
        $filters = $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $doctorId = auth()->user()->role === 'dokter' ? $this->currentDoctorId() : null;
        $date = $filters['tanggal'] ?? today()->toDateString();
        $scheduledClinicIds = $doctorId ? $this->scheduledClinicIds($doctorId, $date) : [];
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan.diagnosa'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->whereDate('tanggal', $date)
            ->whereIn('status', ['pemeriksaan', 'farmasi', 'kasir', 'selesai'])
            ->when($doctorId, fn ($q) => $q->where(fn ($assigned) => $assigned
                ->where('dokter_id', $doctorId)
                ->orWhere(fn ($unassigned) => $unassigned->whereNull('dokter_id')
                    ->where('status', 'pemeriksaan')
                    ->whereIn('poliklinik_id', $scheduledClinicIds))))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('pelayanan/pemeriksaan/index', [
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit) => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                    'gender' => $visit->pasien?->jenis_kelamin,
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'doctor' => $visit->dokter?->nama,
                    'canClaim' => $doctorId !== null && $visit->dokter_id === null,
                    'status' => $visit->status,
                    'diagnosisCount' => $visit->pemeriksaan?->diagnosa->count() ?? 0,
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
        $tindakanList = Tindakan::where('is_active', true)->where('poliklinik_id', $kunjungan->poliklinik_id)->orderBy('nama')->get();
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
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'paymentType' => 'internal',
                'doctorId' => $kunjungan->dokter_id,
                'canClaim' => auth()->user()->role === 'dokter' && $kunjungan->dokter_id === null,
                'clinicId' => $kunjungan->poliklinik_id,
                'clinicType' => $kunjungan->poliklinik?->jenis,
                'clinic' => $kunjungan->poliklinik?->nama ?? '—',
                'patient' => [
                    'id' => $kunjungan->pasien->id,
                    'name' => $kunjungan->pasien->nama,
                    'medicalRecordNumber' => $kunjungan->pasien->no_rm,
                    'gender' => $kunjungan->pasien->jenis_kelamin,
                    'age' => $kunjungan->pasien->umur,
                    'bloodType' => $kunjungan->pasien->golongan_darah,
                    'allergies' => $kunjungan->pasien->riwayat_alergi,
                ],
                'screening' => $kunjungan->screening ? [
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
                    'medicalHistory' => $kunjungan->screening->riwayat_penyakit,
                    'familyHistory' => $kunjungan->screening->riwayat_penyakit_keluarga,
                    'allergyHistory' => $kunjungan->screening->riwayat_alergi,
                    'painScale' => $kunjungan->screening->skala_nyeri,
                    'fallRisk' => $kunjungan->screening->risiko_jatuh,
                    'triage' => $kunjungan->screening->kesimpulan_triase,
                    'priority' => $kunjungan->screening->prioritas_layanan,
                    'dentalPainLocation' => $kunjungan->screening->lokasi_nyeri_gigi,
                    'dentalPainTriggers' => $kunjungan->screening->pemicu_nyeri_gigi ?? [],
                    'dentalPainDuration' => $kunjungan->screening->durasi_keluhan_gigi,
                    'dentalMedicalRisks' => $kunjungan->screening->risiko_medis_gigi ?? [],
                    'dentalInfectionHistory' => $kunjungan->screening->riwayat_infeksi_gigi ?? [],
                    'dentalNotes' => $kunjungan->screening->catatan_medis_gigi,
                ] : null,
                'examination' => $kunjungan->pemeriksaan ? [
                    'doctorId' => $kunjungan->pemeriksaan->dokter_id,
                    'startedAt' => ($kunjungan->pemeriksaan->started_at ?? $kunjungan->pemeriksaan->created_at)?->locale('id')->isoFormat('D MMMM Y HH:mm'),
                    'anamnesis' => is_array($effectiveNote) ? ($effectiveNote['anamnesis'] ?? null) : $kunjungan->pemeriksaan->anamnesis,
                    'currentHistory' => is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_sekarang'] ?? null) : $kunjungan->pemeriksaan->riwayat_penyakit_sekarang,
                    'pastHistory' => is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_dahulu'] ?? null) : $kunjungan->pemeriksaan->riwayat_penyakit_dahulu,
                    'familyHistory' => is_array($effectiveNote) ? ($effectiveNote['riwayat_penyakit_keluarga'] ?? null) : $kunjungan->pemeriksaan->riwayat_penyakit_keluarga,
                    'allergyHistory' => is_array($effectiveNote) ? ($effectiveNote['riwayat_alergi'] ?? null) : $kunjungan->pemeriksaan->riwayat_alergi,
                    'physicalExam' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik'] ?? null) : $kunjungan->pemeriksaan->pemeriksaan_fisik,
                    'physicalSystems' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik_terstruktur'] ?? []) : ($kunjungan->pemeriksaan->pemeriksaan_fisik_terstruktur ?? []),
                    'extraoralExam' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_ekstraoral'] ?? null) : $kunjungan->pemeriksaan->pemeriksaan_ekstraoral,
                    'oralHygieneIndex' => is_numeric($oralHygieneIndex) ? (float) $oralHygieneIndex : null,
                    'differentialDiagnosis' => is_array($effectiveNote) ? ($effectiveNote['diagnosis_banding'] ?? null) : $kunjungan->pemeriksaan->diagnosis_banding,
                    'followUpDate' => is_array($effectiveNote) ? ($effectiveNote['kontrol_berikutnya'] ?? null) : $kunjungan->pemeriksaan->kontrol_berikutnya?->toDateString(),
                    'notes' => is_array($effectiveNote) ? ($effectiveNote['catatan'] ?? null) : $kunjungan->pemeriksaan->catatan,
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
                        'notes' => $version->payload['catatan'] ?? null,
                        'education' => $version->payload['edukasi'] ?? null,
                        'odontogram' => collect($version->payload['odontogram'] ?? [])->map(fn (array $finding): array => [
                            'toothFdi' => $finding['tooth_fdi'],
                            'surface' => $finding['surface'],
                            'findingCode' => $finding['finding_code'],
                            'notes' => $finding['notes'] ?? null,
                        ])->values()->all(),
                    ])->values(),
                ] : null,
                'diagnoses' => $kunjungan->pemeriksaan?->diagnosa->map(fn (Diagnosa $diagnosis) => [
                    'id' => $diagnosis->id,
                    'code' => $diagnosis->kode_icd10,
                    'codeSystem' => $diagnosis->code_system,
                    'codeRelease' => $diagnosis->code_release,
                    'name' => $diagnosis->nama_diagnosa,
                    'type' => $diagnosis->jenis,
                ])->values() ?? collect(),
                'treatments' => $kunjungan->tindakanKunjungan->map(fn (TindakanKunjungan $item) => [
                    'id' => $item->id,
                    'name' => $item->nama_tindakan_manual ?? $item->tindakan?->nama ?? 'Tindakan dihapus',
                    'quantity' => $item->jumlah,
                    'tariff' => (float) $item->tarif,
                    'toothFdi' => $item->tooth_fdi,
                    'note' => $item->catatan,
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
            'treatments' => $tindakanList->map(fn (Tindakan $treatment) => [
                'id' => $treatment->id,
                'name' => $treatment->nama,
                'tariff' => (float) $treatment->tarif,
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

    public function claim(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $doctorId = $this->currentDoctorId();

        DB::transaction(function () use ($request, $kunjungan, $auditRecorder, $doctorId): void {
            $lockedVisit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
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
            $data = $request->validate([
                'kode_icd10' => ['required', 'string'],
                'code_system' => ['sometimes', 'nullable', 'string', 'max:100'],
                'code_release' => ['sometimes', 'nullable', 'string', 'max:100'],
                'nama_diagnosa' => ['required', 'string'],
                'jenis' => ['required', 'in:utama,tambahan'],
            ]);
            $codeSystem = $data['code_system'] ?? 'icd10_who';
            $catalog = ClinicalTerminology::query()->where('code_system', $codeSystem)->where('is_active', true);
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

            $pemeriksaan = $kunjungan->pemeriksaan()->firstOrCreate(['kunjungan_id' => $kunjungan->id], ['status' => 'draft']);

            $pemeriksaan->diagnosa()->create([
                'kode_icd10' => $data['kode_icd10'],
                'code_system' => $codeSystem,
                'code_release' => $data['code_release'] ?? null,
                'nama_diagnosa' => $data['nama_diagnosa'],
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

                $obat = ! empty($data['obat_id']) ? Obat::whereKey($data['obat_id'])->lockForUpdate()->firstOrFail() : null;
                $isResepLuar = (bool) ($data['is_resep_luar'] ?? false);
                if (! $isResepLuar && (! $obat || ! $obat->is_active || $obat->jenis !== 'obat')) {
                    throw ValidationException::withMessages(['obat_id' => 'Pilih obat aktif dari persediaan klinik.']);
                }

                $item = $resep->resepObat()->create([
                    'obat_id' => $data['obat_id'] ?? null,
                    'nama_obat' => $data['nama_obat'] ?? $obat?->nama,
                    'jumlah' => $data['jumlah'],
                    'satuan' => $data['satuan'] ?? $obat?->satuan_kecil,
                    'aturan_pakai' => $data['aturan_pakai'] ?? null,
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
                'tindakan_id' => ['nullable', 'required_without:nama_tindakan_manual', 'exists:tindakan,id'],
                'nama_tindakan_manual' => ['nullable', 'required_without:tindakan_id', 'string', 'min:3', 'max:255'],
                'jumlah' => ['required', 'integer', 'min:1'],
                'catatan' => ['nullable', 'string', 'max:2000'],
                'tooth_fdi' => ['nullable', Rule::in(OdontogramFinding::toothCodes())],
            ]);

            if (filled($data['tindakan_id'] ?? null) === filled($data['nama_tindakan_manual'] ?? null)) {
                throw ValidationException::withMessages(['tindakan_id' => 'Pilih tindakan dari daftar atau isi nama tindakan manual, bukan keduanya.']);
            }

            abort_if($request->filled('tooth_fdi') && $kunjungan->poliklinik?->jenis !== 'gigi', 422, 'Lokasi gigi hanya untuk kunjungan Poli Gigi.');

            $tindakanId = null;
            $manualName = null;
            if (filled($data['tindakan_id'] ?? null)) {
                $tindakan = Tindakan::query()->where('is_active', true)->findOrFail($data['tindakan_id']);
                abort_unless($tindakan->poliklinik_id === $kunjungan->poliklinik_id, 422, 'Tindakan tidak tersedia di poliklinik kunjungan ini.');
                $tindakanId = $tindakan->id;
            } else {
                $manualName = trim($data['nama_tindakan_manual']);
                if ($manualName === '') {
                    throw ValidationException::withMessages(['nama_tindakan_manual' => 'Nama tindakan manual tidak boleh kosong.']);
                }
            }

            $kunjungan->tindakanKunjungan()->create([
                'tindakan_id' => $tindakanId,
                'nama_tindakan_manual' => $manualName,
                'dokter_id' => $kunjungan->dokter_id,
                'jumlah' => $data['jumlah'],
                'tarif' => 0,
                'tarif_dokter' => 0,
                'tooth_fdi' => $request->input('tooth_fdi'),
                'catatan' => $data['catatan'] ?? null,
            ]);

            return back()->with('success', 'Tindakan berhasil ditambahkan.');
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
                'nomor_surat' => ['nullable', 'string', 'max:120'],
                'tanggal' => ['required', 'date'],
                'surat_template_id' => ['nullable', 'exists:correspondence_templates,id'],
            ]);

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

            $kunjungan->loadMissing(['pasien', 'dokter', 'poliklinik']);
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
                filled($examination->anamnesis) && filled($examination->pemeriksaan_fisik),
                422,
                'Anamnesis dan pemeriksaan fisik wajib diisi sebelum finalisasi.'
            );
            abort_unless($examination->diagnosa()->where('jenis', 'utama')->exists(), 422, 'Diagnosis utama wajib dicatat sebelum finalisasi.');
            if ($lockedVisit->poliklinik?->jenis === 'gigi') {
                abort_unless($lockedVisit->odontogramFindings()->exists(), 422, 'Catat temuan odontogram sebelum finalisasi kunjungan gigi.');
            }

            $procedures = $lockedVisit->tindakanKunjungan()->with('tindakan.bhp.obat')->orderBy('id')->lockForUpdate()->get();
            $consumptions = [];
            foreach ($procedures as $procedure) {
                if (! $procedure->tindakan || $procedure->bhp_consumed_at) {
                    continue;
                }
                foreach ($procedure->tindakan->bhp as $supply) {
                    abort_unless($supply->obat?->jenis === 'bhp', 422, 'Komposisi BHP tindakan harus memakai master BHP.');
                    $consumptions[] = ['medicine' => $supply->obat_id, 'quantity' => (float) $supply->jumlah * (float) $procedure->jumlah, 'procedure' => $procedure->id];
                }
            }
            usort($consumptions, fn (array $a, array $b): int => $a['medicine'] <=> $b['medicine']);
            foreach ($consumptions as $consumption) {
                app(PersediaanRecorder::class)->issue($consumption['medicine'], $consumption['quantity'], $request->user(), $lockedVisit, 'TindakanBhp', $consumption['procedure']);
            }
            foreach ($procedures as $procedure) {
                if ($procedure->tindakan) {
                    $procedure->update(['bhp_consumed_at' => now()]);
                }
            }

            $noteRecorder->recordFinal($examination, $noteRecorder->snapshot($lockedVisit, $examination), $request->user()->id);
            $examination->update([
                'status' => 'selesai',
                'signed_by_user_id' => $request->user()->id,
                'signed_at' => now(),
            ]);

            $prescription = $lockedVisit->resep;
            if ($prescription && $prescription->resepObat()->exists()) {
                Farmasi::firstOrCreate(
                    ['kunjungan_id' => $lockedVisit->id],
                    ['resep_id' => $prescription->id, 'status' => 'menunggu']
                );
                $lockedVisit->update(['status' => 'farmasi']);
            } else {
                $lockedVisit->update(['status' => 'selesai']);
            }

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
            'pemeriksaan_ekstraoral' => ['sometimes', Rule::excludeIf(! $isDentalClinic), 'nullable', 'string', 'max:5000'],
            'oral_hygiene_index' => ['sometimes', Rule::excludeIf(! $isDentalClinic), 'nullable', 'numeric', 'between:0,6'],
            'diagnosis_banding' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'catatan' => ['sometimes', 'nullable', 'string'],
            'edukasi' => ['sometimes', 'nullable', 'string'],
            'kontrol_berikutnya' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $fields = array_intersect_key($data, array_flip([
            'anamnesis', 'riwayat_penyakit_sekarang', 'riwayat_penyakit_dahulu',
            'riwayat_penyakit_keluarga', 'riwayat_alergi', 'pemeriksaan_fisik',
            'pemeriksaan_fisik_terstruktur', 'pemeriksaan_ekstraoral', 'oral_hygiene_index',
            'diagnosis_banding', 'catatan', 'edukasi', 'kontrol_berikutnya',
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
            abort_unless(in_array($lockedVisit->status, ['farmasi', 'kasir', 'selesai'], true), 422, 'Kunjungan tidak dalam status yang dapat dikoreksi.');
            $examination = $lockedVisit->pemeriksaan()->lockForUpdate()->first();
            abort_unless($examination?->signed_at, 422, 'Catatan lama belum difinalisasi dengan versi yang dapat dikoreksi.');
            abort_unless($noteRecorder->verify($examination), 422, 'Integritas riwayat catatan tidak valid; koreksi dihentikan.');
            $latestVersion = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
            $payload = $latestVersion->payload;

            foreach ($fields as $field => $value) {
                $normalized = is_string($value) ? trim($value) : $value;
                if (in_array($field, ['anamnesis', 'pemeriksaan_fisik'], true) && blank($normalized)) {
                    throw ValidationException::withMessages([$field => 'Bagian klinis utama tidak boleh dikosongkan.']);
                }

                $payload[$field] = $normalized === '' ? null : $normalized;
            }

            if ($payload === $latestVersion->payload) {
                throw ValidationException::withMessages(['reason' => 'Tidak ada perubahan catatan untuk dibuatkan addendum.']);
            }

            $noteRecorder->recordAddendum($examination, $payload, $request->user()->id, trim($data['reason']));
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
            abort_unless(in_array($lockedVisit->status, ['farmasi', 'kasir', 'selesai'], true), 422, 'Kunjungan tidak dalam status yang dapat dikoreksi.');
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
        $doctorId = auth()->user()->nakes?->id;
        abort_unless($doctorId, 403, 'Akun dokter belum terhubung dengan data tenaga kesehatan.');

        return $doctorId;
    }

    /**
     * @return array<int, int>
     */
    private function scheduledClinicIds(int $doctorId, string $date): array
    {
        $visitDate = Carbon::parse($date);

        return JadwalDokter::query()
            ->where('dokter_id', $doctorId)
            ->where('hari', strtolower($visitDate->locale('id')->dayName))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $visitDate->toDateString()))
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $visitDate->toDateString()))
            ->whereHas('dokter', fn ($query) => $query->where('is_active', true)->where('jabatan', 'dokter'))
            ->pluck('poliklinik_id')->unique()->values()->all();
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
        $this->authorizeVisit($kunjungan);

        if (auth()->user()->role === 'dokter') {
            abort_unless($kunjungan->dokter_id === $this->currentDoctorId(), 409, 'Ambil kunjungan terlebih dahulu sebelum mencatat pemeriksaan.');
        }

        abort_unless(
            $kunjungan->status === 'pemeriksaan' && $kunjungan->pemeriksaan?->status !== 'selesai',
            422,
            'Pemeriksaan sudah selesai dan tidak dapat diubah.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExamination(Request $request, Kunjungan $kunjungan): array
    {
        $isGeneralClinic = $kunjungan->poliklinik?->jenis === 'umum';
        $isDentalClinic = $kunjungan->poliklinik?->jenis === 'gigi';

        return $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string', 'max:10000'],
            'riwayat_penyakit_sekarang' => ['nullable', 'string', 'max:10000'],
            'riwayat_penyakit_dahulu' => ['nullable', 'string', 'max:10000'],
            'riwayat_penyakit_keluarga' => ['nullable', 'string', 'max:10000'],
            'riwayat_alergi' => ['nullable', 'string', 'max:5000'],
            'pemeriksaan_fisik' => ['nullable', 'string', 'max:10000'],
            'pemeriksaan_fisik_terstruktur' => [Rule::excludeIf(! $isGeneralClinic), 'nullable', 'array'],
            'pemeriksaan_fisik_terstruktur.*' => ['nullable', 'string', 'max:3000'],
            'pemeriksaan_ekstraoral' => [Rule::excludeIf(! $isDentalClinic), 'nullable', 'string', 'max:5000'],
            'oral_hygiene_index' => [Rule::excludeIf(! $isDentalClinic), 'nullable', 'numeric', 'between:0,6'],
            'diagnosis_banding' => ['nullable', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'edukasi' => ['nullable', 'string', 'max:5000'],
            'kontrol_berikutnya' => ['nullable', 'date_format:Y-m-d'],
        ]);
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

        if (in_array($data['finding_code'], ['missing', 'unerupted'], true) && $data['surface'] !== 'W') {
            throw ValidationException::withMessages(['surface' => 'Temuan gigi hilang atau belum erupsi berlaku untuk seluruh gigi.']);
        }

        return $data;
    }
}
