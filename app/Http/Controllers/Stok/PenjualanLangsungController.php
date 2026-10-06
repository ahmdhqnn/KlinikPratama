<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\PenjualanLangsung;
use App\Models\StokMutasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PenjualanLangsungController extends Controller
{
    public function index(Request $request): View
    {
        $penjualan = PenjualanLangsung::with(['items.obat', 'kasir'])
            ->when($request->search, fn ($q, $s) => $q->where('no_transaksi', 'like', "%$s%")->orWhere('nama_pembeli', 'like', "%$s%"))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('stok.penjualan-langsung.index', compact('penjualan'));
    }

    public function create(): View
    {
        $obatList = Obat::where('is_active', true)->where('stok', '>', 0)->orderBy('nama')->get();

        return view('stok.penjualan-langsung.create', compact('obatList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nama_pembeli' => ['nullable', 'string', 'max:200'],
            'metode_bayar' => ['required', 'in:tunai,transfer,qris'],
            'bayar' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.obat_id' => ['required', 'exists:obat,id'],
            'items.*.jumlah' => ['required', 'numeric', 'min:1'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = 0;
        foreach ($request->items as $item) {
            $subtotal += $item['jumlah'] * $item['harga'];
        }

        $kembalian = max(0, $request->bayar - $subtotal);
        $noTransaksi = 'PJL-'.now()->format('Ymd').'-'.str_pad(PenjualanLangsung::max('id') + 1, 4, '0', STR_PAD_LEFT);

        $penjualan = PenjualanLangsung::create([
            'no_transaksi' => $noTransaksi,
            'tanggal' => today(),
            'kasir_id' => auth()->user()->nakes?->id,
            'nama_pembeli' => $request->nama_pembeli ?? 'Umum',
            'total' => $subtotal,
            'bayar' => $request->bayar,
            'kembalian' => $kembalian,
            'metode_bayar' => $request->metode_bayar,
        ]);

        foreach ($request->items as $itemData) {
            $penjualan->items()->create([
                'obat_id' => $itemData['obat_id'],
                'jumlah' => $itemData['jumlah'],
                'harga' => $itemData['harga'],
                'total' => $itemData['jumlah'] * $itemData['harga'],
            ]);

            // Deduct stock
            $obat = Obat::find($itemData['obat_id']);
            if ($obat) {
                $stokSebelum = $obat->stok;
                $stokSesudah = max(0, $stokSebelum - $itemData['jumlah']);
                $obat->update(['stok' => $stokSesudah]);

                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'jenis' => 'keluar',
                    'referensi_type' => 'PenjualanLangsung',
                    'referensi_id' => $penjualan->id,
                    'jumlah' => $itemData['jumlah'],
                    'harga' => $itemData['harga'],
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'keterangan' => "Penjualan langsung {$penjualan->no_transaksi}",
                ]);
            }
        }

        return redirect()->route('stok.penjualan-langsung.nota', $penjualan)
            ->with('success', 'Penjualan berhasil disimpan.');
    }

    public function show(PenjualanLangsung $penjualanLangsung): View
    {
        $penjualanLangsung->load(['items.obat', 'kasir']);

        return view('stok.penjualan-langsung.show', compact('penjualanLangsung'));
    }

    public function nota(PenjualanLangsung $penjualanLangsung): View
    {
        $penjualanLangsung->load(['items.obat', 'kasir']);

        return view('stok.penjualan-langsung.nota', compact('penjualanLangsung'));
    }
}
