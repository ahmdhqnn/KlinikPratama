@extends('layouts.app')

@section('title', 'Laporan Pendapatan Klinik')
@section('page-title', 'Laporan Pendapatan Kasir')

@section('content')
<div class="py-4 space-y-6">
    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" action="{{ route('laporan.pendapatan') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Filter
            </button>
            <a href="{{ route('laporan.pendapatan.export', ['dari' => $dari, 'sampai' => $sampai]) }}"
               class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 inline-flex items-center">
                📊 Export Excel
            </a>
        </form>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-gray-500 uppercase font-semibold">Total Pendapatan Bersih</span>
            <p class="text-3xl font-bold text-emerald-600 mt-2 font-mono">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-gray-500 uppercase font-semibold">Total Potongan / Diskon</span>
            <p class="text-3xl font-bold text-red-600 mt-2 font-mono">Rp {{ number_format($totalDiskon, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <th class="px-4 py-3 text-left">No. Tagihan</th>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Pasien</th>
                        <th class="px-4 py-3 text-left">Poliklinik</th>
                        <th class="px-4 py-3 text-center">Metode Bayar</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">Diskon</th>
                        <th class="px-4 py-3 text-right">Total Bersih</th>
                        <th class="px-4 py-3 text-center">Kuitansi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($tagihan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold">{{ $item->no_tagihan }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $item->kunjungan->poliklinik->nama }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge badge-blue text-[10px] uppercase">{{ $item->metode_bayar }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-gray-600">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-mono text-red-600">Rp {{ number_format($item->diskon, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('pelayanan.kasir.kuitansi', $item) }}" class="text-xs text-blue-600 hover:underline">
                                Cetak
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada transaksi tagihan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tagihan->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $tagihan->links() }}</div>
        @endif
    </div>
</div>
@endsection
