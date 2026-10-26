<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use App\Models\Poliklinik;
use App\Models\RuangPoli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PoliklinikController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $depotsEnabled = config('clinic.modules.medicine_depots', false);
        $poliklinik = Poliklinik::query()
            ->when($depotsEnabled, fn ($query) => $query->with('depoObat'))
            ->when(! config('clinic.modules.laboratory', false), fn ($query) => $query->where('jenis', '!=', 'lab'))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('master/poliklinik/index', [
            'clinics' => [
                'data' => $poliklinik->getCollection()->map(fn (Poliklinik $clinic): array => [
                    'id' => $clinic->id,
                    'code' => $clinic->kode,
                    'name' => $clinic->nama,
                    'type' => $clinic->jenis,
                    'depotId' => $depotsEnabled ? $clinic->depo_obat_id : null,
                    'depotName' => $depotsEnabled ? $clinic->depoObat?->nama : null,
                    'active' => $clinic->is_active,
                ])->values(),
                'currentPage' => $poliklinik->currentPage(),
                'lastPage' => $poliklinik->lastPage(),
                'perPage' => $poliklinik->perPage(),
                'total' => $poliklinik->total(),
                'from' => $poliklinik->firstItem(),
                'to' => $poliklinik->lastItem(),
                'previousUrl' => $poliklinik->previousPageUrl(),
                'nextUrl' => $poliklinik->nextPageUrl(),
            ],
            'filters' => ['search' => $filters['search'] ?? ''],
            'depotsEnabled' => $depotsEnabled,
            'depots' => $depotsEnabled ? DepoObat::where('is_active', true)->orderBy('nama')->get()->map(fn (DepoObat $depot): array => [
                'id' => $depot->id,
                'name' => $depot->nama,
                'code' => $depot->kode,
            ])->values() : [],
            'clinicTypes' => [
                ['value' => 'umum', 'label' => 'Umum'],
                ['value' => 'gigi', 'label' => 'Gigi'],
                ['value' => 'kia', 'label' => 'KIA / Kebidanan'],
                ['value' => 'mata', 'label' => 'Mata'],
                ['value' => 'tht', 'label' => 'THT'],
                ['value' => 'kulit', 'label' => 'Kulit'],
                ...(config('clinic.modules.laboratory', false) ? [['value' => 'lab', 'label' => 'Laboratorium']] : []),
                ['value' => 'radiologi', 'label' => 'Radiologi'],
                ['value' => 'ugd', 'label' => 'UGD'],
            ],
        ]);
    }

    public function show(Poliklinik $poliklinik): Response
    {
        $poliklinik->load('ruangPoli');

        return Inertia::render('master/poliklinik/show', [
            'clinic' => [
                'id' => $poliklinik->id,
                'code' => $poliklinik->kode,
                'name' => $poliklinik->nama,
                'rooms' => $poliklinik->ruangPoli->map(fn (RuangPoli $room): array => [
                    'id' => $room->id,
                    'name' => $room->nama,
                    'active' => $room->is_active,
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:poliklinik,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'string', Rule::notIn(config('clinic.modules.laboratory', false) ? [] : ['lab'])],
            'depo_obat_id' => config('clinic.modules.medicine_depots', false) ? ['nullable', 'exists:depo_obat,id'] : ['exclude'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Poliklinik::create($data);

        return back()->with('success', 'Poliklinik berhasil ditambahkan.');
    }

    public function update(Request $request, Poliklinik $poliklinik): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', "unique:poliklinik,kode,{$poliklinik->id}"],
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'string', Rule::notIn(config('clinic.modules.laboratory', false) ? [] : ['lab'])],
            'depo_obat_id' => config('clinic.modules.medicine_depots', false) ? ['nullable', 'exists:depo_obat,id'] : ['exclude'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $poliklinik->update($data);

        return back()->with('success', 'Poliklinik berhasil diperbarui.');
    }

    public function destroy(Poliklinik $poliklinik): RedirectResponse
    {
        $poliklinik->delete();

        return back()->with('success', 'Poliklinik berhasil dihapus.');
    }

    public function storeRuang(Request $request, Poliklinik $poliklinik): RedirectResponse
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:100'],
        ]);

        $poliklinik->ruangPoli()->create([
            'nama' => $request->nama,
            'is_active' => true,
        ]);

        return back()->with('success', 'Ruang poli berhasil ditambahkan.');
    }

    public function destroyRuang(RuangPoli $ruang): RedirectResponse
    {
        $ruang->delete();

        return back()->with('success', 'Ruang poli berhasil dihapus.');
    }
}
