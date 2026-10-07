<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\AsuransiHarga;
use App\Models\Obat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AsuransiController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:umum,bpjs,perusahaan'],
        ]);
        $insurances = Asuransi::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($subquery) use ($search): void {
                $subquery->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('master/asuransi/index', [
            'insurances' => [
                'data' => $insurances->getCollection()->map(fn (Asuransi $insurance): array => [
                    'id' => $insurance->id,
                    'code' => $insurance->kode,
                    'name' => $insurance->nama,
                    'type' => $insurance->jenis,
                    'address' => $insurance->alamat,
                    'phone' => $insurance->telepon,
                    'notes' => $insurance->catatan,
                    'active' => $insurance->is_active,
                ])->values(),
                'currentPage' => $insurances->currentPage(),
                'lastPage' => $insurances->lastPage(),
                'perPage' => $insurances->perPage(),
                'total' => $insurances->total(),
                'from' => $insurances->firstItem(),
                'to' => $insurances->lastItem(),
                'previousUrl' => $insurances->previousPageUrl(),
                'nextUrl' => $insurances->nextPageUrl(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['jenis'] ?? '',
            ],
            'types' => [
                ['value' => 'umum', 'label' => 'Umum (Mandiri)'],
                ['value' => 'bpjs', 'label' => 'BPJS Kesehatan'],
                ['value' => 'perusahaan', 'label' => 'Perusahaan / Korporasi'],
            ],
        ]);
    }

    public function show(Asuransi $asuransi): Response
    {
        $asuransi->load('asuransiHarga.obat');
        $medicines = Obat::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('master/asuransi/show', [
            'insurance' => [
                'id' => $asuransi->id,
                'code' => $asuransi->kode,
                'name' => $asuransi->nama,
                'prices' => $asuransi->asuransiHarga->map(fn (AsuransiHarga $price): array => [
                    'id' => $price->id,
                    'medicineId' => $price->obat_id,
                    'medicine' => $price->obat?->nama ?? 'Obat dihapus',
                    'regularPrice' => (float) ($price->obat?->harga_jual ?? 0),
                    'specialPrice' => (float) $price->harga_khusus,
                ])->values(),
            ],
            'medicines' => $medicines->map(fn (Obat $medicine): array => [
                'id' => $medicine->id,
                'name' => $medicine->nama,
                'regularPrice' => (float) $medicine->harga_jual,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:asuransi,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'in:umum,bpjs,perusahaan'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        Asuransi::create($data);

        return back()->with('success', 'Asuransi berhasil ditambahkan.');
    }

    public function update(Request $request, Asuransi $asuransi): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:asuransi,kode,{$asuransi->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'jenis' => ['required', 'in:umum,bpjs,perusahaan'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $asuransi->update($data);

        return back()->with('success', 'Asuransi berhasil diperbarui.');
    }

    public function destroy(Asuransi $asuransi): RedirectResponse
    {
        $asuransi->delete();

        return back()->with('success', 'Asuransi berhasil dihapus.');
    }

    public function storeHarga(Request $request, Asuransi $asuransi): RedirectResponse
    {
        $data = $request->validate([
            'obat_id' => ['required', 'exists:obat,id'],
            'harga_khusus' => ['required', 'numeric', 'min:0'],
        ]);

        $asuransi->asuransiHarga()->updateOrCreate(
            ['obat_id' => $data['obat_id']],
            ['harga_khusus' => $data['harga_khusus']]
        );

        return back()->with('success', 'Harga khusus berhasil disimpan.');
    }

    public function destroyHarga(AsuransiHarga $harga): RedirectResponse
    {
        $harga->delete();

        return back()->with('success', 'Harga khusus berhasil dihapus.');
    }
}
