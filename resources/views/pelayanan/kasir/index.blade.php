@extends('layouts.app')

@section('title', 'Antrian Kasir')
@section('page-title', 'Kasir & Pembayaran')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Antrian pembayaran tagihan pasien rawat jalan dan penerbitan kuitansi</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('pelayanan.kasir.index') }}" class="flex items-center space-x-4">
                <input type="date" name="tanggal" value="{{ request('tanggal', today()->toDateString()) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">No. Kunjungan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Pasien</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Poliklinik</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Penjamin</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800">{{ $item->no_kunjungan }}</td>
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-gray-800">{{ $item->pasien->nama }}</div>
                            <span class="text-xs text-gray-400 font-mono">{{ $item->pasien->no_rm }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $item->poliklinik->nama }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }}">
                                {{ strtoupper($item->jenis_bayar) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($item->tagihan && $item->tagihan->status === 'lunas')
                            <span class="badge badge-green font-semibold">Lunas</span>
                            @else
                            <span class="badge bg-pink-100 text-pink-800 font-semibold">Menunggu Pembayaran</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($item->tagihan && $item->tagihan->status === 'lunas')
                            <a href="{{ route('pelayanan.kasir.kuitansi', $item->tagihan) }}"
                               class="px-3 py-1.5 bg-green-50 text-green-700 hover:bg-green-100 text-xs font-semibold rounded-lg">
                                🖨️ Kuitansi
                            </a>
                            @else
                            <a href="{{ route('pelayanan.kasir.show', $item) }}"
                               class="px-4 py-1.5 bg-pink-600 text-white text-xs font-semibold rounded-lg hover:bg-pink-700 shadow-sm">
                                Bayar Tagihan →
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada antrian kasir</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($kunjungan->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $kunjungan->links() }}</div>
        @endif
    </div>
</div>
@endsection
