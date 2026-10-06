<?php

namespace App\Http\Controllers\Master;

use App\Exports\ObatExport;
use App\Exports\ObatTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ObatImport;
use App\Models\Obat;
use App\Models\StokMutasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ObatController extends Controller
{
    public function index(Request $request): View
    {
        $obat = Obat::query()
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%")->orWhere('kode_kfa', 'like', "%$s%"))
            ->when($request->jenis, fn ($q, $j) => $q->where('jenis', $j))
            ->when($request->stok_rendah, fn ($q) => $q->whereColumn('stok', '<=', 'stok_minimum'))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('master.obat.index', compact('obat'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:obat,kode'],
            'kode_kfa' => ['nullable', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:200'],
            'satuan_besar' => ['nullable', 'string', 'max:50'],
            'satuan_kecil' => ['nullable', 'string', 'max:50'],
            'konversi_satuan' => ['required', 'numeric', 'min:1'],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'indikasi' => ['nullable', 'string'],
            'kandungan' => ['nullable', 'string'],
            'stok' => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'jenis' => ['required', 'in:obat,bhp'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Obat::create($data);

        return back()->with('success', 'Obat berhasil ditambahkan.');
    }

    public function update(Request $request, Obat $obat): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:obat,kode,{$obat->id}"],
            'kode_kfa' => ['nullable', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:200'],
            'satuan_besar' => ['nullable', 'string', 'max:50'],
            'satuan_kecil' => ['nullable', 'string', 'max:50'],
            'konversi_satuan' => ['required', 'numeric', 'min:1'],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'indikasi' => ['nullable', 'string'],
            'kandungan' => ['nullable', 'string'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'jenis' => ['required', 'in:obat,bhp'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $obat->update($data);

        return back()->with('success', 'Obat berhasil diperbarui.');
    }

    public function destroy(Obat $obat): RedirectResponse
    {
        $obat->delete();

        return back()->with('success', 'Obat berhasil dihapus.');
    }

    public function stok(Obat $obat): View
    {
        $mutasi = StokMutasi::where('obat_id', $obat->id)
            ->latest()
            ->paginate(20);

        return view('master.obat.stok', compact('obat', 'mutasi'));
    }

    public function tambahStok(Request $request, Obat $obat): RedirectResponse
    {
        $request->validate([
            'jumlah' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $stokSebelum = $obat->stok;
        $stokSesudah = $stokSebelum + $request->jumlah;

        $obat->update(['stok' => $stokSesudah]);

        StokMutasi::create([
            'obat_id' => $obat->id,
            'jenis' => 'masuk',
            'jumlah' => $request->jumlah,
            'harga' => $obat->harga_beli,
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSesudah,
            'keterangan' => $request->keterangan ?? 'Penambahan stok manual',
        ]);

        return back()->with('success', "Stok berhasil ditambah sebanyak {$request->jumlah}.");
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new ObatExport, 'data-obat-'.date('Y-m-d').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            Excel::import(new ObatImport, $request->file('file'));

            return back()->with('success', 'Data obat berhasil diimpor.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengimpor data: '.$e->getMessage());
        }
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ObatTemplateExport, 'template-import-obat.xlsx');
    }
}
