<?php

namespace App\Http\Controllers\Pelayanan;

use App\Exports\PasienExport;
use App\Exports\PasienTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PasienImport;
use App\Models\Asuransi;
use App\Models\Pasien;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PasienController extends Controller
{
    public function index(Request $request): Response
    {
        $pasien = Pasien::with('asuransi')
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

        return Inertia::render('pelayanan/pasien/index', [
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
            'riwayat_alergi' => ['nullable', 'string'],
        ]);

        // Generate No. RM
        $lastPasien = Pasien::withTrashed()->latest('id')->first();
        $nextId = $lastPasien ? ($lastPasien->id + 1) : 1;
        $data['no_rm'] = 'RM-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);

        $pasien = Pasien::create($data);

        return redirect()->route('pelayanan.pasien.show', $pasien)
            ->with('success', "Pasien berhasil didaftarkan dengan No. RM: {$pasien->no_rm}");
    }

    public function show(Pasien $pasien): Response
    {
        $this->authorizeDoctorPatient($pasien);
        $pasien->load(['asuransi', 'kunjungan' => fn ($q) => $q->with(['poliklinik', 'dokter', 'tagihan'])->latest()->limit(20)]);

        return Inertia::render('pelayanan/pasien/show', [
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
                'allergies' => $pasien->riwayat_alergi,
                'visits' => $pasien->kunjungan->map(fn ($visit) => [
                    'id' => $visit->id,
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
                'allergies' => $pasien->riwayat_alergi,
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
            'riwayat_alergi' => ['nullable', 'string'],
        ]);

        $pasien->update($data);

        return redirect()->route('pelayanan.pasien.show', $pasien)->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(Pasien $pasien): RedirectResponse
    {
        $pasien->delete();

        return redirect()->route('pelayanan.pasien.index')->with('success', 'Data pasien berhasil dihapus.');
    }

    public function rekamMedis(Pasien $pasien): Response
    {
        $this->authorizeDoctorPatient($pasien);
        $pasien->load(['kunjungan' => fn ($query) => $query->with([
            'poliklinik:id,nama',
            'dokter:id,nama',
            'screening',
            'pemeriksaan.diagnosa',
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
            'visits' => $pasien->kunjungan->map(fn ($visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'clinic' => $visit->poliklinik?->nama ?? '—',
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
                    'anamnesis' => $visit->pemeriksaan->anamnesis,
                    'physicalExamination' => $visit->pemeriksaan->pemeriksaan_fisik,
                    'education' => $visit->pemeriksaan->edukasi,
                    'notes' => $visit->pemeriksaan->catatan,
                    'nextControl' => $visit->pemeriksaan->kontrol_berikutnya?->format('d/m/Y'),
                    'diagnoses' => $visit->pemeriksaan->diagnosa->map(fn ($diagnosis): array => [
                        'code' => $diagnosis->kode_icd10,
                        'name' => $diagnosis->nama_diagnosa,
                        'type' => $diagnosis->jenis,
                    ])->all(),
                ] : null,
                'prescriptions' => $visit->resep?->resepObat->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->nama_obat,
                    'quantity' => $item->jumlah,
                    'unit' => $item->satuan,
                    'instructions' => $item->aturan_pakai,
                ])->all() ?? [],
                'treatments' => $visit->tindakanKunjungan->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->tindakan?->nama ?? 'Tindakan dihapus',
                    'quantity' => $item->jumlah,
                ])->all(),
            ])->all(),
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

    public function export(): BinaryFileResponse
    {
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
