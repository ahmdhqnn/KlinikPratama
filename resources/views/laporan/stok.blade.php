@extends('layouts.app')

@section('title', 'Laporan Stok Obat')
@section('page-title', 'Laporan Stok & Opname')

@section('content')
<div class="py-4 space-y-6">
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-gray-500 uppercase font-semibold">Total Item Obat Terdaftar</span>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalItem }} item</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-red-600 uppercase font-semibold">Obat Menipis / Kritis (≤ Minimum)</span>
            <p class="text-3xl font-bold text-red-600 mt-2">{{ $stokRendahCount }} item</p>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('laporan.stok') }}" class="flex flex-wrap items-center gap-3">
                <select name="jenis" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Jenis</option>
                    <option value="obat" {{ request('jenis') === 'obat' ? 'selected' : '' }}>Obat</option>
                    <option value="bhp" {{ request('jenis') === 'bhp' ? 'selected' : '' }}>BHP</option>
                </select>
                <label class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="stok_rendah" value="1" {{ request('stok_rendah') ? 'checked' : '' }} class="mr-2 rounded">
                    Hanya Stok Kritis
                </label>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <th class="px-4 py-3 text-left">Kode</th>
                        <th class="px-4 py-3 text-left">Nama Obat / Alkes</th>
                        <th class="px-4 py-3 text-left">Jenis</th>
                        <th class="px-4 py-3 text-center">Stok Fisik</th>
                        <th class="px-4 py-3 text-center">Batas Minimum</th>
                        <th class="px-4 py-3 text-right">Harga Beli</th>
                        <th class="px-4 py-3 text-right">Nilai Total Aset</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($obat as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold">{{ $item->kode }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->nama }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $item->jenis === 'obat' ? 'badge-blue' : 'badge-purple' }} text-[10px]">
                                {{ strtoupper($item->jenis) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center font-mono font-bold">{{ $item->stok }} {{ $item->satuan_kecil }}</td>
                        <td class="px-4 py-3 text-center font-mono text-gray-500">{{ $item->stok_minimum }}</td>
                        <td class="px-4 py-3 text-right font-mono text-gray-600">Rp {{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-gray-800">
                            Rp {{ number_format($item->stok * $item->harga_beli, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($item->stok <= 0)
                            <span class="badge badge-red font-bold">Habis</span>
                            @elseif($item->stok <= $item->stok_minimum)
                            <span class="badge badge-yellow font-bold">Kritis</span>
                            @else
                            <span class="badge badge-green">Aman</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada data obat</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($obat->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $obat->links() }}</div>
        @endif
    </div>
</div>
@endsection
