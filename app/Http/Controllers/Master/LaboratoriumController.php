<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LabBhp;
use App\Models\LabIndikator;
use App\Models\Laboratorium;
use App\Models\Obat;
use App\Models\Poliklinik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaboratoriumController extends Controller
{
    public function index(Request $request): View
    {
        $laboratorium = Laboratorium::with('poliklinik')
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->orderBy('nama')->get();

        return view('master.laboratorium.index', compact('laboratorium', 'poliklinikList', 'obatList'));
    }

    public function show(Laboratorium $laboratorium): View
    {
        $laboratorium->load(['poliklinik', 'indikator', 'bhp.obat']);
        $obatList = Obat::where('is_active', true)->orderBy('nama')->get();

        return view('master.laboratorium.show', compact('laboratorium', 'obatList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:laboratorium,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Laboratorium::create($data);

        return back()->with('success', 'Pemeriksaan lab berhasil ditambahkan.');
    }

    public function update(Request $request, Laboratorium $laboratorium): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:laboratorium,kode,{$laboratorium->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $laboratorium->update($data);

        return back()->with('success', 'Pemeriksaan lab berhasil diperbarui.');
    }

    public function destroy(Laboratorium $laboratorium): RedirectResponse
    {
        $laboratorium->delete();

        return back()->with('success', 'Pemeriksaan lab berhasil dihapus.');
    }

    public function storeIndikator(Request $request, Laboratorium $laboratorium): RedirectResponse
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'nilai_rujukan_min' => ['nullable', 'string'],
            'nilai_rujukan_max' => ['nullable', 'string'],
            'format_input' => ['required', 'in:text,number,select'],
            'pilihan' => ['nullable', 'string'],
        ]);

        $pilihan = null;
        if ($request->format_input === 'select' && $request->pilihan) {
            $items = array_filter(array_map('trim', explode(',', $request->pilihan)));
            $pilihan = json_encode(array_values($items));
        }

        $laboratorium->indikator()->create([
            'nama' => $request->nama,
            'satuan' => $request->satuan,
            'nilai_rujukan_min' => $request->nilai_rujukan_min,
            'nilai_rujukan_max' => $request->nilai_rujukan_max,
            'format_input' => $request->format_input,
            'pilihan' => $pilihan,
            'urutan' => $laboratorium->indikator()->count() + 1,
        ]);

        return back()->with('success', 'Indikator berhasil ditambahkan.');
    }

    public function destroyIndikator(LabIndikator $indikator): RedirectResponse
    {
        $indikator->delete();

        return back()->with('success', 'Indikator berhasil dihapus.');
    }

    public function storeBhp(Request $request, Laboratorium $laboratorium): RedirectResponse
    {
        $request->validate([
            'obat_id' => ['required', 'exists:obat,id'],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
        ]);

        $laboratorium->bhp()->create([
            'obat_id' => $request->obat_id,
            'jumlah' => $request->jumlah,
        ]);

        return back()->with('success', 'BHP lab berhasil ditambahkan.');
    }

    public function destroyBhp(LabBhp $bhp): RedirectResponse
    {
        $bhp->delete();

        return back()->with('success', 'BHP lab berhasil dihapus.');
    }
}
