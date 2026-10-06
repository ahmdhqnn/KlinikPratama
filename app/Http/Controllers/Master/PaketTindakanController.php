<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Laboratorium;
use App\Models\PaketTindakan;
use App\Models\PaketTindakanItem;
use App\Models\Tindakan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaketTindakanController extends Controller
{
    public function index(Request $request): View
    {
        $paket = PaketTindakan::withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($sub) => $sub->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%")))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $tindakanList = Tindakan::where('is_active', true)->orderBy('nama')->get();
        $labList = Laboratorium::where('is_active', true)->orderBy('nama')->get();

        return view('master.paket-tindakan.index', compact('paket', 'tindakanList', 'labList'));
    }

    public function show(PaketTindakan $paketTindakan): View
    {
        $paketTindakan->load(['items.tindakan', 'items.laboratorium']);
        $tindakanList = Tindakan::where('is_active', true)->orderBy('nama')->get();
        $labList = Laboratorium::where('is_active', true)->orderBy('nama')->get();

        return view('master.paket-tindakan.show', compact('paketTindakan', 'tindakanList', 'labList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:paket_tindakan,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        PaketTindakan::create($data);

        return back()->with('success', 'Paket tindakan berhasil ditambahkan.');
    }

    public function update(Request $request, PaketTindakan $paketTindakan): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:paket_tindakan,kode,{$paketTindakan->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'tarif' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $paketTindakan->update($data);

        return back()->with('success', 'Paket tindakan berhasil diperbarui.');
    }

    public function destroy(PaketTindakan $paketTindakan): RedirectResponse
    {
        $paketTindakan->delete();

        return back()->with('success', 'Paket tindakan berhasil dihapus.');
    }

    public function storeItem(Request $request, PaketTindakan $paketTindakan): RedirectResponse
    {
        $request->validate([
            'jenis' => ['required', 'in:tindakan,lab'],
            'tindakan_id' => ['nullable', 'required_if:jenis,tindakan', 'exists:tindakan,id'],
            'laboratorium_id' => ['nullable', 'required_if:jenis,lab', 'exists:laboratorium,id'],
        ]);

        $paketTindakan->items()->create([
            'jenis' => $request->jenis,
            'tindakan_id' => $request->jenis === 'tindakan' ? $request->tindakan_id : null,
            'laboratorium_id' => $request->jenis === 'lab' ? $request->laboratorium_id : null,
        ]);

        return back()->with('success', 'Item berhasil ditambahkan ke paket tindakan.');
    }

    public function destroyItem(PaketTindakanItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', 'Item berhasil dihapus dari paket tindakan.');
    }
}
