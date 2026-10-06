<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\BiayaAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BiayaAdminController extends Controller
{
    public function index(Request $request): View
    {
        $biayaAdmin = BiayaAdmin::query()
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%"))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('master.biaya-admin.index', compact('biayaAdmin'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        BiayaAdmin::create([
            'nama' => $request->nama,
            'tarif' => $request->tarif,
            'keterangan' => $request->keterangan,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Biaya admin berhasil ditambahkan.');
    }

    public function update(Request $request, BiayaAdmin $biayaAdmin): RedirectResponse
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $biayaAdmin->update([
            'nama' => $request->nama,
            'tarif' => $request->tarif,
            'keterangan' => $request->keterangan,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Biaya admin berhasil diperbarui.');
    }

    public function destroy(BiayaAdmin $biayaAdmin): RedirectResponse
    {
        $biayaAdmin->delete();

        return back()->with('success', 'Biaya admin berhasil dihapus.');
    }
}
