<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\Http\Controllers\Controller;
use App\Models\Diagnosa;
use App\Models\Farmasi;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\OdontogramFinding;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\ResepObat;
use App\Models\StokMutasi;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
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
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan.diagnosa'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->whereIn('status', ['pemeriksaan', 'farmasi', 'kasir', 'selesai'])
            ->when(auth()->user()->role === 'dokter', fn ($q) => $q->where('dokter_id', $this->currentDoctorId()))
            ->orderBy('created_at')
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
            'date' => $request->input('tanggal', today()->toDateString()),
        ]);
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder, ClinicalNoteRecorder $noteRecorder): Response
    {
        $this->authorizeVisit($kunjungan);
        $auditRecorder->record($request, 'clinical_examination.view', $kunjungan->pasien_id, $kunjungan->id);
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'screening',
            'pemeriksaan.diagnosa', 'pemeriksaan.signedBy', 'pemeriksaan.clinicalNoteVersions.actor', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'suratMedis',
            'labHasil.laboratorium', 'rujukanInternal.dariPoli', 'rujukanInternal.kePoli',
            'informedConsent', 'odontogramFindings',
        ]);

        $dokterList = auth()->user()->role === 'dokter'
            ? Nakes::whereKey($this->currentDoctorId())->get()
            : Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $tindakanList = Tindakan::where('is_active', true)->where('poliklinik_id', $kunjungan->poliklinik_id)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->where('jenis', 'obat')->orderBy('nama')->get();
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $latestNote = $kunjungan->pemeriksaan?->clinicalNoteVersions->sortBy('version')->last();
        $noteIntegrityValid = $kunjungan->pemeriksaan?->signed_at ? $noteRecorder->verify($kunjungan->pemeriksaan) : null;
        $effectiveNote = $noteIntegrityValid ? $latestNote->payload : null;
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
                'paymentType' => $kunjungan->jenis_bayar,
                'doctorId' => $kunjungan->dokter_id,
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
                    'complaint' => $kunjungan->screening->keluhan,
                ] : null,
                'examination' => $kunjungan->pemeriksaan ? [
                    'doctorId' => $kunjungan->pemeriksaan->dokter_id,
                    'anamnesis' => is_array($effectiveNote) ? ($effectiveNote['anamnesis'] ?? null) : $kunjungan->pemeriksaan->anamnesis,
                    'physicalExam' => is_array($effectiveNote) ? ($effectiveNote['pemeriksaan_fisik'] ?? null) : $kunjungan->pemeriksaan->pemeriksaan_fisik,
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
                    'name' => $diagnosis->nama_diagnosa,
                    'type' => $diagnosis->jenis,
                ])->values() ?? collect(),
                'treatments' => $kunjungan->tindakanKunjungan->map(fn (TindakanKunjungan $item) => [
                    'id' => $item->id,
                    'name' => $item->tindakan?->nama ?? 'Tindakan dihapus',
                    'quantity' => $item->jumlah,
                    'tariff' => (float) $item->tarif,
                    'toothFdi' => $item->tooth_fdi,
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
                ],
                'letters' => $kunjungan->suratMedis->map(fn ($letter) => [
                    'id' => $letter->id,
                    'type' => $letter->jenis,
                    'number' => $letter->nomor_surat,
                    'content' => $letter->konten,
                    'date' => $letter->tanggal?->format('d/m/Y'),
                ])->values(),
                'referrals' => $kunjungan->rujukanInternal->map(fn ($referral) => [
                    'id' => $referral->id,
                    'fromClinic' => $referral->dariPoli?->nama,
                    'toClinic' => $referral->kePoli?->nama,
                    'notes' => $referral->catatan,
                    'status' => $referral->status,
                ])->values(),
            ],
            'doctors' => $dokterList->map(fn (Nakes $doctor) => ['id' => $doctor->id, 'name' => $doctor->nama]),
            'treatments' => $tindakanList->map(fn (Tindakan $treatment) => [
                'id' => $treatment->id,
                'name' => $treatment->nama,
                'tariff' => (float) $treatment->tarif,
            ]),
            'medicines' => $obatList->map(fn (Obat $medicine) => [
                'id' => $medicine->id,
                'name' => $medicine->nama,
                'stock' => (float) $medicine->stok,
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
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $data = $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'edukasi' => ['nullable', 'string'],
            'kontrol_berikutnya' => ['nullable', 'date'],
        ]);

        $data['kunjungan_id'] = $kunjungan->id;
        $data['dokter_id'] = auth()->user()->role === 'dokter'
            ? $this->currentDoctorId()
            : ($data['dokter_id'] ?? $kunjungan->dokter_id);
        $data['status'] = 'draft';

        Pemeriksaan::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        return back()->with('success', 'Data pemeriksaan berhasil disimpan.');
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $data = $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'edukasi' => ['nullable', 'string'],
            'kontrol_berikutnya' => ['nullable', 'date'],
        ]);

        $data['dokter_id'] = auth()->user()->role === 'dokter'
            ? $this->currentDoctorId()
            : ($data['dokter_id'] ?? $kunjungan->dokter_id);
        $kunjungan->pemeriksaan()->updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        return back()->with('success', 'Data pemeriksaan berhasil diperbarui.');
    }

    public function storeDiagnosa(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $request->validate([
            'kode_icd10' => ['required', 'string'],
            'nama_diagnosa' => ['required', 'string'],
            'jenis' => ['required', 'in:utama,tambahan'],
        ]);

        $pemeriksaan = $kunjungan->pemeriksaan()->firstOrCreate(['kunjungan_id' => $kunjungan->id], ['status' => 'draft']);

        $pemeriksaan->diagnosa()->create([
            'kode_icd10' => $request->kode_icd10,
            'nama_diagnosa' => $request->nama_diagnosa,
            'jenis' => $request->jenis,
        ]);

        return back()->with('success', 'Diagnosa berhasil ditambahkan.');
    }

    public function destroyDiagnosa(Diagnosa $diagnosa): RedirectResponse
    {
        $this->authorizeMutableVisit($diagnosa->load('pemeriksaan.kunjungan')->pemeriksaan->kunjungan);
        $diagnosa->delete();

        return back()->with('success', 'Diagnosa berhasil dihapus.');
    }

    public function storeResep(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $isExternal = $request->boolean('is_resep_luar');
        $data = $request->validate([
            'obat_id' => ['nullable', 'required_unless:is_resep_luar,1', 'exists:obat,id'],
            'nama_obat' => ['nullable', 'required_if:is_resep_luar,1', 'string', 'max:255'],
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
            $stokDikurangi = false;

            if (! $isResepLuar) {
                if (! $obat || (float) $obat->stok < (float) $data['jumlah']) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Stok obat tidak mencukupi. Stok tersedia: '.($obat?->stok ?? 0).'.',
                    ]);
                }

                $stokSebelum = (float) $obat->stok;
                $obat->update(['stok' => $stokSebelum - (float) $data['jumlah']]);
                $stokDikurangi = true;
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
                'stok_dikurangi' => $stokDikurangi,
            ]);

            if ($stokDikurangi) {
                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'jenis' => 'keluar',
                    'referensi_type' => 'ResepObat',
                    'referensi_id' => $item->id,
                    'jumlah' => $data['jumlah'],
                    'harga' => $obat->harga_jual,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $obat->stok,
                    'keterangan' => "Reservasi resep {$resep->no_resep}",
                ]);
            }
        });

        return back()->with('success', 'Obat berhasil ditambahkan ke resep.');
    }

    public function destroyResep(ResepObat $resepObat): RedirectResponse
    {
        $resepObat->load('resep.kunjungan', 'obat');
        $this->authorizeMutableVisit($resepObat->resep->kunjungan);
        abort_unless($resepObat->resep->status === 'menunggu', 422, 'Resep yang sudah diproses tidak dapat diubah.');

        DB::transaction(function () use ($resepObat): void {
            if ($resepObat->stok_dikurangi && $resepObat->obat) {
                $obat = Obat::whereKey($resepObat->obat_id)->lockForUpdate()->firstOrFail();
                $stokSebelum = (float) $obat->stok;
                $obat->update(['stok' => $stokSebelum + (float) $resepObat->jumlah]);
                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'jenis' => 'masuk',
                    'referensi_type' => 'ResepObat',
                    'referensi_id' => $resepObat->id,
                    'jumlah' => $resepObat->jumlah,
                    'harga' => $obat->harga_jual,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $obat->stok,
                    'keterangan' => 'Pengembalian stok karena item resep dihapus',
                ]);
            }

            $resepObat->delete();
        });

        return back()->with('success', 'Obat berhasil dihapus dari resep.');
    }

    public function storeTindakan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $request->validate([
            'tindakan_id' => ['required', 'exists:tindakan,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'tooth_fdi' => ['nullable', Rule::in(OdontogramFinding::toothCodes())],
        ]);

        abort_if($request->filled('tooth_fdi') && $kunjungan->poliklinik?->jenis !== 'gigi', 422, 'Lokasi gigi hanya untuk kunjungan Poli Gigi.');

        $tindakan = Tindakan::findOrFail($request->tindakan_id);
        abort_unless($tindakan->poliklinik_id === $kunjungan->poliklinik_id, 422, 'Tindakan tidak tersedia di poliklinik kunjungan ini.');

        $kunjungan->tindakanKunjungan()->create([
            'tindakan_id' => $tindakan->id,
            'dokter_id' => $request->dokter_id ?? $kunjungan->dokter_id,
            'jumlah' => $request->jumlah,
            'tarif' => $tindakan->tarif * $request->jumlah,
            'tarif_dokter' => $tindakan->tarif_dokter * $request->jumlah,
            'tooth_fdi' => $request->input('tooth_fdi'),
        ]);

        return back()->with('success', 'Tindakan berhasil ditambahkan.');
    }

    public function destroyTindakan(TindakanKunjungan $tindakanKunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($tindakanKunjungan->load('kunjungan')->kunjungan);
        $tindakanKunjungan->delete();

        return back()->with('success', 'Tindakan berhasil dihapus.');
    }

    public function storeSurat(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $request->validate([
            'jenis' => ['required', 'in:sakit,sehat,rujukan,lainnya'],
            'konten' => ['nullable', 'string'],
            'nomor_surat' => ['nullable', 'string'],
            'tanggal' => ['required', 'date'],
        ]);

        $kunjungan->suratMedis()->create([
            'dokter_id' => $kunjungan->dokter_id,
            'jenis' => $request->jenis,
            'konten' => $request->konten,
            'nomor_surat' => $request->nomor_surat,
            'tanggal' => $request->tanggal,
        ]);

        return back()->with('success', 'Surat medis berhasil dibuat.');
    }

    public function storeRujukan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeMutableVisit($kunjungan);
        $request->validate([
            'ke_poli_id' => ['required', 'exists:poliklinik,id'],
            'catatan' => ['nullable', 'string'],
        ]);

        $kunjungan->rujukanInternal()->create([
            'dari_poli_id' => $kunjungan->poliklinik_id,
            'ke_poli_id' => $request->ke_poli_id,
            'catatan' => $request->catatan,
            'status' => 'menunggu',
        ]);

        return back()->with('success', 'Rujukan internal berhasil dibuat.');
    }

    public function selesai(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
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

            $noteRecorder->recordFinal($examination, $noteRecorder->snapshot($lockedVisit, $examination), $request->user()->id);
            $examination->update([
                'status' => 'selesai',
                'signed_by_user_id' => $request->user()->id,
                'signed_at' => now(),
            ]);

            $prescription = $lockedVisit->resep;
            if ($prescription) {
                Farmasi::firstOrCreate(
                    ['kunjungan_id' => $lockedVisit->id],
                    ['resep_id' => $prescription->id, 'status' => 'menunggu']
                );
                $lockedVisit->update(['status' => 'farmasi']);
            } else {
                $lockedVisit->update(['status' => 'kasir']);
            }

            $auditRecorder->record($request, 'clinical_examination.finalize', $lockedVisit->pasien_id, $lockedVisit->id);
        });

        return redirect()->route('pelayanan.pemeriksaan.index')
            ->with('success', 'Pemeriksaan selesai. Pasien diteruskan ke tahap berikutnya.');
    }

    public function addendum(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $noteRecorder, ClinicalAuditRecorder $auditRecorder): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'anamnesis' => ['sometimes', 'nullable', 'string'],
            'pemeriksaan_fisik' => ['sometimes', 'nullable', 'string'],
            'catatan' => ['sometimes', 'nullable', 'string'],
            'edukasi' => ['sometimes', 'nullable', 'string'],
            'kontrol_berikutnya' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $fields = array_intersect_key($data, array_flip(['anamnesis', 'pemeriksaan_fisik', 'catatan', 'edukasi', 'kontrol_berikutnya']));
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
        $query = $request->q;

        $results = Icd10::where('kode', 'like', "%$query%")
            ->orWhere('nama', 'like', "%$query%")
            ->limit(10)
            ->get(['kode', 'nama']);

        return response()->json($results);
    }

    private function currentDoctorId(): int
    {
        $doctorId = auth()->user()->nakes?->id;
        abort_unless($doctorId, 403, 'Akun dokter belum terhubung dengan data tenaga kesehatan.');

        return $doctorId;
    }

    private function authorizeVisit(Kunjungan $kunjungan): void
    {
        if (auth()->user()->role === 'dokter') {
            abort_unless($kunjungan->dokter_id === $this->currentDoctorId(), 403, 'Kunjungan bukan tanggung jawab dokter ini.');
        }
    }

    private function authorizeMutableVisit(Kunjungan $kunjungan): void
    {
        $this->authorizeVisit($kunjungan);

        abort_unless(
            $kunjungan->status === 'pemeriksaan' && $kunjungan->pemeriksaan?->status !== 'selesai',
            422,
            'Pemeriksaan sudah selesai dan tidak dapat diubah.'
        );
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
