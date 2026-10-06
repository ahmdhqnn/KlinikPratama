@extends('layouts.app')

@section('title', 'Dashboard Perawat')
@section('page-title', 'Dashboard Perawat')

@section('content')
<div class="py-4 space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Menunggu TTV</p>
            <p class="text-3xl font-bold text-blue-600 mt-2">{{ $stats['menunggu_ttv'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Siap Diperiksa Dokter</p>
            <p class="text-3xl font-bold text-green-600 mt-2">{{ $stats['siap_dokter'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Triase Darurat</p>
            <p class="text-3xl font-bold text-red-600 mt-2">{{ $stats['triase_darurat'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">Daftar Kunjungan Hari Ini</h3>
                <p class="text-xs text-gray-500 mt-1">Prioritaskan pasien berdasarkan hasil skrining visual.</p>
            </div>
            <a href="{{ route('pelayanan.screening.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Buka Daftar Kunjungan</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Pasien</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Poli</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Triase</th>
                        <th class="px-4 py-3 text-right text-xs uppercase text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $item)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $item->pasien?->nama }}<div class="text-xs text-gray-400">{{ $item->pasien?->no_rm }}</div></td>
                        <td class="px-4 py-3 text-sm">{{ $item->poliklinik?->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold">{{ ucfirst($item->screening?->kesimpulan_triase ?? 'Belum dinilai') }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('pelayanan.screening.show', $item) }}" class="text-sm text-blue-600 hover:underline">Periksa</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada kunjungan hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
