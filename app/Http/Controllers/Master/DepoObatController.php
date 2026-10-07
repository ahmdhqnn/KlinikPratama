<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepoObatController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $depoObat = DepoObat::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('master/depo-obat/index', [
            'depots' => [
                'data' => $depoObat->getCollection()->map(fn (DepoObat $depot): array => [
                    'id' => $depot->id,
                    'code' => $depot->kode,
                    'name' => $depot->nama,
                    'description' => $depot->deskripsi,
                    'active' => $depot->is_active,
                ])->values(),
                'currentPage' => $depoObat->currentPage(),
                'lastPage' => $depoObat->lastPage(),
                'perPage' => $depoObat->perPage(),
                'total' => $depoObat->total(),
                'from' => $depoObat->firstItem(),
                'to' => $depoObat->lastItem(),
                'previousUrl' => $depoObat->previousPageUrl(),
                'nextUrl' => $depoObat->nextPageUrl(),
            ],
            'filters' => $filters,
        ]);
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
