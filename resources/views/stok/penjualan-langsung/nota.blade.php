@extends('layouts.app')

@section('title', 'Nota Penjualan - ' . $penjualanLangsung->no_transaksi)
@section('page-title', 'Nota: ' . $penjualanLangsung->no_transaksi)

@section('content')
<div class="py-4 max-w-xl mx-auto space-y-6">
    <div class="flex items-center justify-between no-print">
        <a href="{{ route('stok.penjualan-langsung.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Penjualan</a>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white font-semibold text-xs rounded-lg hover:bg-blue-700">
            🖨️ Cetak Struk
        </button>
    </div>

    {{-- Thermal Receipt Style Layout --}}
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 font-mono text-xs" id="nota-print">
        <div class="text-center pb-4 border-b border-dashed border-gray-300">
            <h2 class="text-base font-bold text-gray-900">{{ config('app.name') }}</h2>
            <p class="text-[10px] text-gray-500">APOTEK & INSTALASI FARMASI</p>
            <p class="text-[10px] text-gray-500">Jl. Contoh No. 1, Telp: 021-12345678</p>
        </div>

        <div class="py-3 border-b border-dashed border-gray-300 space-y-1">
            <div class="flex justify-between">
                <span>No. Transaksi:</span>
                <span class="font-bold">{{ $penjualanLangsung->no_transaksi }}</span>
            </div>
            <div class="flex justify-between">
                <span>Tanggal:</span>
                <span>{{ $penjualanLangsung->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="flex justify-between">
                <span>Pembeli:</span>
                <span>{{ $penjualanLangsung->nama_pembeli ?? 'Umum' }}</span>
            </div>
        </div>

        <div class="py-3 border-b border-dashed border-gray-300">
            <table class="w-full">
                <thead>
                    <tr class="text-left border-b border-gray-200">
                        <th class="pb-1">Item</th>
                        <th class="pb-1 text-center">Qty</th>
                        <th class="pb-1 text-right">Harga</th>
                        <th class="pb-1 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($penjualanLangsung->items as $item)
                    <tr>
                        <td class="py-1.5 font-sans">{{ $item->obat->nama }}</td>
                        <td class="py-1.5 text-center">{{ $item->jumlah }}</td>
                        <td class="py-1.5 text-right">{{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="py-1.5 text-right font-semibold">{{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="py-3 border-b border-dashed border-gray-300 space-y-1">
            <div class="flex justify-between font-bold text-sm">
                <span>TOTAL:</span>
                <span>Rp {{ number_format($penjualanLangsung->total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>BAYAR ({{ strtoupper($penjualanLangsung->metode_bayar) }}):</span>
                <span>Rp {{ number_format($penjualanLangsung->bayar, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>KEMBALIAN:</span>
                <span class="font-bold text-gray-900">Rp {{ number_format($penjualanLangsung->kembalian, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="text-center pt-4 text-[10px] text-gray-500">
            <p>Terima kasih atas pembelian Anda.</p>
            <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
        </div>
    </div>
</div>
@endsection
