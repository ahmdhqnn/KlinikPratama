<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\StokMutasi;
use App\PersediaanRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersediaanController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'jenis' => ['nullable', 'in:obat,bhp']]);
        $medicines = Obat::withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->orderBy('nama')->paginate(20)->withQueryString()
            ->through(fn (Obat $medicine): array => [
                'id' => $medicine->id, 'code' => $medicine->kode, 'name' => $medicine->nama,
                'type' => $medicine->jenis, 'stock' => (float) $medicine->stok, 'usable' => (float) $medicine->usable_stock,
                'minimum' => (float) $medicine->stok_minimum, 'unit' => $medicine->satuan_kecil,
                'url' => route('stok.persediaan.show', $medicine),
            ]);

        return Inertia::render('stok/persediaan/index', ['medicines' => $this->pageData($medicines), 'filters' => $filters]);
    }

    public function show(Obat $obat): Response
    {
        $obat->load('batches.depo');
        $movements = $obat->stokMutasi()->with(['batch', 'actor'])->latest('id')->paginate(25)->withQueryString()
            ->through(fn (StokMutasi $movement): array => [
                'id' => $movement->id, 'date' => $movement->created_at->format('d/m/Y H:i'), 'type' => $movement->jenis,
                'batch' => $movement->batch?->nomor_batch, 'quantity' => (float) $movement->jumlah,
                'before' => (float) $movement->stok_sebelum, 'after' => (float) $movement->stok_sesudah,
                'actor' => $movement->actor?->name, 'reason' => $movement->keterangan,
                'canReturn' => $movement->jenis === 'keluar' && $movement->referensi_type === 'Farmasi',
            ]);

        return Inertia::render('stok/persediaan/show', [
            'medicine' => ['id' => $obat->id, 'name' => $obat->nama, 'code' => $obat->kode, 'stock' => (float) $obat->stok, 'unit' => $obat->satuan_kecil, 'purchasePrice' => (float) $obat->harga_beli],
            'batches' => $obat->batches->map(fn (ObatBatch $batch): array => [
                'id' => $batch->id, 'number' => $batch->nomor_batch, 'expiry' => $batch->expired_at?->toDateString(),
                'stock' => (float) $batch->stok, 'status' => $batch->status, 'source' => $batch->sumber,
                'depot' => $batch->depo?->nama, 'reference' => $batch->referensi,
                'expired' => $batch->expired_at?->lte(today()) ?? false,
                'nearExpiry' => $batch->expired_at?->between(today()->addDay(), today()->addDays(90)) ?? false,
            ]),
            'depots' => DepoObat::where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
            'movements' => $this->pageData($movements), 'today' => today()->toDateString(),
        ]);
    }

    public function receive(Request $request, Obat $obat, PersediaanRecorder $inventory): RedirectResponse
    {
        $data = $request->validate([
            ...$this->batchRules(), 'harga_beli' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'sumber' => ['required', 'in:pengadaan,hibah'],
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

    public function transfer(Request $request, ObatBatch $batch, PersediaanRecorder $inventory): RedirectResponse
    {
        $data = $request->validate([
            'depo_id' => $this->depotRules(), 'jumlah' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $inventory->transfer($batch, $data, $request->user());

        return back()->with('success', 'Mutasi antar depo tercatat; saldo total tetap sama.');
    }

    public function returnDispensing(Request $request, StokMutasi $mutasi, PersediaanRecorder $inventory): RedirectResponse
    {
        $data = $request->validate(['jumlah' => ['required', 'integer', 'min:1', 'max:99999999'], 'alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $inventory->returnDispensing($mutasi, (float) $data['jumlah'], $data['alasan'], $request->user());

        return back()->with('success', 'Obat retur masuk karantina untuk pemeriksaan farmasi.');
    }

    private function depotRules(): array
    {
        return ['required', 'integer', Rule::exists('depo_obat', 'id')->where('is_active', true)->whereNull('deleted_at')];
    }

    private function batchRules(): array
    {
        return [
            'depo_id' => $this->depotRules(), 'nomor_batch' => ['required', 'string', 'max:100'],
            'expired_at' => ['required', 'date_format:Y-m-d', 'after:today'],
            'jumlah' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'referensi' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
