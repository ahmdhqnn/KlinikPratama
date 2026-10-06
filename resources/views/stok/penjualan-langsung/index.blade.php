@extends('layouts.app')

@section('title', 'Penjualan Obat Langsung')
@section('page-title', 'Penjualan Obat Langsung (OTC)')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Penjualan obat bebas (Over The Counter) langsung ke pembeli tanpa pendaftaran periksa</p>
        </div>
        <a href="{{ route('stok.penjualan-langsung.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            + Transaksi Baru
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('stok.penjualan-langsung.index') }}" class="flex items-center space-x-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor transaksi, nama pembeli..."
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <input type="date" name="tanggal" value="{{ request('tanggal', today()->toDateString()) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">No. Transaksi</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Tanggal</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Pembeli</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Metode</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-right">Total</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($penjualan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-bold text-gray-800">{{ $item->no_transaksi }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->tanggal->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $item->nama_pembeli ?? 'Umum' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge badge-blue uppercase text-xs">{{ $item->metode_bayar }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 font-mono">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('stok.penjualan-langsung.nota', $item) }}" class="text-xs text-blue-600 hover:underline">
                                🖨️ Cetak Nota
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada transaksi penjualan langsung</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($penjualan->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $penjualan->links() }}</div>
        @endif
    </div>
</div>
@endsection
