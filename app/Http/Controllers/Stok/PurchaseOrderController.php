<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\DepoObat;
use App\Models\Obat;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StokMutasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::with(['depo', 'items.obat'])
            ->when($request->search, fn ($q, $s) => $q->where('no_po', 'like', "%$s%")->orWhere('supplier', 'like', "%$s%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('stok.purchase-order.index', compact('orders'));
    }

    public function create(): View
    {
        $depoList = DepoObat::where('is_active', true)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->orderBy('nama')->get();

        return view('stok.purchase-order.create', compact('depoList', 'obatList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'depo_id' => ['required', 'exists:depo_obat,id'],
            'supplier' => ['required', 'string', 'max:200'],
            'tanggal' => ['required', 'date'],
            'tanggal_kirim' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.obat_id' => ['required', 'exists:obat,id'],
            'items.*.jumlah' => ['required', 'numeric', 'min:1'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
        ]);

        $noPo = 'PO-'.now()->format('Ymd').'-'.str_pad((PurchaseOrder::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT);

        $total = 0;
        foreach ($request->items as $item) {
            $total += $item['jumlah'] * $item['harga'];
        }

        $po = PurchaseOrder::create([
            'no_po' => $noPo,
            'depo_id' => $request->depo_id,
            'supplier' => $request->supplier,
            'tanggal' => $request->tanggal,
            'tanggal_kirim' => $request->tanggal_kirim,
            'status' => 'draft',
            'catatan' => $request->catatan,
            'total' => $total,
        ]);

        foreach ($request->items as $item) {
            $po->items()->create([
                'obat_id' => $item['obat_id'],
                'jumlah' => $item['jumlah'],
                'harga' => $item['harga'],
                'total' => $item['jumlah'] * $item['harga'],
            ]);
        }

        return redirect()->route('stok.purchase-order.show', $po)
            ->with('success', "PO {$po->no_po} berhasil dibuat.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['depo', 'items.obat']);

        return view('stok.purchase-order.show', compact('purchaseOrder'));
    }

    public function kirim(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->update(['status' => 'dikirim']);

        return back()->with('success', 'Status PO diubah menjadi Dikirim.');
    }

    public function terimaBarang(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['depo', 'items.obat']);

        return view('stok.purchase-order.terima', compact('purchaseOrder'));
    }

    public function prosesTerima(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:purchase_order_item,id'],
            'items.*.jumlah_terima' => ['required', 'numeric', 'min:0'],
        ]);

        $semuaSelesai = true;

        foreach ($request->items as $itemData) {
            $item = PurchaseOrderItem::findOrFail($itemData['id']);
            $jumlahTerima = $itemData['jumlah_terima'];

            if ($jumlahTerima > 0) {
                $item->update(['jumlah_terima' => $item->jumlah_terima + $jumlahTerima]);

                // Update obat stock
                $obat = $item->obat;
                $stokSebelum = $obat->stok;
                $stokSesudah = $stokSebelum + $jumlahTerima;
                $obat->update(['stok' => $stokSesudah]);

                // Log mutation
                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'depo_id' => $purchaseOrder->depo_id,
                    'jenis' => 'masuk',
                    'referensi_type' => 'PurchaseOrder',
                    'referensi_id' => $purchaseOrder->id,
                    'jumlah' => $jumlahTerima,
                    'harga' => $item->harga,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'keterangan' => "Penerimaan PO {$purchaseOrder->no_po}",
                ]);
            }

            if ($item->jumlah_terima < $item->jumlah) {
                $semuaSelesai = false;
            }
        }

        $purchaseOrder->update([
            'status' => $semuaSelesai ? 'diterima' : 'sebagian',
        ]);

        return redirect()->route('stok.purchase-order.show', $purchaseOrder)
            ->with('success', 'Penerimaan barang berhasil diproses dan stok telah diperbarui.');
    }
}
