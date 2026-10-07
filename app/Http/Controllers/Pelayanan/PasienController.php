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
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PasienController extends Controller
{
    public function index(Request $request): View
    {
        $pasien = Pasien::with('asuransi')
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")
                ->orWhere('no_rm', 'like', "%$s%")
                ->orWhere('nik', 'like', "%$s%")
                ->orWhere('telepon', 'like', "%$s%"))
            ->when($request->asuransi_id, fn ($q, $a) => $q->where('asuransi_id', $a))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.pasien.index', compact('pasien', 'asuransiList'));
    }

    public function create(): View
    {
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.pasien.create', compact('asuransiList'));
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

    public function show(Pasien $pasien): View
    {
        $this->authorizeDoctorPatient($pasien);
        $pasien->load(['asuransi', 'kunjungan' => fn ($q) => $q->with(['poliklinik', 'dokter', 'tagihan'])->latest()->limit(20)]);

        return view('pelayanan.pasien.show', compact('pasien'));
    }

    public function edit(Pasien $pasien): View
    {
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.pasien.edit', compact('pasien', 'asuransiList'));
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

    public function rekamMedis(Pasien $pasien): View
    {
        $this->authorizeDoctorPatient($pasien);
        $pasien->load(['kunjungan' => fn ($q) => $q->with([
            'poliklinik', 'dokter', 'screening', 'pemeriksaan.diagnosa', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'tagihan', 'suratMedis', 'labHasil.laboratorium',
        ])->latest()]);

        return view('pelayanan.pasien.rekam-medis', compact('pasien'));
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
