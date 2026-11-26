<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\StokMutasi;
use App\PersediaanRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersediaanController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'jenis' => ['nullable', 'in:obat,bhp'], 'stok' => ['nullable', 'in:minimum,kedaluwarsa']]);
        $medicines = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when(($filters['stok'] ?? null) === 'minimum', fn ($query) => $query->where('is_active', true)->lowUsableStock())
            ->when(($filters['stok'] ?? null) === 'kedaluwarsa', fn ($query) => $query->where('is_active', true)
                ->whereHas('batches', fn ($batch) => $batch->usable()->whereBetween('expired_at', [today()->addDay()->toDateString(), today()->addDays(90)->toDateString()])))
            ->orderBy('nama')->orderBy('id')->paginate(10)->withQueryString()
            ->through(fn (Obat $medicine): array => [
                'id' => $medicine->id, 'code' => $medicine->kode, 'name' => $medicine->nama,
                'type' => $medicine->jenis, 'stock' => (float) $medicine->stok, 'usable' => (float) $medicine->usable_stock,
                'minimum' => (float) $medicine->stok_minimum, 'unit' => $medicine->satuan_kecil,
                'url' => route('stok.persediaan.show', $medicine),
            ]);

        $summary = Obat::query()->withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')->get(['id', 'jenis', 'stok_minimum', 'is_active']);

        return Inertia::render('stok/persediaan/index', [
            'medicines' => $this->pageData($medicines),
            'filters' => $filters,
            'stats' => [
                'medicines' => $summary->where('jenis', 'obat')->count(),
                'supplies' => $summary->where('jenis', 'bhp')->count(),
                'lowStock' => $summary->filter(fn (Obat $medicine): bool => $medicine->is_active && (float) $medicine->usable_stock <= (float) $medicine->stok_minimum)->count(),
            ],
        ]);
    }

    public function show(Obat $obat): Response
    {
        $obat->load('batches');
        $movements = $obat->stokMutasi()->with(['batch', 'actor'])->latest('id')->paginate(10)->withQueryString()
            ->through(fn (StokMutasi $movement): array => [
                'id' => $movement->id, 'date' => $movement->created_at->format('d/m/Y H:i'), 'type' => $movement->jenis,
                'batch' => $movement->batch?->nomor_batch, 'quantity' => (float) $movement->jumlah,
                'before' => (float) $movement->stok_sebelum, 'after' => (float) $movement->stok_sesudah,
                'actor' => $movement->actor?->name, 'reason' => request()->user()->role === 'admin' && in_array($movement->referensi_type, ['Farmasi', 'FarmasiMandiri', 'TindakanBhp'], true) ? 'Mutasi pelayanan tercatat' : $movement->keterangan,
                'canReturn' => request()->user()->role === 'farmasi' && $movement->jenis === 'keluar' && in_array($movement->referensi_type, ['Farmasi', 'FarmasiMandiri'], true),
            ]);

        return Inertia::render('stok/persediaan/show', [
            'medicine' => ['id' => $obat->id, 'name' => $obat->nama, 'code' => $obat->kode, 'stock' => (float) $obat->stok, 'unit' => $obat->satuan_kecil],
            'batches' => $obat->batches->map(fn (ObatBatch $batch): array => [
                'id' => $batch->id, 'number' => $batch->nomor_batch, 'expiry' => $batch->expired_at?->toDateString(),
                'stock' => (float) $batch->stok, 'status' => $batch->status, 'source' => $batch->sumber,
                'reference' => $batch->referensi,
                'expired' => $batch->expired_at?->lte(today()) ?? false,
                'nearExpiry' => $batch->expired_at?->between(today()->addDay(), today()->addDays(90)) ?? false,
            ]),
            'batchBalance' => (float) $obat->batches->sum('stok'),
            'movements' => $this->pageData($movements), 'today' => today()->toDateString(),
        ]);
    }

    public function receive(Request $request, Obat $obat, PersediaanRecorder $inventory): RedirectResponse
    {
        $data = $request->validate([
            ...$this->batchRules(),
            'sumber' => ['required', 'in:distribusi,hibah,saldo_awal,lainnya'],
        ]);
        $inventory->receive($obat->id, $data, $request->user());

        return back()->with('success', 'Penerimaan batch tercatat pada kartu stok.');
    }

    public function adjust(Request $request, ObatBatch $batch, PersediaanRecorder $inventory): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:opname,rusak,pemusnahan'],
            'jumlah' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $inventory->adjust($batch, $data, $request->user());

        return back()->with('success', 'Penyesuaian batch dan alasan tercatat.');
    }

    public function reconcile(Request $request, ObatBatch $batch, PersediaanRecorder $inventory): RedirectResponse
    {
        $data = $request->validate($this->batchRules());
        $inventory->reconcile($batch, $data, $request->user());

        return back()->with('success', 'Saldo karantina telah diverifikasi ke batch fisik.');
    }

    public function returnDispensing(Request $request, StokMutasi $mutasi, PersediaanRecorder $inventory): RedirectResponse
    {
        abort_unless($request->user()->role === 'farmasi', 403);
        $data = $request->validate(['jumlah' => ['required', 'integer', 'min:1', 'max:99999999'], 'alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $inventory->returnDispensing($mutasi, (float) $data['jumlah'], $data['alasan'], $request->user());

        return back()->with('success', 'Obat retur masuk karantina untuk pemeriksaan farmasi.');
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
