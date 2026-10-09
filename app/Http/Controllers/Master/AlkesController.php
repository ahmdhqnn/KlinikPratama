<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Alkes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlkesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $alkes = Alkes::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('master/alkes/index', [
            'items' => [
                'data' => $alkes->getCollection()->map(fn (Alkes $item): array => [
                    'id' => $item->id,
                    'code' => $item->kode,
                    'name' => $item->nama,
                    'unit' => $item->satuan,
                    'stock' => $item->stok,
                    'minimumStock' => $item->stok_minimum,
                    'purchasePrice' => (float) $item->harga_beli,
                    'sellingPrice' => (float) $item->harga_jual,
                    'active' => $item->is_active,
                ])->values(),
                'currentPage' => $alkes->currentPage(),
                'lastPage' => $alkes->lastPage(),
                'perPage' => $alkes->perPage(),
                'total' => $alkes->total(),
                'from' => $alkes->firstItem(),
                'to' => $alkes->lastItem(),
                'previousUrl' => $alkes->previousPageUrl(),
                'nextUrl' => $alkes->nextPageUrl(),
            ],
            'filters' => $filters,
        ]);
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
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['harga_jual'] = 0;
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
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['harga_jual'] = 0;
        $alke->update($data);

        return back()->with('success', 'Alat kesehatan berhasil diperbarui.');
    }

    public function destroy(Alkes $alke): RedirectResponse
    {
        $alke->delete();

        return back()->with('success', 'Alat kesehatan berhasil dihapus.');
    }
}
