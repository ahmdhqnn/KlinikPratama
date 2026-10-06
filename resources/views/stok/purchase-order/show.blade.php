@extends('layouts.app')

@section('title', 'Detail PO - ' . $purchaseOrder->no_po)
@section('page-title', 'Purchase Order: ' . $purchaseOrder->no_po)

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('stok.purchase-order.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Daftar PO</a>
        <div class="flex items-center space-x-3">
            @if($purchaseOrder->status === 'draft')
            <form method="POST" action="{{ route('stok.purchase-order.kirim', $purchaseOrder) }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700">
                    Kirim PO ke Supplier
                </button>
            </form>
            @endif

            @if(in_array($purchaseOrder->status, ['dikirim', 'sebagian']))
            <a href="{{ route('stok.purchase-order.terima.form', $purchaseOrder) }}" class="px-4 py-2 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700">
                📦 Verifikasi Penerimaan Barang
            </a>
            @endif
        </div>
    </div>

    {{-- Detail Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pb-6 border-b border-gray-100 text-xs">
            <div>
                <span class="text-gray-400 block uppercase">Nomor PO</span>
                <span class="font-mono font-bold text-gray-800 text-sm">{{ $purchaseOrder->no_po }}</span>
            </div>
            <div>
                <span class="text-gray-400 block uppercase">Supplier</span>
                <span class="font-bold text-gray-800 text-sm">{{ $purchaseOrder->supplier }}</span>
            </div>
            <div>
                <span class="text-gray-400 block uppercase">Tanggal PO</span>
                <span class="text-gray-800">{{ $purchaseOrder->tanggal->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="text-gray-400 block uppercase">Status</span>
                <span class="badge badge-blue text-xs mt-1">{{ ucfirst($purchaseOrder->status) }}</span>
            </div>
        </div>

        <div class="py-4">
            <h4 class="text-sm font-bold text-gray-800 mb-3">Item Obat yang Dipesan</h4>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-4 py-2 text-left">Nama Obat</th>
                        <th class="px-4 py-2 text-center">Dipesan</th>
                        <th class="px-4 py-2 text-center">Sudah Diterima</th>
                        <th class="px-4 py-2 text-right">Harga Satuan</th>
                        <th class="px-4 py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($purchaseOrder->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->obat->nama }}</td>
                        <td class="px-4 py-3 text-center font-mono font-semibold">{{ $item->jumlah }} {{ $item->obat->satuan_kecil }}</td>
                        <td class="px-4 py-3 text-center font-mono font-bold text-green-600">{{ $item->jumlah_terima }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">Rp {{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-200 font-bold">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right">TOTAL PO:</td>
                        <td class="px-4 py-3 text-right font-mono text-blue-700 text-base">Rp {{ number_format($purchaseOrder->total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
