@extends('layouts.app')

@section('title', 'Laporan Kunjungan Pasien')
@section('page-title', 'Laporan Kunjungan Pasien')

@section('content')
<div class="py-4 space-y-6">
    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" action="{{ route('laporan.kunjungan') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Filter Data
            </button>
            <a href="{{ route('laporan.kunjungan.export', ['dari' => $dari, 'sampai' => $sampai]) }}"
               class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </a>
        </form>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-gray-500 uppercase font-semibold">Total Kunjungan</span>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalKunjungan }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-green-600 uppercase font-semibold">Kunjungan BPJS</span>
            <p class="text-3xl font-bold text-green-700 mt-2">{{ $totalBpjs }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <span class="text-xs text-blue-600 uppercase font-semibold">Kunjungan Non-BPJS (Umum)</span>
            <p class="text-3xl font-bold text-blue-700 mt-2">{{ $totalUmum }}</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <th class="px-4 py-3 text-left">No. Kunjungan</th>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">No. RM</th>
                        <th class="px-4 py-3 text-left">Nama Pasien</th>
                        <th class="px-4 py-3 text-left">Poliklinik</th>
                        <th class="px-4 py-3 text-left">Dokter</th>
                        <th class="px-4 py-3 text-center">Penjamin</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold">{{ $item->no_kunjungan }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->tanggal->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-mono text-blue-600">{{ $item->pasien->no_rm }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->pasien->nama }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $item->poliklinik->nama }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->dokter?->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }} text-[10px]">
                                {{ strtoupper($item->jenis_bayar) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->status === 'selesai' ? 'badge-green' : 'badge-gray' }} text-[10px]">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada data kunjungan</td></tr>
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
