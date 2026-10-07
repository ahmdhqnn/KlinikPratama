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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ObatController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:obat,bhp'],
            'stok_rendah' => ['nullable', 'boolean'],
        ]);
        $obat = Obat::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($subquery) => $subquery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")
                ->orWhere('kode_kfa', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when(filter_var($filters['stok_rendah'] ?? false, FILTER_VALIDATE_BOOL), fn ($query) => $query->whereColumn('stok', '<=', 'stok_minimum'))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('master/obat/index', [
            'medicines' => [
                'data' => $obat->getCollection()->map(fn (Obat $medicine): array => [
                    'id' => $medicine->id,
                    'code' => $medicine->kode,
                    'kfaCode' => $medicine->kode_kfa,
                    'name' => $medicine->nama,
                    'largeUnit' => $medicine->satuan_besar,
                    'smallUnit' => $medicine->satuan_kecil,
                    'unitConversion' => (float) $medicine->konversi_satuan,
                    'purchasePrice' => (float) $medicine->harga_beli,
                    'sellingPrice' => (float) $medicine->harga_jual,
                    'stock' => (float) $medicine->stok,
                    'minimumStock' => (float) $medicine->stok_minimum,
                    'indication' => $medicine->indikasi,
                    'ingredients' => $medicine->kandungan,
                    'type' => $medicine->jenis,
                    'active' => $medicine->is_active,
                ])->values(),
                'currentPage' => $obat->currentPage(),
                'lastPage' => $obat->lastPage(),
                'perPage' => $obat->perPage(),
                'total' => $obat->total(),
                'from' => $obat->firstItem(),
                'to' => $obat->lastItem(),
                'previousUrl' => $obat->previousPageUrl(),
                'nextUrl' => $obat->nextPageUrl(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['jenis'] ?? '',
                'lowStock' => filter_var($filters['stok_rendah'] ?? false, FILTER_VALIDATE_BOOL),
            ],
        ]);
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

    public function stok(Obat $obat): Response
    {
        $mutasi = StokMutasi::where('obat_id', $obat->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('master/obat/stok', [
            'medicine' => [
                'id' => $obat->id,
                'code' => $obat->kode,
                'name' => $obat->nama,
                'stock' => (float) $obat->stok,
                'minimumStock' => (float) $obat->stok_minimum,
                'unit' => $obat->satuan_kecil,
            ],
            'movements' => [
                'data' => $mutasi->getCollection()->map(fn (StokMutasi $movement): array => [
                    'id' => $movement->id,
                    'date' => $movement->created_at?->toIso8601String(),
                    'type' => $movement->jenis,
                    'quantity' => (float) $movement->jumlah,
                    'stockBefore' => (float) $movement->stok_sebelum,
                    'stockAfter' => (float) $movement->stok_sesudah,
                    'description' => $movement->keterangan,
                ])->values(),
                'currentPage' => $mutasi->currentPage(),
                'lastPage' => $mutasi->lastPage(),
                'perPage' => $mutasi->perPage(),
                'total' => $mutasi->total(),
                'from' => $mutasi->firstItem(),
                'to' => $mutasi->lastItem(),
                'previousUrl' => $mutasi->previousPageUrl(),
                'nextUrl' => $mutasi->nextPageUrl(),
            ],
        ]);
    }

    public function tambahStok(Request $request, Obat $obat): RedirectResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $obat): void {
            $lockedMedicine = Obat::query()->lockForUpdate()->findOrFail($obat->id);
            $stockBefore = (float) $lockedMedicine->stok;
            $stockAfter = $stockBefore + (float) $data['jumlah'];

            $lockedMedicine->update(['stok' => $stockAfter]);

            StokMutasi::create([
                'obat_id' => $lockedMedicine->id,
                'jenis' => 'masuk',
                'jumlah' => $data['jumlah'],
                'harga' => $lockedMedicine->harga_beli,
                'stok_sebelum' => $stockBefore,
                'stok_sesudah' => $stockAfter,
                'keterangan' => $data['keterangan'] ?? 'Penambahan stok manual',
            ]);
        });

        return back()->with('success', "Stok berhasil ditambah sebanyak {$data['jumlah']}.");
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
