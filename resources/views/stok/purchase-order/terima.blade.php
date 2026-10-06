@extends('layouts.app')

@section('title', 'Verifikasi Barang Datang - ' . $purchaseOrder->no_po)
@section('page-title', 'Verifikasi Barang Datang: ' . $purchaseOrder->no_po)

@section('content')
<div class="py-4 max-w-4xl space-y-6">
    <div>
        <a href="{{ route('stok.purchase-order.show', $purchaseOrder) }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Detail PO</a>
        <h2 class="text-xl font-bold text-gray-800 mt-1">Verifikasi Fisik Penerimaan Barang</h2>
        <p class="text-sm text-gray-500">Masukkan jumlah fisik barang yang datang. Stok obat akan otomatis bertambah sesuai jumlah yang diverifikasi.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('stok.purchase-order.terima', $purchaseOrder) }}">
            @csrf

            <table class="w-full text-sm mb-6">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-4 py-3 text-left">Nama Obat</th>
                        <th class="px-4 py-3 text-center">Jumlah Dipesan</th>
                        <th class="px-4 py-3 text-center">Sudah Diterima</th>
                        <th class="px-4 py-3 text-center">Sisa Belum Datang</th>
                        <th class="px-4 py-3 text-center w-40">Terima Sekarang <span class="text-red-500">*</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($purchaseOrder->items as $index => $item)
                    @php $sisa = max(0, $item->jumlah - $item->jumlah_terima); @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-800">{{ $item->obat->nama }}</span>
                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                        </td>
                        <td class="px-4 py-3 text-center font-mono">{{ $item->jumlah }} {{ $item->obat->satuan_kecil }}</td>
                        <td class="px-4 py-3 text-center font-mono text-green-600">{{ $item->jumlah_terima }}</td>
                        <td class="px-4 py-3 text-center font-mono text-orange-600 font-semibold">{{ $sisa }}</td>
                        <td class="px-4 py-3 text-center">
                            <input type="number" step="0.01" name="items[{{ $index }}][jumlah_terima]"
                                   value="{{ $sisa }}" min="0" max="{{ $sisa }}"
                                   class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-center font-mono text-sm focus:ring-2 focus:ring-green-500">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-100">
                <a href="{{ route('stok.purchase-order.show', $purchaseOrder) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-green-600 text-white font-bold text-sm rounded-lg hover:bg-green-700 shadow-sm"
                        onclick="return confirm('Konfirmasi penerimaan barang? Stok obat akan bertambah secara otomatis.')">
                    ✓ Konfirmasi Penerimaan & Tambah Stok
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
