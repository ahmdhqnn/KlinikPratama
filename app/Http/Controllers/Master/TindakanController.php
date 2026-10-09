<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\TindakanBhp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TindakanController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'in:medis,lab'],
        ]);
        $tindakan = Tindakan::with('poliklinik')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->when($filters['kategori'] ?? null, fn ($query, string $category) => $query->where('kategori', $category))
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('master/tindakan/index', [
            'treatments' => [
                'data' => $tindakan->getCollection()->map(fn (Tindakan $treatment): array => [
                    'id' => $treatment->id,
                    'code' => $treatment->kode,
                    'icd9Code' => $treatment->kode_icd9,
                    'name' => $treatment->nama,
                    'category' => $treatment->kategori,
                    'clinicId' => $treatment->poliklinik_id,
                    'clinic' => $treatment->poliklinik?->nama,
                    'tariff' => (float) $treatment->tarif,
                    'doctorTariff' => (float) $treatment->tarif_dokter,
                    'assistantTariff' => (float) $treatment->tarif_asisten,
                    'clinicTariff' => (float) $treatment->tarif_klinik,
                    'active' => $treatment->is_active,
                ])->values(),
                'currentPage' => $tindakan->currentPage(),
                'lastPage' => $tindakan->lastPage(),
                'perPage' => $tindakan->perPage(),
                'total' => $tindakan->total(),
                'from' => $tindakan->firstItem(),
                'to' => $tindakan->lastItem(),
                'previousUrl' => $tindakan->previousPageUrl(),
                'nextUrl' => $tindakan->nextPageUrl(),
            ],
            'filters' => ['search' => $filters['search'] ?? '', 'category' => $filters['kategori'] ?? ''],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'categories' => [
                ['value' => 'medis', 'label' => 'Medis'],
                ['value' => 'lab', 'label' => 'Laboratorium'],
            ],
        ]);
    }

    public function show(Tindakan $tindakan): Response
    {
        $tindakan->load(['poliklinik', 'bhp.obat']);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->where('jenis', 'bhp')->orderBy('nama')->get();

        return Inertia::render('master/tindakan/show', [
            'treatment' => [
                'id' => $tindakan->id,
                'code' => $tindakan->kode,
                'name' => $tindakan->nama,
                'bhp' => $tindakan->bhp->map(fn (TindakanBhp $item): array => [
                    'id' => $item->id,
                    'medicine' => $item->obat?->nama ?? 'Item dihapus',
                    'unit' => $item->obat?->satuan_kecil ?? '—',
                    'quantity' => (float) $item->jumlah,
                ])->values(),
            ],
            'medicines' => $obatList->map(fn (Obat $medicine): array => [
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
            'kode' => ['required', 'string', 'max:30', 'unique:tindakan,kode'],
            'kode_icd9' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'in:medis,lab'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'tarif' => ['sometimes', 'numeric', 'min:0'],
            'tarif_dokter' => ['sometimes', 'numeric', 'min:0'],
            'tarif_asisten' => ['sometimes', 'numeric', 'min:0'],
            'tarif_klinik' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['tarif'] = 0;
        $data['tarif_dokter'] = 0;
        $data['tarif_asisten'] = 0;
        $data['tarif_klinik'] = 0;
        Tindakan::create($data);

        return back()->with('success', 'Tindakan berhasil ditambahkan.');
    }

    public function update(Request $request, Tindakan $tindakan): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:tindakan,kode,{$tindakan->id}"],
            'kode_icd9' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'in:medis,lab'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'tarif' => ['sometimes', 'numeric', 'min:0'],
            'tarif_dokter' => ['sometimes', 'numeric', 'min:0'],
            'tarif_asisten' => ['sometimes', 'numeric', 'min:0'],
            'tarif_klinik' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['tarif'] = 0;
        $data['tarif_dokter'] = 0;
        $data['tarif_asisten'] = 0;
        $data['tarif_klinik'] = 0;
        $tindakan->update($data);

        return back()->with('success', 'Tindakan berhasil diperbarui.');
    }

    public function destroy(Tindakan $tindakan): RedirectResponse
    {
        $tindakan->delete();

        return back()->with('success', 'Tindakan berhasil dihapus.');
    }

    public function storeBhp(Request $request, Tindakan $tindakan): RedirectResponse
    {
        $request->validate([
            'obat_id' => ['required', Rule::exists('obat', 'id')->where('jenis', 'bhp')->where('is_active', true)->whereNull('deleted_at')],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
        ]);

        $tindakan->bhp()->create([
            'obat_id' => $request->obat_id,
            'jumlah' => $request->jumlah,
        ]);

        return back()->with('success', 'BHP berhasil ditambahkan.');
    }

    public function destroyBhp(TindakanBhp $bhp): RedirectResponse
    {
        $bhp->delete();

        return back()->with('success', 'BHP berhasil dihapus.');
    }
}
