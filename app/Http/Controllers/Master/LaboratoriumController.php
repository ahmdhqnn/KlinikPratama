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
use Inertia\Inertia;
use Inertia\Response;

class LaboratoriumController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $laboratories = Laboratorium::with('poliklinik')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($subquery) use ($search): void {
                $subquery->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $clinics = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('master/laboratorium/index', [
            'laboratories' => [
                'data' => $laboratories->getCollection()->map(fn (Laboratorium $laboratory): array => [
                    'id' => $laboratory->id,
                    'code' => $laboratory->kode,
                    'name' => $laboratory->nama,
                    'clinicId' => $laboratory->poliklinik_id,
                    'clinic' => $laboratory->poliklinik?->nama,
                    'tariff' => (float) $laboratory->tarif,
                    'description' => $laboratory->deskripsi,
                    'active' => $laboratory->is_active,
                ])->values(),
                'currentPage' => $laboratories->currentPage(),
                'lastPage' => $laboratories->lastPage(),
                'perPage' => $laboratories->perPage(),
                'total' => $laboratories->total(),
                'from' => $laboratories->firstItem(),
                'to' => $laboratories->lastItem(),
                'previousUrl' => $laboratories->previousPageUrl(),
                'nextUrl' => $laboratories->nextPageUrl(),
            ],
            'filters' => ['search' => $filters['search'] ?? ''],
            'clinics' => $clinics->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
        ]);
    }

    public function show(Laboratorium $laboratorium): Response
    {
        $laboratorium->load(['poliklinik', 'indikator', 'bhp.obat']);
        $medicines = Obat::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('master/laboratorium/show', [
            'laboratory' => [
                'id' => $laboratorium->id,
                'code' => $laboratorium->kode,
                'name' => $laboratorium->nama,
                'clinic' => $laboratorium->poliklinik?->nama ?? 'Umum',
                'tariff' => (float) $laboratorium->tarif,
                'indicators' => $laboratorium->indikator->map(fn (LabIndikator $indicator): array => [
                    'id' => $indicator->id,
                    'name' => $indicator->nama,
                    'unit' => $indicator->satuan,
                    'referenceMin' => $indicator->nilai_rujukan_min,
                    'referenceMax' => $indicator->nilai_rujukan_max,
                    'format' => $indicator->format_input,
                    'choices' => $indicator->pilihan ?? [],
                ])->values(),
                'supplies' => $laboratorium->bhp->map(fn (LabBhp $supply): array => [
                    'id' => $supply->id,
                    'medicine' => $supply->obat?->nama ?? 'Item dihapus',
                    'unit' => $supply->obat?->satuan_kecil ?? '—',
                    'quantity' => (float) $supply->jumlah,
                ])->values(),
            ],
            'medicines' => $medicines->map(fn (Obat $medicine): array => [
                'id' => $medicine->id,
                'name' => $medicine->nama,
                'stock' => (float) $medicine->stok,
                'unit' => $medicine->satuan_kecil,
            ])->values(),
        ]);
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
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'nilai_rujukan_min' => ['nullable', 'string'],
            'nilai_rujukan_max' => ['nullable', 'string'],
            'format_input' => ['required', 'in:text,number,select'],
            'pilihan' => ['nullable', 'string'],
        ]);

        $data['pilihan'] = $data['format_input'] === 'select' && filled($data['pilihan'] ?? null)
            ? array_values(array_filter(array_map('trim', explode(',', $data['pilihan']))))
            : null;
        $data['urutan'] = $laboratorium->indikator()->count() + 1;
        $laboratorium->indikator()->create($data);

        return back()->with('success', 'Indikator berhasil ditambahkan.');
    }

    public function destroyIndikator(LabIndikator $indikator): RedirectResponse
    {
        $indikator->delete();

        return back()->with('success', 'Indikator berhasil dihapus.');
    }

    public function storeBhp(Request $request, Laboratorium $laboratorium): RedirectResponse
    {
        $data = $request->validate([
            'obat_id' => ['required', 'exists:obat,id'],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
        ]);

        $laboratorium->bhp()->create($data);

        return back()->with('success', 'BHP lab berhasil ditambahkan.');
    }

    public function destroyBhp(LabBhp $bhp): RedirectResponse
    {
        $bhp->delete();

        return back()->with('success', 'BHP lab berhasil dihapus.');
    }
}
