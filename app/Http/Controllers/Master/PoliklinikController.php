<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:umum,gigi'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);
        $poliklinik = Poliklinik::query()
            ->whereIn('jenis', ['umum', 'gigi'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'aktif'))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        $clinicIds = Poliklinik::query()->whereIn('jenis', ['umum', 'gigi'])->pluck('id');

        return Inertia::render('master/poliklinik/index', [
            'stats' => [
                'total' => $clinicIds->count(),
                'active' => Poliklinik::query()->whereIn('jenis', ['umum', 'gigi'])->where('is_active', true)->count(),
                'totalRooms' => RuangPoli::query()->whereIn('poliklinik_id', $clinicIds)->count(),
                'activeRooms' => RuangPoli::query()->whereIn('poliklinik_id', $clinicIds)->where('is_active', true)->count(),
            ],
            'clinics' => [
                'data' => $poliklinik->getCollection()->map(fn (Poliklinik $clinic): array => [
                    'id' => $clinic->id,
                    'code' => $clinic->kode,
                    'name' => $clinic->nama,
                    'type' => $clinic->jenis,
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
            'filters' => ['search' => $filters['search'] ?? '', 'jenis' => $filters['jenis'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function show(Poliklinik $poliklinik): Response
    {
        abort_unless(in_array($poliklinik->jenis, ['umum', 'gigi'], true), 404);
        $poliklinik->load(['ruangPoli' => fn ($query) => $query->orderBy('nama')]);

        return Inertia::render('master/poliklinik/show', [
            'clinic' => [
                'id' => $poliklinik->id,
                'code' => $poliklinik->kode,
                'name' => $poliklinik->nama,
                'rooms' => $poliklinik->ruangPoli->map(fn (RuangPoli $room): array => [
                    'id' => $room->id,
                    'code' => $room->kode,
                    'name' => $room->nama,
                    'satusehatLocationId' => $room->satusehat_location_id,
                    'physicalTypeCode' => $room->physical_type_code ?? 'ro',
                    'active' => $room->is_active,
                ])->values(),
            ],
        ]);
    }

    public function storeRuang(Request $request, Poliklinik $poliklinik): RedirectResponse
    {
        abort_unless(in_array($poliklinik->jenis, ['umum', 'gigi'], true), 404);
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:100', Rule::unique('ruang_poli')->where('poliklinik_id', $poliklinik->id)],
            'satusehat_location_id' => ['nullable', 'string', 'max:64'],
            'physical_type_code' => ['nullable', 'string', 'max:16'],
        ]);

        $poliklinik->ruangPoli()->create([
            'kode' => $data['kode'] ?? null,
            'nama' => $data['nama'],
            'satusehat_location_id' => $data['satusehat_location_id'] ?? null,
            'physical_type_code' => $data['physical_type_code'] ?? 'ro',
            'is_active' => true,
        ]);

        return back()->with('success', 'Ruang poli berhasil ditambahkan.');
    }

    public function updateRuang(Request $request, RuangPoli $ruang): RedirectResponse
    {
        abort_unless(in_array($ruang->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:100', Rule::unique('ruang_poli')->where('poliklinik_id', $ruang->poliklinik_id)->ignore($ruang)],
            'satusehat_location_id' => ['nullable', 'string', 'max:64'],
            'physical_type_code' => ['nullable', 'string', 'max:16'],
            'is_active' => ['required', 'boolean'],
        ]);
        $ruang->update($data);

        return back()->with('success', 'Ruang poli berhasil diperbarui.');
    }
}
