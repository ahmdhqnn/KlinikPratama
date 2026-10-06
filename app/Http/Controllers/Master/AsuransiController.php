<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\AsuransiHarga;
use App\Models\Obat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AsuransiController extends Controller
{
    public function index(Request $request): View
    {
        $asuransi = Asuransi::query()
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->when($request->jenis, fn ($q, $j) => $q->where('jenis', $j))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('master.asuransi.index', compact('asuransi'));
    }

    public function show(Asuransi $asuransi): View
    {
        $asuransi->load('asuransiHarga.obat');
        $obatList = Obat::where('is_active', true)->orderBy('nama')->get();

        return view('master.asuransi.show', compact('asuransi', 'obatList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:asuransi,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'in:umum,bpjs,perusahaan'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Asuransi::create($data);

        return back()->with('success', 'Asuransi berhasil ditambahkan.');
    }

    public function update(Request $request, Asuransi $asuransi): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:asuransi,kode,{$asuransi->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'in:umum,bpjs,perusahaan'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $asuransi->update($data);

        return back()->with('success', 'Asuransi berhasil diperbarui.');
    }

    public function destroy(Asuransi $asuransi): RedirectResponse
    {
        $asuransi->delete();

        return back()->with('success', 'Asuransi berhasil dihapus.');
    }

    public function storeHarga(Request $request, Asuransi $asuransi): RedirectResponse
    {
        $request->validate([
            'obat_id' => ['required', 'exists:obat,id'],
            'harga_khusus' => ['required', 'numeric', 'min:0'],
        ]);

        $asuransi->asuransiHarga()->updateOrCreate(
            ['obat_id' => $request->obat_id],
            ['harga_khusus' => $request->harga_khusus]
        );

        return back()->with('success', 'Harga khusus berhasil disimpan.');
    }

    public function destroyHarga(AsuransiHarga $harga): RedirectResponse
    {
        $harga->delete();

        return back()->with('success', 'Harga khusus berhasil dihapus.');
    }
}
