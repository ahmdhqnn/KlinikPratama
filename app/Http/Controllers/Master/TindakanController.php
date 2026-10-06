<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\TindakanBhp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TindakanController extends Controller
{
    public function index(Request $request): View
    {
        $tindakan = Tindakan::with('poliklinik')
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->when($request->kategori, fn ($q, $k) => $q->where('kategori', $k))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->orderBy('nama')->get();

        return view('master.tindakan.index', compact('tindakan', 'poliklinikList', 'obatList'));
    }

    public function show(Tindakan $tindakan): View
    {
        $tindakan->load(['poliklinik', 'bhp.obat']);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->orderBy('nama')->get();

        return view('master.tindakan.show', compact('tindakan', 'poliklinikList', 'obatList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:tindakan,kode'],
            'kode_icd9' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'in:medis,lab'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'tarif_dokter' => ['required', 'numeric', 'min:0'],
            'tarif_asisten' => ['required', 'numeric', 'min:0'],
            'tarif_klinik' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Tindakan::create($data);

        return back()->with('success', 'Tindakan berhasil ditambahkan.');
    }

    public function update(Request $request, Tindakan $tindakan): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:tindakan,kode,{$tindakan->id}"],
            'kode_icd9' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'in:medis,lab'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'tarif_dokter' => ['required', 'numeric', 'min:0'],
            'tarif_asisten' => ['required', 'numeric', 'min:0'],
            'tarif_klinik' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $tindakan->update($data);

        return back()->with('success', 'Tindakan berhasil diperbarui.');
    }

    public function destroy(Tindakan $tindakan): RedirectResponse
    {
        $tindakan->delete();

        return back()->with('success', 'Tindakan berhasil dihapus.');
    }

    public function storeBhp(Request $request, Tindakan $tindakan): RedirectResponse
    {
        $request->validate([
            'obat_id' => ['required', 'exists:obat,id'],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
        ]);

        $tindakan->bhp()->create([
            'obat_id' => $request->obat_id,
            'jumlah' => $request->jumlah,
        ]);

        return back()->with('success', 'BHP berhasil ditambahkan.');
    }

    public function destroyBhp(TindakanBhp $bhp): RedirectResponse
    {
        $bhp->delete();

        return back()->with('success', 'BHP berhasil dihapus.');
    }
}
