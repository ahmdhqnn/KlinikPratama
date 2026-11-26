<?php

namespace App\Http\Controllers\Stok;

use App\ClinicalAuditRecorder;
use App\Exports\OperationalReportExport;
use App\FarmasiClinicResolver;
use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Poliklinik;
use App\Models\StokMutasi;
use App\PersediaanRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PersediaanController extends Controller
{
    public function index(Request $request, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:obat,bhp'],
            'stok' => ['nullable', 'in:minimum,kedaluwarsa'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $depoId = $this->resolveDepoId($request, $pharmacyClinics, isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null);
        $baseQuery = $this->inventoryQuery($filters, $depoId);

        $medicines = (clone $baseQuery)
            ->orderBy('nama')->orderBy('id')->paginate(10)->withQueryString()
            ->through(fn (Obat $medicine): array => [
                'id' => $medicine->id, 'code' => $medicine->kode, 'name' => $medicine->nama,
                'type' => $medicine->jenis,
                'stock' => $depoId !== null ? (float) $medicine->depo_stock : (float) $medicine->stok,
                'usable' => (float) $medicine->usable_stock,
                'minimum' => (float) $medicine->stok_minimum, 'unit' => $medicine->satuan_kecil,
                'url' => route('stok.persediaan.show', $medicine),
            ]);

        return Inertia::render('stok/persediaan/index', [
            'medicines' => $this->pageData($medicines),
            'filters' => $filters,
            'clinics' => $request->user()->isAdmin()
                ? Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])->map(fn (Poliklinik $clinic): array => ['id' => $clinic->id, 'name' => $clinic->nama])->values()
                : [],
            'stats' => [
                'medicines' => (clone $baseQuery)->where('jenis', 'obat')->count(),
                'supplies' => (clone $baseQuery)->where('jenis', 'bhp')->count(),
                'lowStock' => (clone $baseQuery)->where('is_active', true)->lowUsableStock($depoId)->count(),
            ],
        ]);
    }

    public function export(Request $request, FarmasiClinicResolver $pharmacyClinics, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:obat,bhp'],
            'stok' => ['nullable', 'in:minimum,kedaluwarsa'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $depoId = $this->resolveDepoId($request, $pharmacyClinics, isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : null);
        $query = $this->inventoryQuery($filters, $depoId)
            ->withMin(['batches as next_expiry' => fn (Builder $batch) => $batch->usable()->forDepo($depoId)], 'expired_at')
            ->orderBy('nama')->orderBy('id');

        $audit->record($request, 'inventory.export', metadata: ['depo_id' => $depoId, ...$filters]);

        return Excel::download(new OperationalReportExport(
            $query,
            ['Kode', 'Nama item', 'Jenis', 'Satuan', 'Stok fisik', 'Stok layak pakai', 'Stok minimum', 'Status', 'Kedaluwarsa terdekat'],
            fn (Obat $item): array => [
                $item->kode, $item->nama, strtoupper($item->jenis), $item->satuan_kecil,
                $depoId !== null ? (float) $item->depo_stock : (float) $item->stok,
                (float) $item->usable_stock, (float) $item->stok_minimum,
                (float) $item->usable_stock <= 0 ? 'Habis' : ((float) $item->usable_stock <= (float) $item->stok_minimum ? 'Kritis' : 'Aman'),
                $item->next_expiry,
            ],
        ), 'persediaan-farmasi-'.today()->toDateString().'.xlsx');
    }

    public function show(Request $request, Obat $obat, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $depoId = $this->resolveDepoId($request, $pharmacyClinics);
        $filters = $request->validate([
            'batch_status' => ['nullable', 'in:tersedia,karantina,kedaluwarsa'],
            'jenis_mutasi' => ['nullable', 'in:masuk,keluar,opname,rusak,pemusnahan,retur,verifikasi_masuk,verifikasi_keluar'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $batchesQuery = $obat->batches()->forDepo($depoId);
        $allDepoBatches = (clone $batchesQuery)->get();
        $filteredBatches = (clone $batchesQuery)
            ->when($filters['batch_status'] ?? null, function (Builder $query, string $status): void {
                if ($status === 'kedaluwarsa') {
                    $query->whereDate('expired_at', '<=', today());
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q->where('nomor_batch', 'like', "%{$search}%")->orWhere('referensi', 'like', "%{$search}%")))
            ->orderBy('expired_at')->orderBy('id')->get();

        $movements = $obat->stokMutasi()->with(['batch', 'actor'])
            ->when($depoId !== null, fn (Builder $query) => $query->where('depo_id', $depoId))
            ->when($filters['jenis_mutasi'] ?? null, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->where('keterangan', 'like', "%{$search}%")
                ->orWhereHas('batch', fn (Builder $b) => $b->where('nomor_batch', 'like', "%{$search}%"))))
            ->latest('id')->paginate(10)->withQueryString()
            ->through(fn (StokMutasi $movement): array => [
                'id' => $movement->id, 'date' => $movement->created_at->format('d/m/Y H:i'), 'type' => $movement->jenis,
                'batch' => $movement->batch?->nomor_batch, 'quantity' => (float) $movement->jumlah,
                'before' => (float) $movement->stok_sebelum, 'after' => (float) $movement->stok_sesudah,
                'actor' => $movement->actor?->name, 'reason' => request()->user()->role === 'admin' && in_array($movement->referensi_type, ['Farmasi', 'FarmasiMandiri', 'TindakanBhp'], true) ? 'Mutasi pelayanan tercatat' : $movement->keterangan,
                'canReturn' => request()->user()->role === 'farmasi' && $movement->jenis === 'keluar' && in_array($movement->referensi_type, ['Farmasi', 'FarmasiMandiri'], true),
            ]);

        return Inertia::render('stok/persediaan/show', [
            'medicine' => [
                'id' => $obat->id, 'name' => $obat->nama, 'code' => $obat->kode,
                'stock' => $depoId !== null ? (float) $allDepoBatches->sum('stok') : (float) $obat->stok,
                'unit' => $obat->satuan_kecil,
            ],
            'filters' => [
                'batch_status' => $filters['batch_status'] ?? '',
                'jenis_mutasi' => $filters['jenis_mutasi'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
            'batches' => $filteredBatches->map(fn (ObatBatch $batch): array => [
                'id' => $batch->id, 'number' => $batch->nomor_batch, 'expiry' => $batch->expired_at?->toDateString(),
                'stock' => (float) $batch->stok, 'status' => $batch->status, 'source' => $batch->sumber,
                'reference' => $batch->referensi,
                'expired' => $batch->expired_at?->lte(today()) ?? false,
                'nearExpiry' => $batch->expired_at?->between(today()->addDay(), today()->addDays(90)) ?? false,
            ]),
            'batchBalance' => (float) $allDepoBatches->sum('stok'),
            'movements' => $this->pageData($movements), 'today' => today()->toDateString(),
        ]);
    }

    public function receive(Request $request, Obat $obat, PersediaanRecorder $inventory, FarmasiClinicResolver $pharmacyClinics): RedirectResponse
    {
        $data = $request->validate([
            ...$this->batchRules(),
            'sumber' => ['required', 'in:distribusi,hibah,saldo_awal,lainnya'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
        $depoId = $this->resolveDepoId($request, $pharmacyClinics, isset($data['poliklinik_id']) ? (int) $data['poliklinik_id'] : null);
        unset($data['poliklinik_id']);
        $data['depo_id'] = $depoId;
        $inventory->receive($obat->id, $data, $request->user());

        return back()->with('success', 'Penerimaan batch tercatat pada kartu stok.');
    }

    public function adjust(Request $request, ObatBatch $batch, PersediaanRecorder $inventory, FarmasiClinicResolver $pharmacyClinics): RedirectResponse
    {
        $pharmacyClinics->authorizeBatch($request->user(), $batch);
        $data = $request->validate([
            'jenis' => ['required', 'in:opname,rusak,pemusnahan'],
            'jumlah' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $inventory->adjust($batch, $data, $request->user());

        return back()->with('success', 'Penyesuaian batch dan alasan tercatat.');
    }

    public function reconcile(Request $request, ObatBatch $batch, PersediaanRecorder $inventory, FarmasiClinicResolver $pharmacyClinics): RedirectResponse
    {
        $pharmacyClinics->authorizeBatch($request->user(), $batch);
        $data = $request->validate($this->batchRules());
        $inventory->reconcile($batch, $data, $request->user());

        return back()->with('success', 'Saldo karantina telah diverifikasi ke batch fisik.');
    }

    public function returnDispensing(Request $request, StokMutasi $mutasi, PersediaanRecorder $inventory, FarmasiClinicResolver $pharmacyClinics): RedirectResponse
    {
        abort_unless($request->user()->role === 'farmasi', 403);
        $depoId = $this->resolveDepoId($request, $pharmacyClinics);
        if ($depoId !== null && $mutasi->depo_id !== null && (int) $mutasi->depo_id !== $depoId) {
            abort(404);
        }
        $data = $request->validate(['jumlah' => ['required', 'integer', 'min:1', 'max:99999999'], 'alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $inventory->returnDispensing($mutasi, (float) $data['jumlah'], $data['alasan'], $request->user());

        return back()->with('success', 'Obat retur masuk karantina untuk pemeriksaan farmasi.');
    }

    private function resolveDepoId(Request $request, FarmasiClinicResolver $pharmacyClinics, ?int $adminClinicId = null): ?int
    {
        if ($request->user()->role === 'farmasi') {
            $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());

            return $clinicId !== null ? $pharmacyClinics->depoId($request->user()) : null;
        }

        if ($adminClinicId !== null) {
            return Poliklinik::query()->whereKey($adminClinicId)->value('depo_obat_id');
        }

        return null;
    }

    private function inventoryQuery(array $filters, ?int $depoId): Builder
    {
        return Obat::withSum(['batches as usable_stock' => fn (Builder $query) => $query->usable()->forDepo($depoId)], 'stok')
            ->withSum(['batches as depo_stock' => fn (Builder $query) => $query->forDepo($depoId)], 'stok')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->when(($filters['stok'] ?? null) === 'minimum', fn (Builder $query) => $query->where('is_active', true)->lowUsableStock($depoId))
            ->when(($filters['stok'] ?? null) === 'kedaluwarsa', fn (Builder $query) => $query->where('is_active', true)
                ->whereHas('batches', fn (Builder $batch) => $batch->usable()->forDepo($depoId)->whereBetween('expired_at', [today()->addDay()->toDateString(), today()->addDays(90)->toDateString()])));
    }

    private function batchRules(): array
    {
        return [
            'nomor_batch' => ['required', 'string', 'max:100'],
            'expired_at' => ['required', 'date_format:Y-m-d', 'after:today'],
            'jumlah' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'referensi' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
