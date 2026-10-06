@extends('layouts.app')

@section('title', 'Laporan Laboratorium')
@section('page-title', 'Laporan Hasil Laboratorium')

@section('content')
<div class="py-4 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" action="{{ route('laporan.laboratorium') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Filter</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">No. Kunjungan</th>
                        <th class="px-4 py-3 text-left">Pasien</th>
                        <th class="px-4 py-3 text-left">Pemeriksaan Lab</th>
                        <th class="px-4 py-3 text-left">Petugas Lab</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($hasilLab as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600 font-mono">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono font-semibold">{{ $item->kunjungan->no_kunjungan }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-3 text-gray-800 font-medium">{{ $item->laboratorium->nama }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->petugas?->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->status === 'selesai' ? 'badge-green' : 'badge-yellow' }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data pemeriksaan laboratorium</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($hasilLab->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $hasilLab->links() }}</div>
        @endif
    </div>
</div>
@endsection
