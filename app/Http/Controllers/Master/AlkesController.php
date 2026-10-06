<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Alkes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlkesController extends Controller
{
    public function index(Request $request): View
    {
        $alkes = Alkes::query()
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('master.alkes.index', compact('alkes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:alkes,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'stok' => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Alkes::create($data);

        return back()->with('success', 'Alat kesehatan berhasil ditambahkan.');
    }

    public function update(Request $request, Alkes $alke): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:alkes,kode,{$alke->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'stok' => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $alke->update($data);

        return back()->with('success', 'Alat kesehatan berhasil diperbarui.');
    }

    public function destroy(Alkes $alke): RedirectResponse
    {
        $alke->delete();

        return back()->with('success', 'Alat kesehatan berhasil dihapus.');
    }
}
