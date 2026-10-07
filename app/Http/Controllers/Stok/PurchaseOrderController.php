<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use App\Models\Obat;
use App\Models\PurchaseOrder;
use App\Models\StokMutasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,dikirim,sebagian,diterima'],
        ]);

        $orders = PurchaseOrder::query()
            ->with(['depo:id,nama'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('no_po', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'number' => $order->no_po,
                'supplier' => $order->supplier,
                'date' => $order->tanggal?->format('Y-m-d'),
                'status' => $order->status,
                'total' => (float) $order->total,
                'depot' => $order->depo?->nama,
                'showUrl' => route('stok.purchase-order.show', $order),
                'receiveUrl' => route('stok.purchase-order.terima.form', $order),
            ]);

        return Inertia::render('stok/purchase-order/index', [
            'orders' => $orders,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('stok/purchase-order/create', [
            'depots' => DepoObat::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
            'medicines' => Obat::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'satuan_kecil', 'harga_beli'])->map(fn (Obat $medicine): array => [
                'id' => $medicine->id,
                'nama' => $medicine->nama,
                'satuan_kecil' => $medicine->satuan_kecil,
                'harga_beli' => (float) $medicine->harga_beli,
            ]),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'depo_id' => ['required', 'integer', 'exists:depo_obat,id'],
            'supplier' => ['required', 'string', 'max:200'],
            'tanggal' => ['required', 'date'],
            'tanggal_kirim' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.obat_id' => ['required', 'integer', 'distinct', 'exists:obat,id'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.harga' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($data): PurchaseOrder {
            $activeDepot = DepoObat::query()->whereKey($data['depo_id'])->where('is_active', true)->exists();

            if (! $activeDepot) {
                throw ValidationException::withMessages(['depo_id' => 'Pilih depo yang masih aktif.']);
            }

            $medicineIds = collect($data['items'])->pluck('obat_id')->all();
            $activeMedicines = Obat::query()->whereIn('id', $medicineIds)->where('is_active', true)->count();

            if ($activeMedicines !== count($medicineIds)) {
                throw ValidationException::withMessages(['items' => 'Pilih obat yang masih aktif.']);
            }

            $total = collect($data['items'])->sum(fn (array $item): float => round((float) $item['jumlah'] * (float) $item['harga'], 2));
            $purchaseOrder = PurchaseOrder::create([
                'no_po' => 'PO-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'depo_id' => $data['depo_id'],
                'supplier' => $data['supplier'],
                'tanggal' => $data['tanggal'],
                'tanggal_kirim' => $data['tanggal_kirim'] ?? null,
                'status' => 'draft',
                'catatan' => $data['catatan'] ?? null,
                'total' => $total,
            ]);

            foreach ($data['items'] as $item) {
                $purchaseOrder->items()->create([
                    'obat_id' => $item['obat_id'],
                    'jumlah' => $item['jumlah'],
                    'harga' => $item['harga'],
                    'total' => round((float) $item['jumlah'] * (float) $item['harga'], 2),
                ]);
            }

            return $purchaseOrder;
        });

        return redirect()->route('stok.purchase-order.show', $purchaseOrder)
            ->with('success', "PO {$purchaseOrder->no_po} berhasil dibuat.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['depo:id,nama', 'items.obat:id,nama,satuan_kecil']);

        return Inertia::render('stok/purchase-order/show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->no_po,
                'supplier' => $purchaseOrder->supplier,
                'date' => $purchaseOrder->tanggal?->format('Y-m-d'),
                'deliveryDate' => $purchaseOrder->tanggal_kirim?->format('Y-m-d'),
                'status' => $purchaseOrder->status,
                'depot' => $purchaseOrder->depo?->nama,
                'note' => $purchaseOrder->catatan,
                'total' => (float) $purchaseOrder->total,
                'items' => $purchaseOrder->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'medicine' => $item->obat?->nama ?? 'Obat dihapus',
                    'unit' => $item->obat?->satuan_kecil,
                    'quantity' => (float) $item->jumlah,
                    'received' => (float) $item->jumlah_terima,
                    'price' => (float) $item->harga,
                    'total' => (float) $item->total,
                ])->all(),
                'dispatchUrl' => route('stok.purchase-order.kirim', $purchaseOrder),
                'receiveUrl' => route('stok.purchase-order.terima.form', $purchaseOrder),
            ],
        ]);
    }

    public function kirim(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya PO berstatus draft yang dapat dikirim.']);
        }

        $purchaseOrder->update(['status' => 'dikirim']);

        return back()->with('success', 'Status PO diubah menjadi Dikirim.');
    }

    public function terimaBarang(PurchaseOrder $purchaseOrder): Response
    {
        abort_unless(in_array($purchaseOrder->status, ['dikirim', 'sebagian'], true), 404);
        $purchaseOrder->load(['depo:id,nama', 'items.obat:id,nama,satuan_kecil']);

        return Inertia::render('stok/purchase-order/terima', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->no_po,
                'supplier' => $purchaseOrder->supplier,
                'depot' => $purchaseOrder->depo?->nama,
                'items' => $purchaseOrder->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'medicine' => $item->obat?->nama ?? 'Obat dihapus',
                    'unit' => $item->obat?->satuan_kecil,
                    'quantity' => (float) $item->jumlah,
                    'received' => (float) $item->jumlah_terima,
                ])->all(),
            ],
            'submitUrl' => route('stok.purchase-order.terima', $purchaseOrder),
            'showUrl' => route('stok.purchase-order.show', $purchaseOrder),
        ]);
    }

    public function prosesTerima(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:purchase_order_item,id'],
            'items.*.jumlah_terima' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $purchaseOrder): void {
            $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if (! in_array($lockedOrder->status, ['dikirim', 'sebagian'], true)) {
                throw ValidationException::withMessages(['status' => 'PO ini tidak dapat menerima barang pada status saat ini.']);
            }

            $items = $lockedOrder->items()->whereIn('id', collect($data['items'])->pluck('id'))->orderBy('id')->lockForUpdate()->get();

            if ($items->count() !== count($data['items'])) {
                throw ValidationException::withMessages(['items' => 'Daftar barang tidak sesuai dengan PO ini.']);
            }

            $receivedAny = false;
            foreach ($items as $item) {
                $lineIndex = collect($data['items'])->search(fn (array $line): bool => (int) $line['id'] === $item->id);
                $quantityToReceive = (float) $data['items'][$lineIndex]['jumlah_terima'];
                $remaining = round((float) $item->jumlah - (float) $item->jumlah_terima, 2);

                if ($quantityToReceive > $remaining) {
                    throw ValidationException::withMessages(["items.{$lineIndex}.jumlah_terima" => "Jumlah penerimaan melebihi sisa {$remaining}."]);
                }

                if ($quantityToReceive <= 0) {
                    continue;
                }

                $receivedAny = true;
                $item->update(['jumlah_terima' => round((float) $item->jumlah_terima + $quantityToReceive, 2)]);
                $medicine = Obat::query()->lockForUpdate()->findOrFail($item->obat_id);
                $stockBefore = (float) $medicine->stok;
                $stockAfter = round($stockBefore + $quantityToReceive, 2);
                $medicine->update(['stok' => $stockAfter]);

                StokMutasi::create([
                    'obat_id' => $medicine->id,
                    'depo_id' => $lockedOrder->depo_id,
                    'jenis' => 'masuk',
                    'referensi_type' => 'PurchaseOrder',
                    'referensi_id' => $lockedOrder->id,
                    'jumlah' => $quantityToReceive,
                    'harga' => $item->harga,
                    'stok_sebelum' => $stockBefore,
                    'stok_sesudah' => $stockAfter,
                    'keterangan' => "Penerimaan PO {$lockedOrder->no_po}",
                ]);
            }

            if (! $receivedAny) {
                throw ValidationException::withMessages(['items' => 'Masukkan jumlah barang yang diterima.']);
            }

            $allItemsReceived = $lockedOrder->items()->whereColumn('jumlah_terima', '<', 'jumlah')->doesntExist();
            $lockedOrder->update(['status' => $allItemsReceived ? 'diterima' : 'sebagian']);
        });

        return redirect()->route('stok.purchase-order.show', $purchaseOrder)
            ->with('success', 'Penerimaan barang berhasil diproses dan stok telah diperbarui.');
    }
}
