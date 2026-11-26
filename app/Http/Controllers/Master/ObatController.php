<?php

namespace App\Http\Controllers\Master;

use App\Exports\ObatExport;
use App\Exports\ObatTemplateExport;
use App\FarmasiClinicResolver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Stok\PersediaanController;
use App\Imports\ObatImport;
use App\Models\Obat;
use App\Models\StokMutasi;
use App\PersediaanRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ObatController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:obat,bhp'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'stok_rendah' => ['nullable', 'boolean'],
        ]);
        $obat = Obat::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($subquery) => $subquery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")
                ->orWhere('kode_kfa', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'aktif'))
            ->when(filter_var($filters['stok_rendah'] ?? false, FILTER_VALIDATE_BOOL), fn ($query) => $query->whereColumn('stok', '<=', 'stok_minimum'))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('master/obat/index', [
            'stats' => [
                'total' => Obat::count(),
                'obat' => Obat::where('jenis', 'obat')->count(),
                'bhp' => Obat::where('jenis', 'bhp')->count(),
                'lowStock' => Obat::where('is_active', true)->whereColumn('stok', '<=', 'stok_minimum')->count(),
            ],
            'medicines' => [
                'data' => $obat->getCollection()->map(fn (Obat $medicine): array => [
                    'id' => $medicine->id,
                    'code' => $medicine->kode,
                    'kfaCode' => $medicine->kode_kfa,
                    'kfaPoaCode' => $medicine->kode_kfa_poa,
                    'kfaPovCode' => $medicine->kode_kfa_pov,
                    'kfaName' => $medicine->nama_kfa,
                    'dosageFormCode' => $medicine->kode_bentuk_sediaan,
                    'dosageFormName' => $medicine->nama_bentuk_sediaan,
                    'name' => $medicine->nama,
                    'largeUnit' => $medicine->satuan_besar,
                    'smallUnit' => $medicine->satuan_kecil,
                    'unitConversion' => (float) $medicine->konversi_satuan,
                    'stock' => (float) $medicine->stok,
                    'minimumStock' => (float) $medicine->stok_minimum,
                    'indication' => $medicine->indikasi,
                    'ingredients' => $medicine->kandungan,
                    'type' => $medicine->jenis,
                    'active' => $medicine->is_active,
                    'directDispensingAllowed' => $medicine->boleh_diberikan_langsung,
                    'isHighAlert' => (bool) $medicine->is_high_alert,
                    'isLasa' => (bool) $medicine->is_lasa,
                    'defaultRoute' => $medicine->rute_default,
                ])->values(),
                'currentPage' => $obat->currentPage(),
                'lastPage' => $obat->lastPage(),
                'perPage' => $obat->perPage(),
                'total' => $obat->total(),
                'from' => $obat->firstItem(),
                'to' => $obat->lastItem(),
                'previousUrl' => $obat->previousPageUrl(),
                'nextUrl' => $obat->nextPageUrl(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['jenis'] ?? '',
                'status' => $filters['status'] ?? '',
                'lowStock' => filter_var($filters['stok_rendah'] ?? false, FILTER_VALIDATE_BOOL),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:obat,kode'],
            'kode_kfa' => ['nullable', 'string', 'max:30'],
            'kode_kfa_poa' => ['nullable', 'string', 'max:32'],
            'kode_kfa_pov' => ['nullable', 'string', 'max:32'],
            'nama_kfa' => ['nullable', 'string', 'max:255'],
            'kode_bentuk_sediaan' => ['nullable', 'string', 'max:32'],
            'nama_bentuk_sediaan' => ['nullable', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:200'],
            'satuan_besar' => ['nullable', 'string', 'max:50'],
            'satuan_kecil' => ['nullable', 'string', 'max:50'],
            'konversi_satuan' => ['required', 'numeric', 'min:1'],
            'indikasi' => ['nullable', 'string'],
            'kandungan' => ['nullable', 'string'],
            'stok' => ['sometimes', 'integer', 'in:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'jenis' => ['required', 'in:obat,bhp'],
            'is_active' => ['boolean'],
            'boleh_diberikan_langsung' => ['boolean'],
            'is_high_alert' => ['sometimes', 'boolean'],
            'is_lasa' => ['sometimes', 'boolean'],
            'rute_default' => ['nullable', 'in:oral,topikal,tetes_mata,tetes_telinga,inhalasi,sublingual,injeksi,lainnya'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['boleh_diberikan_langsung'] = $request->boolean('boleh_diberikan_langsung') && $data['jenis'] === 'obat';
        $data['is_high_alert'] = $request->boolean('is_high_alert') && $data['jenis'] === 'obat';
        $data['is_lasa'] = $request->boolean('is_lasa') && $data['jenis'] === 'obat';
        Obat::create($data);

        return back()->with('success', 'Item obat/BHP berhasil ditambahkan.');
    }

    public function update(Request $request, Obat $obat): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:obat,kode,{$obat->id}"],
            'kode_kfa' => ['nullable', 'string', 'max:30'],
            'kode_kfa_poa' => ['nullable', 'string', 'max:32'],
            'kode_kfa_pov' => ['nullable', 'string', 'max:32'],
            'nama_kfa' => ['nullable', 'string', 'max:255'],
            'kode_bentuk_sediaan' => ['nullable', 'string', 'max:32'],
            'nama_bentuk_sediaan' => ['nullable', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:200'],
            'satuan_besar' => ['nullable', 'string', 'max:50'],
            'satuan_kecil' => ['nullable', 'string', 'max:50'],
            'konversi_satuan' => ['required', 'numeric', 'min:1'],
            'indikasi' => ['nullable', 'string'],
            'kandungan' => ['nullable', 'string'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'jenis' => ['required', 'in:obat,bhp'],
            'is_active' => ['boolean'],
            'boleh_diberikan_langsung' => ['boolean'],
            'is_high_alert' => ['sometimes', 'boolean'],
            'is_lasa' => ['sometimes', 'boolean'],
            'rute_default' => ['nullable', 'in:oral,topikal,tetes_mata,tetes_telinga,inhalasi,sublingual,injeksi,lainnya'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['boleh_diberikan_langsung'] = $request->boolean('boleh_diberikan_langsung') && $data['jenis'] === 'obat';
        $data['is_high_alert'] = $request->boolean('is_high_alert') && $data['jenis'] === 'obat';
        $data['is_lasa'] = $request->boolean('is_lasa') && $data['jenis'] === 'obat';
        $obat->update($data);

        return back()->with('success', 'Item obat/BHP berhasil diperbarui.');
    }

    public function destroy(Obat $obat): RedirectResponse
    {
        $obat->delete();

        return back()->with('success', 'Item obat/BHP berhasil dihapus.');
    }

    public function stok(Obat $obat): Response
    {
        $mutasi = StokMutasi::where('obat_id', $obat->id)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('master/obat/stok', [
            'medicine' => [
                'id' => $obat->id,
                'code' => $obat->kode,
                'name' => $obat->nama,
                'stock' => (float) $obat->stok,
                'minimumStock' => (float) $obat->stok_minimum,
                'unit' => $obat->satuan_kecil,
            ],
            'movements' => [
                'data' => $mutasi->getCollection()->map(fn (StokMutasi $movement): array => [
                    'id' => $movement->id,
                    'date' => $movement->created_at?->toIso8601String(),
                    'type' => $movement->jenis,
                    'quantity' => (float) $movement->jumlah,
                    'stockBefore' => (float) $movement->stok_sebelum,
                    'stockAfter' => (float) $movement->stok_sesudah,
                    'description' => request()->user()?->role === 'admin' && in_array($movement->referensi_type, ['Farmasi', 'FarmasiMandiri', 'TindakanBhp'], true)
                        ? 'Mutasi pelayanan tercatat'
                        : $movement->keterangan,
                ])->values(),
                'currentPage' => $mutasi->currentPage(),
                'lastPage' => $mutasi->lastPage(),
                'perPage' => $mutasi->perPage(),
                'total' => $mutasi->total(),
                'from' => $mutasi->firstItem(),
                'to' => $mutasi->lastItem(),
                'previousUrl' => $mutasi->previousPageUrl(),
                'nextUrl' => $mutasi->nextPageUrl(),
            ],
        ]);
    }

    public function tambahStok(Request $request, Obat $obat): RedirectResponse
    {
        return app(PersediaanController::class)->receive(
            $request,
            $obat,
            app(PersediaanRecorder::class),
            app(FarmasiClinicResolver::class),
        );
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new ObatExport, 'data-obat-'.date('Y-m-d').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            DB::transaction(fn () => Excel::import(new ObatImport, $request->file('file')));

            return back()->with('success', 'Data obat dan BHP berhasil diimpor.');
        } catch (ValidationException $e) {
            $firstFailure = $e->failures()[0] ?? null;
            $message = $firstFailure
                ? 'Baris '.$firstFailure->row().': '.($firstFailure->errors()[0] ?? 'Data tidak sesuai format.')
                : 'Periksa format dan kelengkapan baris impor obat.';

            return back()->with('error', 'Gagal mengimpor data: '.$message);
        } catch (\Throwable) {
            return back()->with('error', 'Gagal mengimpor data: periksa keunikan kode obat dan kesesuaian format template.');
        }
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ObatTemplateExport, 'template-import-obat.xlsx');
    }
}
