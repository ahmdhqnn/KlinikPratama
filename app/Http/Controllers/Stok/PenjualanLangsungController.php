<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\PenjualanLangsung;
use App\Models\StokMutasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PenjualanLangsungController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $sales = PenjualanLangsung::query()
            ->with(['kasir:id,nama', 'items.obat:id,nama'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('no_transaksi', 'like', "%{$search}%")
                        ->orWhere('nama_pembeli', 'like', "%{$search}%");
                });
            })
            ->when($filters['tanggal'] ?? null, fn ($query, string $date) => $query->whereDate('tanggal', $date))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PenjualanLangsung $sale): array => [
                'id' => $sale->id,
                'number' => $sale->no_transaksi,
                'date' => $sale->tanggal?->format('Y-m-d'),
                'buyer' => $sale->nama_pembeli ?: 'Umum',
                'cashier' => $sale->kasir?->nama ?? '—',
                'itemCount' => $sale->items->count(),
                'total' => (float) $sale->total,
                'paymentMethod' => $sale->metode_bayar,
                'receiptUrl' => route('stok.penjualan-langsung.nota', $sale),
            ]);

        return Inertia::render('stok/penjualan-langsung/index', [
            'sales' => $sales,
            'filters' => ['search' => $filters['search'] ?? '', 'tanggal' => $filters['tanggal'] ?? ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('stok/penjualan-langsung/create', [
            'medicines' => Obat::query()
                ->where('is_active', true)
                ->where('stok', '>', 0)
                ->orderBy('nama')
                ->get(['id', 'nama', 'satuan_kecil', 'stok', 'harga_jual'])
                ->map(fn (Obat $medicine): array => [
                    'id' => $medicine->id,
                    'name' => $medicine->nama,
                    'unit' => $medicine->satuan_kecil,
                    'stock' => (float) $medicine->stok,
                    'price' => (float) $medicine->harga_jual,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_pembeli' => ['nullable', 'string', 'max:200'],
            'metode_bayar' => ['required', 'in:tunai,transfer,qris'],
            'bayar' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
        ]);

        $sale = DB::transaction(function () use ($data): PenjualanLangsung {
            $quantitiesByMedicine = collect($data['items'])
                ->groupBy('obat_id')
                ->map(fn ($items): float => round($items->sum(fn (array $item): float => (float) $item['jumlah']), 2));
            $medicines = Obat::query()
                ->whereIn('id', $quantitiesByMedicine->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($medicines->count() !== $quantitiesByMedicine->count()) {
                throw ValidationException::withMessages(['items' => 'Salah satu obat sudah tidak tersedia.']);
            }

            foreach ($quantitiesByMedicine as $medicineId => $quantity) {
                $medicine = $medicines->get($medicineId);

                if (! $medicine->is_active) {
                    throw ValidationException::withMessages(['items' => "Obat {$medicine->nama} sudah tidak aktif."]);
                }

                if ($quantity > (float) $medicine->stok) {
                    throw ValidationException::withMessages(['items' => "Stok {$medicine->nama} tidak mencukupi. Sisa stok: {$medicine->stok}."]);
                }
            }

            $lineItems = collect($data['items'])->map(function (array $item) use ($medicines): array {
                $medicine = $medicines->get($item['obat_id']);
                $quantity = (float) $item['jumlah'];
                $price = (float) $medicine->harga_jual;

                return [
                    'medicine' => $medicine,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => round($quantity * $price, 2),
                ];
            });
            $total = round($lineItems->sum('total'), 2);
            $paid = (float) $data['bayar'];

            if ($paid < $total) {
                throw ValidationException::withMessages(['bayar' => 'Nominal bayar tidak boleh kurang dari total tagihan.']);
            }

            $sale = PenjualanLangsung::create([
                'no_transaksi' => 'PJL-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'tanggal' => today(),
                'kasir_id' => auth()->user()->nakes?->id,
                'nama_pembeli' => ($data['nama_pembeli'] ?? null) ?: 'Umum',
                'total' => $total,
                'bayar' => $paid,
                'kembalian' => round($paid - $total, 2),
                'metode_bayar' => $data['metode_bayar'],
            ]);

            foreach ($lineItems as $lineItem) {
                $medicine = $lineItem['medicine'];
                $sale->items()->create([
                    'obat_id' => $medicine->id,
                    'jumlah' => $lineItem['quantity'],
                    'harga' => $lineItem['price'],
                    'total' => $lineItem['total'],
                ]);
            }

            foreach ($quantitiesByMedicine as $medicineId => $quantity) {
                $medicine = $medicines->get($medicineId);
                $stockBefore = (float) $medicine->stok;
                $stockAfter = round($stockBefore - $quantity, 2);
                $medicine->update(['stok' => $stockAfter]);

                StokMutasi::create([
                    'obat_id' => $medicine->id,
                    'jenis' => 'keluar',
                    'referensi_type' => 'PenjualanLangsung',
                    'referensi_id' => $sale->id,
                    'jumlah' => $quantity,
                    'harga' => $medicine->harga_jual,
                    'stok_sebelum' => $stockBefore,
                    'stok_sesudah' => $stockAfter,
                    'keterangan' => "Penjualan langsung {$sale->no_transaksi}",
                ]);
            }

            return $sale;
        });

        return redirect()->route('stok.penjualan-langsung.nota', $sale)
            ->with('success', 'Penjualan berhasil disimpan.');
    }

    public function show(PenjualanLangsung $penjualanLangsung): Response
    {
        return $this->receipt($penjualanLangsung);
    }

    public function nota(PenjualanLangsung $penjualanLangsung): Response
    {
        return $this->receipt($penjualanLangsung);
    }

    private function receipt(PenjualanLangsung $sale): Response
    {
        $sale->load(['items.obat:id,nama,satuan_kecil', 'kasir:id,nama']);

        return Inertia::render('stok/penjualan-langsung/nota', [
            'receipt' => [
                'id' => $sale->id,
                'number' => $sale->no_transaksi,
                'createdAt' => $sale->created_at?->format('d/m/Y H:i'),
                'buyer' => $sale->nama_pembeli ?: 'Umum',
                'cashier' => $sale->kasir?->nama ?? '—',
                'total' => (float) $sale->total,
                'paid' => (float) $sale->bayar,
                'change' => (float) $sale->kembalian,
                'paymentMethod' => $sale->metode_bayar,
                'items' => $sale->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->obat?->nama ?? 'Obat dihapus',
                    'unit' => $item->obat?->satuan_kecil,
                    'quantity' => (float) $item->jumlah,
                    'price' => (float) $item->harga,
                    'total' => (float) $item->total,
                ])->all(),
            ],
            'indexUrl' => route('stok.penjualan-langsung.index'),
        ]);
    }
}
