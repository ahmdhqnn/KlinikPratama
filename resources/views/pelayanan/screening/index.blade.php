@extends('layouts.app')

@section('title', 'Screening Pasien')
@section('page-title', 'Screening & Tanda Vital Pasien')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Daftar pasien menunggu pemeriksaan tanda vital dan skrining keperawatan awal</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">No. Kunjungan</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Pasien</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Poliklinik</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Status</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($kunjungan as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800">{{ $item->no_kunjungan }}</td>
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-800">{{ $item->pasien?->nama ?? '-' }}</div>
                        <span class="text-xs text-gray-400 font-mono">{{ $item->pasien?->no_rm ?? '-' }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-800">{{ $item->poliklinik?->nama ?? '-' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="badge {{ $item->screening ? 'badge-green' : 'badge-yellow' }}">
                            {{ $item->screening ? 'Sudah Screening' : 'Belum Screening' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('pelayanan.screening.show', $item) }}"
                           class="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700">
                            {{ $item->screening ? 'Edit Screening' : 'Mulai Screening' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada pasien menunggu screening hari ini</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
