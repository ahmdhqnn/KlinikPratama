<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\BiayaAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BiayaAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $biayaAdmin = BiayaAdmin::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('nama', 'like', "%{$search}%"))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('master/biaya-admin/index', [
            'fees' => [
                'data' => $biayaAdmin->getCollection()->map(fn (BiayaAdmin $fee): array => [
                    'id' => $fee->id,
                    'name' => $fee->nama,
                    'tariff' => (float) $fee->tarif,
                    'description' => $fee->keterangan,
                    'active' => $fee->is_active,
                ])->values(),
                'currentPage' => $biayaAdmin->currentPage(),
                'lastPage' => $biayaAdmin->lastPage(),
                'perPage' => $biayaAdmin->perPage(),
                'total' => $biayaAdmin->total(),
                'from' => $biayaAdmin->firstItem(),
                'to' => $biayaAdmin->lastItem(),
                'previousUrl' => $biayaAdmin->previousPageUrl(),
                'nextUrl' => $biayaAdmin->nextPageUrl(),
            ],
            'filters' => $filters,
        ]);
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
