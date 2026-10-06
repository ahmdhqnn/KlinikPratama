@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6 py-4">
    {{-- Stats cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Pasien</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($totalPasien) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('pelayanan.pasien.index') }}" class="text-xs text-blue-600 hover:text-blue-800">Lihat semua →</a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Kunjungan Hari Ini</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($kunjunganHariIni) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('pelayanan.antrian') }}" class="text-xs text-green-600 hover:text-green-800">Lihat antrian →</a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Kunjungan Bulan Ini</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($kunjunganBulanIni) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('laporan.kunjungan') }}" class="text-xs text-purple-600 hover:text-purple-800">Lihat laporan →</a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pendapatan Bulan Ini</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format($pendapatanBulanIni, 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('laporan.pendapatan') }}" class="text-xs text-yellow-600 hover:text-yellow-800">Lihat laporan →</a>
            </div>
        </div>
    </div>

    {{-- Status antrian & tabel kunjungan --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Status antrian hari ini --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Status Kunjungan Hari Ini</h2>
            <div class="space-y-3">
                @php
                $statusList = [
                    'menunggu'    => ['label' => 'Menunggu', 'color' => 'bg-yellow-100 text-yellow-700'],
                    'screening'   => ['label' => 'Screening', 'color' => 'bg-blue-100 text-blue-700'],
                    'pemeriksaan' => ['label' => 'Pemeriksaan', 'color' => 'bg-purple-100 text-purple-700'],
                    'farmasi'     => ['label' => 'Farmasi', 'color' => 'bg-orange-100 text-orange-700'],
                    'kasir'       => ['label' => 'Kasir', 'color' => 'bg-pink-100 text-pink-700'],
                    'selesai'     => ['label' => 'Selesai', 'color' => 'bg-green-100 text-green-700'],
                    'batal'       => ['label' => 'Batal', 'color' => 'bg-red-100 text-red-700'],
                ];
                @endphp
                @foreach($statusList as $status => $info)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">{{ $info['label'] }}</span>
                    <span class="inline-flex items-center justify-center min-w-8 h-7 px-2 rounded-full text-xs font-semibold {{ $info['color'] }}">
                        {{ $statusKunjungan[$status] ?? 0 }}
                    </span>
                </div>
                @endforeach
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('pelayanan.antrian') }}"
                   class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Lihat Antrian
                </a>
            </div>
        </div>

        {{-- Kunjungan terkini --}}
        <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Kunjungan Terkini Hari Ini</h2>
                <a href="{{ route('pelayanan.kunjungan.index') }}" class="text-sm text-blue-600 hover:text-blue-800">Lihat semua →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No. Kunjungan</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Pasien</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Poli</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Dokter</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($kunjunganTerkini as $kunjungan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <span class="font-mono">{{ $kunjungan->no_kunjungan }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <div class="font-medium">{{ $kunjungan->pasien->nama }}</div>
                                <div class="text-xs text-gray-400">{{ $kunjungan->pasien->no_rm }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $kunjungan->poliklinik->nama }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $kunjungan->dokter?->nama ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @php
                                $statusColors = [
                                    'menunggu'    => 'badge-yellow',
                                    'screening'   => 'badge-blue',
                                    'pemeriksaan' => 'badge-purple',
                                    'farmasi'     => 'bg-orange-100 text-orange-800',
                                    'kasir'       => 'bg-pink-100 text-pink-800',
                                    'selesai'     => 'badge-green',
                                    'batal'       => 'badge-red',
                                ];
                                @endphp
                                <span class="badge {{ $statusColors[$kunjungan->status] ?? 'badge-gray' }}">
                                    {{ ucfirst($kunjungan->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                Belum ada kunjungan hari ini
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4">Aksi Cepat</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach([
                ['route' => 'pelayanan.pasien.create', 'icon' => 'user-plus', 'label' => 'Daftarkan Pasien Baru', 'color' => 'blue'],
                ['route' => 'pelayanan.kunjungan.create', 'icon' => 'clipboard', 'label' => 'Buka Kunjungan', 'color' => 'green'],
                ['route' => 'pelayanan.antrian', 'icon' => 'list', 'label' => 'Lihat Antrian', 'color' => 'purple'],
                ['route' => 'stok.purchase-order.create', 'icon' => 'shopping-cart', 'label' => 'Buat PO Obat', 'color' => 'yellow'],
                ['route' => 'stok.penjualan-langsung.create', 'icon' => 'tag', 'label' => 'Jual Obat Langsung', 'color' => 'orange'],
                ['route' => 'laporan.pendapatan', 'icon' => 'chart', 'label' => 'Laporan Pendapatan', 'color' => 'red'],
            ] as $action)
            <a href="{{ route($action['route']) }}"
               class="flex flex-col items-center p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-{{ $action['color'] }}-300 hover:bg-{{ $action['color'] }}-50 transition-colors group">
                <div class="w-10 h-10 bg-{{ $action['color'] }}-100 rounded-xl flex items-center justify-center mb-2 group-hover:bg-{{ $action['color'] }}-200 transition-colors">
                    <svg class="w-5 h-5 text-{{ $action['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($action['icon'] === 'user-plus')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        @elseif($action['icon'] === 'clipboard')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        @elseif($action['icon'] === 'list')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        @elseif($action['icon'] === 'shopping-cart')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        @elseif($action['icon'] === 'tag')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        @endif
                    </svg>
                </div>
                <span class="text-xs text-center text-gray-600 font-medium leading-tight">{{ $action['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
