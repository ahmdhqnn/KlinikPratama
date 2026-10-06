<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use App\Models\Poliklinik;
use App\Models\RuangPoli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PoliklinikController extends Controller
{
    public function index(Request $request): View
    {
        $poliklinik = Poliklinik::with('depoObat')
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $depoList = DepoObat::where('is_active', true)->orderBy('nama')->get();

        return view('master.poliklinik.index', compact('poliklinik', 'depoList'));
    }

    public function show(Poliklinik $poliklinik): View
    {
        $poliklinik->load(['depoObat', 'ruangPoli']);
        $depoList = DepoObat::where('is_active', true)->orderBy('nama')->get();

        return view('master.poliklinik.show', compact('poliklinik', 'depoList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:poliklinik,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'string'],
            'depo_obat_id' => ['nullable', 'exists:depo_obat,id'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Poliklinik::create($data);

        return back()->with('success', 'Poliklinik berhasil ditambahkan.');
    }

    public function update(Request $request, Poliklinik $poliklinik): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', "unique:poliklinik,kode,{$poliklinik->id}"],
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'string'],
            'depo_obat_id' => ['nullable', 'exists:depo_obat,id'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $poliklinik->update($data);

        return back()->with('success', 'Poliklinik berhasil diperbarui.');
    }

    public function destroy(Poliklinik $poliklinik): RedirectResponse
    {
        $poliklinik->delete();

        return back()->with('success', 'Poliklinik berhasil dihapus.');
    }

    public function storeRuang(Request $request, Poliklinik $poliklinik): RedirectResponse
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:100'],
        ]);

        $poliklinik->ruangPoli()->create([
            'nama' => $request->nama,
            'is_active' => true,
        ]);

        return back()->with('success', 'Ruang poli berhasil ditambahkan.');
    }

    public function destroyRuang(RuangPoli $ruang): RedirectResponse
    {
        $ruang->delete();

        return back()->with('success', 'Ruang poli berhasil dihapus.');
    }
}
