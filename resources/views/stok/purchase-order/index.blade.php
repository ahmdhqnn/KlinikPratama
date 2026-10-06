@extends('layouts.app')

@section('title', 'Purchase Order (PO) Obat')
@section('page-title', 'Purchase Order (PO) Obat')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Kelola pemesanan obat ke supplier dan verifikasi penerimaan barang fisik</p>
        </div>
        <a href="{{ route('stok.purchase-order.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            + Buat PO Baru
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('stok.purchase-order.index') }}" class="flex items-center space-x-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor PO atau nama supplier..."
                       class="w-full pl-4 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="dikirim" {{ request('status') === 'dikirim' ? 'selected' : '' }}>Dikirim</option>
                    <option value="sebagian" {{ request('status') === 'sebagian' ? 'selected' : '' }}>Sebagian</option>
                    <option value="diterima" {{ request('status') === 'diterima' ? 'selected' : '' }}>Diterima Lengkap</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">No. PO</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Tanggal</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Supplier</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Tujuan Depo</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-right">Total Nilai</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $po)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-bold text-gray-800">
                            <a href="{{ route('stok.purchase-order.show', $po) }}" class="text-blue-600 hover:underline">{{ $po->no_po }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $po->tanggal->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $po->supplier }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $po->depo->nama }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">Rp {{ number_format($po->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                            $poColors = [
                                'draft'    => 'badge-gray',
                                'dikirim'  => 'badge-blue',
                                'sebagian' => 'badge-yellow',
                                'diterima' => 'badge-green',
                                'batal'    => 'badge-red',
                            ];
                            @endphp
                            <span class="badge {{ $poColors[$po->status] ?? 'badge-gray' }}">
                                {{ ucfirst($po->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('stok.purchase-order.show', $po) }}" class="text-xs text-blue-600 hover:underline">Detail</a>
                                @if(in_array($po->status, ['dikirim', 'sebagian']))
                                <a href="{{ route('stok.purchase-order.terima.form', $po) }}"
                                   class="text-xs px-2 py-1 bg-green-50 text-green-700 font-semibold rounded hover:bg-green-100">
                                    Verifikasi Barang Datang →
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada purchase order</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection
