<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepoObatController extends Controller
{
    public function index(Request $request): View
    {
        $depoObat = DepoObat::query()
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        return view('master.depo-obat.index', compact('depoObat'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:depo_obat,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        DepoObat::create($data);

        return back()->with('success', 'Depo obat berhasil ditambahkan.');
    }

    public function update(Request $request, DepoObat $depoObat): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', "unique:depo_obat,kode,{$depoObat->id}"],
            'nama' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $depoObat->update($data);

        return back()->with('success', 'Depo obat berhasil diperbarui.');
    }

    public function destroy(DepoObat $depoObat): RedirectResponse
    {
        $depoObat->delete();

        return back()->with('success', 'Depo obat berhasil dihapus.');
    }
}
