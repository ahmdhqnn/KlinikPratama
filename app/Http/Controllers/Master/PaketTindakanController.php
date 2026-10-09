<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Laboratorium;
use App\Models\PaketTindakan;
use App\Models\PaketTindakanItem;
use App\Models\Tindakan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaketTindakanController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $packages = PaketTindakan::withCount('items')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($subquery) => $subquery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('master/paket-tindakan/index', [
            'packages' => [
                'data' => $packages->getCollection()->map(fn (PaketTindakan $package): array => [
                    'id' => $package->id,
                    'code' => $package->kode,
                    'name' => $package->nama,
                    'description' => $package->deskripsi,
                    'tariff' => (float) $package->tarif,
                    'active' => $package->is_active,
                    'itemCount' => $package->items_count,
                ])->values(),
                'currentPage' => $packages->currentPage(),
                'lastPage' => $packages->lastPage(),
                'perPage' => $packages->perPage(),
                'total' => $packages->total(),
                'from' => $packages->firstItem(),
                'to' => $packages->lastItem(),
                'previousUrl' => $packages->previousPageUrl(),
                'nextUrl' => $packages->nextPageUrl(),
            ],
            'filters' => ['search' => $filters['search'] ?? ''],
        ]);
    }

    public function show(PaketTindakan $paketTindakan): Response
    {
        $paketTindakan->load(['items.tindakan', 'items.laboratorium']);

        $treatments = Tindakan::where('is_active', true)->orderBy('nama')->get();
        $laboratories = Laboratorium::where('is_active', true)->orderBy('nama')->get();
        $items = $paketTindakan->items;

        return Inertia::render('master/paket-tindakan/show', [
            'package' => [
                'id' => $paketTindakan->id,
                'code' => $paketTindakan->kode,
                'name' => $paketTindakan->nama,
                'description' => $paketTindakan->deskripsi,
                'tariff' => (float) $paketTindakan->tarif,
                'active' => $paketTindakan->is_active,
                'items' => $items->map(function (PaketTindakanItem $item): array {
                    $service = $item->jenis === 'tindakan' ? $item->tindakan : $item->laboratorium;

                    return [
                        'id' => $item->id,
                        'type' => $item->jenis,
                        'name' => $service?->nama ?? 'Layanan dihapus',
                        'tariff' => (float) ($service?->tarif ?? 0),
                    ];
                })->values(),
                'standardTariffTotal' => (float) $items->sum(fn (PaketTindakanItem $item): float => (float) ($item->jenis === 'tindakan' ? $item->tindakan?->tarif : $item->laboratorium?->tarif)),
            ],
            'treatments' => $treatments->map(fn (Tindakan $treatment): array => [
                'id' => $treatment->id,
                'name' => $treatment->nama,
                'tariff' => (float) $treatment->tarif,
            ])->values(),
            'laboratories' => $laboratories->map(fn (Laboratorium $laboratory): array => [
                'id' => $laboratory->id,
                'name' => $laboratory->nama,
                'tariff' => (float) $laboratory->tarif,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:paket_tindakan,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'tarif' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['tarif'] = 0;
        PaketTindakan::create($data);

        return back()->with('success', 'Paket tindakan berhasil ditambahkan.');
    }

    public function update(Request $request, PaketTindakan $paketTindakan): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:paket_tindakan,kode,{$paketTindakan->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'tarif' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['tarif'] = 0;
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
