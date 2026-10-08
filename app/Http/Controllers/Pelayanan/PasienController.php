<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\Exports\PasienExport;
use App\Exports\PasienTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PasienImport;
use App\Models\Asuransi;
use App\Models\Pasien;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PasienController extends Controller
{
    public function index(Request $request, ClinicalAuditRecorder $auditRecorder): Response
    {
        $pasien = Pasien::with('asuransi')
            ->when($request->user()->role === 'dokter', fn ($query) => $query->whereHas('kunjungan', fn ($visits) => $visits->where('dokter_id', $request->user()->nakes?->id ?? 0)))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($query) => $query
                ->where('nama', 'like', "%$s%")
                ->orWhere('no_rm', 'like', "%$s%")
                ->orWhere('nik', 'like', "%$s%")
                ->orWhere('telepon', 'like', "%$s%")))
            ->when($request->asuransi_id, fn ($q, $a) => $q->where('asuransi_id', $a))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

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
                    'nik' => $patient->nik,
                    'gender' => $patient->jenis_kelamin,
                    'age' => $patient->umur,
                    'phone' => $patient->telepon,
                    'address' => $patient->alamat,
                    'insurance' => $patient->asuransi?->nama ?? 'Umum',
                    'insuranceType' => $patient->asuransi?->jenis,
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
                'insuranceId' => $request->string('asuransi_id')->toString(),
            ],
            'insuranceProviders' => $asuransiList->map(fn (Asuransi $insurance) => [
                'id' => $insurance->id,
                'name' => $insurance->nama,
                'type' => $insurance->jenis,
            ]),
        ]);
    }

    public function create(): Response
    {
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('pelayanan/pasien/form', [
            'insuranceProviders' => $asuransiList->map(fn (Asuransi $insurance) => [
                'id' => $insurance->id,
                'name' => $insurance->nama,
                'type' => $insurance->jenis,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'nik' => ['nullable', 'string', 'max:20'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,O,AB'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'pekerjaan' => ['nullable', 'string', 'max:100'],
            'agama' => ['nullable', 'string', 'max:50'],
            'status_perkawinan' => ['nullable', 'string'],
            'nama_wali' => ['nullable', 'string', 'max:200'],
            'telepon_wali' => ['nullable', 'string', 'max:20'],
            'asuransi_id' => ['nullable', 'exists:asuransi,id'],
            'no_asuransi' => ['nullable', 'string', 'max:50'],
        ]);

        // Generate No. RM
        $lastPasien = Pasien::withTrashed()->latest('id')->first();
        $nextId = $lastPasien ? ($lastPasien->id + 1) : 1;
        $data['no_rm'] = 'RM-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);

        $pasien = Pasien::create($data);

        return redirect()->route('pelayanan.pasien.show', $pasien)
            ->with('success', "Pasien berhasil didaftarkan dengan No. RM: {$pasien->no_rm}");
    }

    public function show(Request $request, Pasien $pasien, ClinicalAuditRecorder $auditRecorder): Response
    {
        $this->authorizeDoctorPatient($pasien);
        $auditRecorder->record($request, 'patient_profile.view', $pasien->id);
        $pasien->load(['asuransi', 'kunjungan' => fn ($q) => $q
            ->when(auth()->user()->role === 'dokter', fn ($visits) => $visits->where('dokter_id', auth()->user()->nakes?->id ?? 0))
            ->with(['poliklinik', 'dokter', 'tagihan'])->latest()->limit(20)]);

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
                'birthDate' => $pasien->tanggal_lahir?->isoFormat('D MMMM Y'),
                'gender' => $pasien->jenis_kelamin,
                'age' => $pasien->umur,
                'bloodType' => $pasien->golongan_darah,
                'phone' => $pasien->telepon,
                'address' => $pasien->alamat,
                'insurance' => $pasien->asuransi?->nama ?? 'Umum (Bayar Sendiri)',
                'insuranceType' => $pasien->asuransi?->jenis,
                'insuranceNumber' => $pasien->no_asuransi,
                'allergies' => in_array(auth()->user()->role, ['dokter', 'perawat'], true) ? $pasien->riwayat_alergi : null,
                'visits' => $pasien->kunjungan->map(fn ($visit) => [
                    'id' => $visit->id,
                    'url' => auth()->user()->role === 'dokter'
                        ? route('pelayanan.pemeriksaan.show', $visit)
                        : route('pelayanan.kunjungan.show', $visit),
                    'number' => $visit->no_kunjungan,
                    'date' => $visit->tanggal?->isoFormat('D MMM Y'),
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'doctor' => $visit->dokter?->nama ?? '—',
                    'status' => $visit->status,
                    'billId' => $visit->tagihan?->id,
                ]),
            ],
        ]);
    }

    public function edit(Pasien $pasien): Response
    {
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('pelayanan/pasien/form', [
            'patient' => [
                'id' => $pasien->id,
                'name' => $pasien->nama,
                'nik' => $pasien->nik,
                'birthDate' => $pasien->tanggal_lahir?->toDateString(),
                'gender' => $pasien->jenis_kelamin,
                'bloodType' => $pasien->golongan_darah,
                'religion' => $pasien->agama,
                'phone' => $pasien->telepon,
                'occupation' => $pasien->pekerjaan,
                'address' => $pasien->alamat,
                'insuranceId' => $pasien->asuransi_id,
                'insuranceNumber' => $pasien->no_asuransi,
            ],
            'insuranceProviders' => $asuransiList->map(fn (Asuransi $insurance) => [
                'id' => $insurance->id,
                'name' => $insurance->nama,
                'type' => $insurance->jenis,
            ]),
        ]);
    }

    public function update(Request $request, Pasien $pasien): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'nik' => ['nullable', 'string', 'max:20'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,O,AB'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'pekerjaan' => ['nullable', 'string', 'max:100'],
            'agama' => ['nullable', 'string', 'max:50'],
            'status_perkawinan' => ['nullable', 'string'],
            'nama_wali' => ['nullable', 'string', 'max:200'],
            'telepon_wali' => ['nullable', 'string', 'max:20'],
            'asuransi_id' => ['nullable', 'exists:asuransi,id'],
            'no_asuransi' => ['nullable', 'string', 'max:50'],
        ]);

        $pasien->update($data);

        return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(Pasien $pasien): RedirectResponse
    {
        $pasien->delete();

        return redirect()->route('pelayanan.pasien.index')->with('success', 'Data pasien berhasil dihapus.');
    }

    public function rekamMedis(Request $request, Pasien $pasien, ClinicalAuditRecorder $auditRecorder, ClinicalNoteRecorder $noteRecorder): Response
    {
        $this->authorizeDoctorPatient($pasien);
        $auditRecorder->record($request, 'medical_record.view', $pasien->id);
        $pasien->load(['kunjungan' => fn ($query) => $query
            ->when(auth()->user()->role === 'dokter', fn ($visits) => $visits->where('dokter_id', auth()->user()->nakes?->id ?? 0))
            ->with([
                'poliklinik:id,nama,jenis',
                'dokter:id,nama',
                'screening',
                'pemeriksaan.diagnosa',
                'pemeriksaan.signedBy',
                'pemeriksaan.clinicalNoteVersions.actor',
                'odontogramFindings',
                'resep.resepObat',
                'tindakanKunjungan.tindakan:id,nama',
            ])->latest()]);

        return Inertia::render('pelayanan/pasien/rekam-medis', [
            'patient' => [
                'id' => $pasien->id,
                'name' => $pasien->nama,
                'medicalRecordNumber' => $pasien->no_rm,
                'nik' => $pasien->nik,
                'gender' => $pasien->jenis_kelamin,
                'age' => $pasien->umur,
                'bloodType' => $pasien->golongan_darah,
                'allergies' => $pasien->riwayat_alergi,
            ],
            'backUrl' => route('pelayanan.pasien.show', $pasien),
            'visits' => $pasien->kunjungan->map(function ($visit) use ($noteRecorder): array {
                $versions = $visit->pemeriksaan?->clinicalNoteVersions->sortBy('version') ?? collect();
                $integrityValid = $visit->pemeriksaan?->signed_at ? $noteRecorder->verify($visit->pemeriksaan) : null;
                $effectiveNote = $integrityValid ? $versions->last()->payload : null;
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
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'clinicType' => $visit->poliklinik?->jenis,
                    'doctor' => $visit->dokter?->nama ?? '—',
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
                        'respiration' => $visit->screening->respirasi,
                        'complaint' => $visit->screening->keluhan,
                    ] : null,
                    'examination' => $visit->pemeriksaan ? [
                        'anamnesis' => is_array($effectiveNote) ? ($effectiveNote['anamnesis'] ?? null) : $visit->pemeriksaan->anamnesis,
                        'physicalExamination' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik'] ?? null) : $visit->pemeriksaan->pemeriksaan_fisik,
                        'education' => is_array($effectiveNote) ? ($effectiveNote['edukasi'] ?? null) : $visit->pemeriksaan->edukasi,
                        'notes' => is_array($effectiveNote) ? ($effectiveNote['catatan'] ?? null) : $visit->pemeriksaan->catatan,
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
                            'physicalExamination' => $version->payload['pemeriksaan_fisik'] ?? null,
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
                        'name' => is_array($item) ? $item['name'] : ($item->tindakan?->nama ?? 'Tindakan dihapus'),
                        'quantity' => is_array($item) ? $item['quantity'] : $item->jumlah,
                        'toothFdi' => is_array($item) ? ($item['tooth_fdi'] ?? null) : $item->tooth_fdi,
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

        $doctorId = auth()->user()->nakes?->id;
        abort_unless(
            $doctorId && $pasien->kunjungan()->where('dokter_id', $doctorId)->exists(),
            403,
            'Pasien bukan tanggung jawab dokter ini.'
        );
    }

    public function gabung(Request $request): RedirectResponse
    {
        $request->validate([
            'pasien_utama_id' => ['required', 'exists:pasien,id'],
            'pasien_hapus_id' => ['required', 'exists:pasien,id', 'different:pasien_utama_id'],
        ]);

        $pasienUtama = Pasien::findOrFail($request->pasien_utama_id);
        $pasienHapus = Pasien::findOrFail($request->pasien_hapus_id);

        // Move all kunjungan from pasienHapus to pasienUtama
        $pasienHapus->kunjungan()->update(['pasien_id' => $pasienUtama->id]);
        $pasienHapus->delete();

        return redirect()->route('pelayanan.pasien.show', $pasienUtama)
            ->with('success', 'Rekam medis berhasil digabungkan.');
    }

    public function export(Request $request, ClinicalAuditRecorder $auditRecorder): BinaryFileResponse
    {
        $auditRecorder->record($request, 'patient_directory.export');

        return Excel::download(new PasienExport, 'data-pasien-'.date('Y-m-d').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        try {
            Excel::import(new PasienImport, $request->file('file'));

            return back()->with('success', 'Data pasien berhasil diimpor.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengimpor data: '.$e->getMessage());
        }
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new PasienTemplateExport, 'template-import-pasien.xlsx');
    }
}
