@extends('layouts.app')

@section('title', 'Dashboard Dokter')
@section('page-title', 'Dashboard Dokter')

@section('content')
<div class="py-4 space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        @foreach([
            ['Kunjungan Hari Ini', $kunjunganHariIni, 'blue'],
            ['Menunggu Pemeriksaan', $menungguPemeriksaan, 'purple'],
            ['Selesai Hari Ini', $selesaiHariIni, 'green'],
        ] as [$label, $value, $color])
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-sm text-gray-500">{{ $label }}</p>
            <p class="text-3xl font-bold text-{{ $color }}-600 mt-2">{{ number_format($value) }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Jadwal Praktik Hari Ini</h2>
            <div class="space-y-3">
                @forelse($jadwalHariIni as $jadwal)
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-2">
                        <span class="text-gray-700">{{ $jadwal->poliklinik->nama }}</span>
                        <span class="font-mono text-purple-600">{{ substr($jadwal->jam_mulai, 0, 5) }} - {{ substr($jadwal->jam_selesai, 0, 5) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Tidak ada jadwal praktik hari ini.</p>
                @endforelse
            </div>
        </div>

        <div class="xl:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">Pasien Perlu Ditangani</h2>
                <a href="{{ route('dokter.kunjungan') }}" class="text-sm text-blue-600 hover:text-blue-800">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr><th class="px-5 py-3 text-left">Pasien</th><th class="px-5 py-3 text-left">Poli</th><th class="px-5 py-3 text-left">Status</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($kunjunganTerkini as $item)
                        <tr>
                            <td class="px-5 py-3"><div class="font-medium">{{ $item->pasien->nama }}</div><div class="text-xs text-gray-400">{{ $item->pasien->no_rm }}</div></td>
                            <td class="px-5 py-3">{{ $item->poliklinik->nama }}</td>
                            <td class="px-5 py-3"><span class="badge badge-purple">{{ ucfirst($item->status) }}</span></td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('pelayanan.pemeriksaan.show', $item) }}" class="text-purple-600 font-semibold">Buka</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">Belum ada pasien yang perlu ditangani.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
