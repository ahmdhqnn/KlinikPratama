@extends('layouts.app')

@section('title', 'Pemeriksaan Dokter')
@section('page-title', 'Pemeriksaan Dokter (Konsultasi)')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Antrian pasien untuk konsultasi, diagnosa ICD-10, tindakan, dan peresepan obat</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('pelayanan.pemeriksaan.index') }}" class="flex items-center space-x-4">
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
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Dokter</th>
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
                            <span class="text-xs text-gray-400 font-mono">{{ $item->pasien->no_rm }} ({{ $item->pasien->jenis_kelamin ?? '-' }})</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $item->poliklinik->nama }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->dokter?->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->status === 'pemeriksaan' ? 'badge-purple' : 'badge-green' }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('pelayanan.pemeriksaan.show', $item) }}"
                               class="px-4 py-1.5 bg-purple-600 text-white text-xs font-semibold rounded-lg hover:bg-purple-700 shadow-sm">
                                Buka Pemeriksaan →
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada pasien menunggu pemeriksaan dokter</td></tr>
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
