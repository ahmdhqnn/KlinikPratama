@extends('layouts.app')

@section('title', 'Antrian Farmasi')
@section('page-title', 'Farmasi / Apotek')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Antrian resep dokter untuk penyiapan, peracikan, dan penyerahan obat ke pasien</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('pelayanan.farmasi.index') }}" class="flex items-center space-x-4">
                <input type="date" name="tanggal" value="{{ request('tanggal', today()->toDateString()) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">No. Resep</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Pasien</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Dokter Peresep</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Jumlah Item</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-bold text-gray-800">
                            {{ $item->resep?->no_resep ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-gray-800">{{ $item->pasien->nama }}</div>
                            <span class="text-xs text-gray-400 font-mono">{{ $item->pasien->no_rm }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->dokter?->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800">
                            {{ $item->resep?->resepObat->count() ?? 0 }} obat
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge bg-orange-100 text-orange-800 font-medium">Menunggu Racik</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('pelayanan.farmasi.show', $item) }}"
                               class="px-4 py-1.5 bg-orange-600 text-white text-xs font-semibold rounded-lg hover:bg-orange-700 shadow-sm">
                                Proses Resep →
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada resep menunggu penyiapan obat</td></tr>
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
