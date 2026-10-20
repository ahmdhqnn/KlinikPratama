<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ClinicalTerminology;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\TindakanBhp;
use Illuminate\Http\JsonResponse;
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
            ->paginate(10)
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
            'procedureCatalogAvailable' => ClinicalTerminology::query()->where('code_system', 'icd9cm_procedure')->where('code_type', 'procedure')->where('is_active', true)->exists(),
        ]);
    }

    public function searchTerminology(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $search = trim($data['q']);
        $normalizedCode = preg_match('/^\d{3,5}$/', $search) === 1
            ? substr($search, 0, 2).'.'.substr($search, 2)
            : $search;

        $codes = ClinicalTerminology::query()
            ->where('code_system', 'icd9cm_procedure')
            ->where('code_type', 'procedure')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('code', 'like', '%'.addcslashes($normalizedCode, '\\%_').'%')
                ->orWhere('display', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->orderBy('code')->limit(20)->get(['code', 'display', 'release']);

        return response()->json($codes);
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
                    'unit' => $item->obat?->satuan_kecil ?? 'Belum diisi',
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
            'kode_icd9' => ['nullable', 'string', 'max:20', 'regex:/^\d{2}(?:\.\d{1,3})?$/'],
            'nama' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'in:medis,lab'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Tindakan::create($data);

        return back()->with('success', 'Tindakan berhasil ditambahkan.');
    }

    public function update(Request $request, Tindakan $tindakan): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:tindakan,kode,{$tindakan->id}"],
            'kode_icd9' => ['nullable', 'string', 'max:20', 'regex:/^\d{2}(?:\.\d{1,3})?$/'],
            'nama' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'in:medis,lab'],
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
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
