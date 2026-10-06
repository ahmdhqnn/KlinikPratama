<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\BiayaPendaftaran;
use App\Models\Nakes;
use App\Models\Poliklinik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BiayaPendaftaranController extends Controller
{
    public function index(Request $request): View
    {
        $biayaPendaftaran = BiayaPendaftaran::with(['poliklinik', 'dokter'])
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();

        return view('master.biaya-pendaftaran.index', compact('biayaPendaftaran', 'poliklinikList', 'dokterList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'tarif' => ['required', 'numeric', 'min:0'],
        ]);

        BiayaPendaftaran::create($request->only(['poliklinik_id', 'dokter_id', 'jenis_pasien', 'tarif']));

        return back()->with('success', 'Biaya pendaftaran berhasil ditambahkan.');
    }

    public function update(Request $request, BiayaPendaftaran $biayaPendaftaran): RedirectResponse
    {
        $request->validate([
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'tarif' => ['required', 'numeric', 'min:0'],
        ]);

        $biayaPendaftaran->update($request->only(['poliklinik_id', 'dokter_id', 'jenis_pasien', 'tarif']));

        return back()->with('success', 'Biaya pendaftaran berhasil diperbarui.');
    }

    public function destroy(BiayaPendaftaran $biayaPendaftaran): RedirectResponse
    {
        $biayaPendaftaran->delete();

        return back()->with('success', 'Biaya pendaftaran berhasil dihapus.');
    }
}
