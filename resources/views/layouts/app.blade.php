<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="flex h-full" id="app">
    {{-- Sidebar --}}
    <div class="flex flex-col w-64 bg-gray-900 min-h-screen fixed left-0 top-0 z-30 overflow-y-auto" id="sidebar">
        {{-- Logo --}}
        <div class="flex items-center h-16 px-4 bg-gray-900 border-b border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-bold text-sm">{{ config('app.name') }}</p>
                    <p class="text-gray-400 text-xs">RME v1.0</p>
                </div>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-2 py-4 space-y-1">
            @if(auth()->user()->role === 'pendaftaran')
            @foreach([
                ['pendaftaran.dashboard', 'Dashboard'],
                ['pendaftaran.laporan-kunjungan', 'Laporan Kunjungan'],
                ['pendaftaran.pendaftaran-baru', 'Pendaftaran Pasien Baru'],
                ['pendaftaran.pendaftaran-lama', 'Pendaftaran Pasien Lama'],
                ['pendaftaran.database-pasien', 'Database Pasien'],
                ['pendaftaran.kunjungan-per-poli', 'Kunjungan Per Poli'],
                ['pendaftaran.laporan-top-diagnosa', 'Laporan Top Diagnosa'],
                ['pendaftaran.jadwal-praktik', 'Jadwal Praktik Dokter'],
            ] as [$routeName, $label])
            <a href="{{ route($routeName) }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                {{ $label }}
            </a>
            @endforeach
            @if(\App\Models\KlinikSetting::pelaksanaTtv() === 'pendaftaran')
            <a href="{{ route('pelayanan.screening.index') }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('pelayanan.screening.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                Pemeriksaan TTV & Skrining
            </a>
            @endif
            @elseif(auth()->user()->role === 'perawat')
            @foreach([
                ['perawat.dashboard', 'Dashboard'],
                ['pelayanan.screening.index', 'Daftar Kunjungan'],
                ['pelayanan.pasien.index', 'Database Pasien'],
                ['pendaftaran.jadwal-praktik', 'Jadwal Praktik Dokter'],
                ['pendaftaran.laporan-top-diagnosa', 'Laporan Top Diagnosa'],
                ['pendaftaran.kunjungan-per-poli', 'Kunjungan Per Poli'],
            ] as [$routeName, $label])
            <a href="{{ route($routeName) }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                {{ $label }}
            </a>
            @endforeach
            @elseif(auth()->user()->role === 'dokter')
            @foreach([
                ['dokter.dashboard', 'Dashboard'],
                ['dokter.kunjungan', 'Daftar Kunjungan'],
                ['dokter.janji-kunjungan', 'Janji Kunjungan'],
                ['dokter.pasien', 'Database Pasien'],
                ['dokter.stok-obat', 'Lihat Stok Obat'],
                ['dokter.laporan-top-diagnosa', 'Laporan Top Diagnosa'],
            ] as [$routeName, $label])
            <a href="{{ route($routeName) }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                {{ $label }}
            </a>
            @endforeach
            <a href="{{ route('pelayanan.pemeriksaan.index') }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('pelayanan.pemeriksaan.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                Pemeriksaan Dokter
            </a>
            @elseif(auth()->user()->role === 'farmasi')
            <a href="{{ route('pelayanan.farmasi.index') }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('pelayanan.farmasi.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                Antrian Farmasi
            </a>
            @elseif(auth()->user()->role === 'kasir')
            <a href="{{ route('pelayanan.kasir.index') }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('pelayanan.kasir.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                Antrian Kasir
            </a>
            @else
            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            {{-- Master Data --}}
            <div x-data="{ open: {{ request()->is('master/*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        Master Data
                    </div>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" class="mt-1 pl-4 space-y-1">
                    @foreach([
                        ['master.depo-obat.index', 'Depo Obat'],
                        ['master.poliklinik.index', 'Poliklinik'],
                        ['master.tindakan.index', 'Tindakan'],
                        ['master.laboratorium.index', 'Laboratorium'],
                        ['master.paket-tindakan.index', 'Paket Tindakan'],
                        ['master.biaya-admin.index', 'Biaya Admin'],
                        ['master.biaya-pendaftaran.index', 'Biaya Pendaftaran'],
                        ['master.nakes.index', 'Nakes / SDM'],
                        ['master.obat.index', 'Obat'],
                        ['master.alkes.index', 'Alkes'],
                        ['master.asuransi.index', 'Asuransi'],
                    ] as [$routeName, $label])
                    <a href="{{ route($routeName) }}"
                       class="flex items-center px-3 py-1.5 rounded-md text-sm transition-colors
                              {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-2"></span>
                        {{ $label }}
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Pelayanan --}}
            <div x-data="{ open: {{ request()->is('pelayanan/*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Pelayanan
                    </div>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" class="mt-1 pl-4 space-y-1">
                    @foreach([
                        ['pelayanan.pasien.index', 'Database Pasien'],
                        ['pelayanan.kunjungan.index', 'Pendaftaran'],
                        ['pelayanan.antrian', 'Antrian'],
                        ['pelayanan.screening.index', 'Screening Pasien'],
                        ['pelayanan.pemeriksaan.index', 'Pemeriksaan Dokter'],
                        ['pelayanan.farmasi.index', 'Farmasi'],
                        ['pelayanan.kasir.index', 'Kasir'],
                    ] as [$routeName, $label])
                    <a href="{{ route($routeName) }}"
                       class="flex items-center px-3 py-1.5 rounded-md text-sm transition-colors
                              {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-2"></span>
                        {{ $label }}
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Manajemen Stok --}}
            <div x-data="{ open: {{ request()->is('stok/*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                        </svg>
                        Manajemen Stok
                    </div>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" class="mt-1 pl-4 space-y-1">
                    @foreach([
                        ['stok.purchase-order.index', 'Purchase Order'],
                        ['stok.penjualan-langsung.index', 'Penjualan Langsung'],
                    ] as [$routeName, $label])
                    <a href="{{ route($routeName) }}"
                       class="flex items-center px-3 py-1.5 rounded-md text-sm transition-colors
                              {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-2"></span>
                        {{ $label }}
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Laporan --}}
            <div x-data="{ open: {{ request()->is('laporan/*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Laporan
                    </div>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" class="mt-1 pl-4 space-y-1">
                    @foreach([
                        ['laporan.kunjungan', 'Lap. Kunjungan'],
                        ['laporan.pendapatan', 'Lap. Pendapatan'],
                        ['laporan.stok', 'Lap. Stok Obat'],
                        ['laporan.laboratorium', 'Lap. Laboratorium'],
                    ] as [$routeName, $label])
                    <a href="{{ route($routeName) }}"
                       class="flex items-center px-3 py-1.5 rounded-md text-sm transition-colors
                              {{ request()->routeIs($routeName) ? 'bg-blue-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-2"></span>
                        {{ $label }}
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Setting --}}
            <div class="pt-2 border-t border-gray-700">
                <a href="{{ route('setting.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('setting.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Setting
                </a>
                <a href="{{ route('users.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('users.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Manajemen User
                </a>
            </div>
            @endif
        </nav>

        {{-- User info at bottom --}}
        <div class="p-4 border-t border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-sm font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                    <p class="text-gray-400 text-xs truncate">{{ ucfirst(auth()->user()->role) }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-400 hover:text-white transition-colors" title="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Main content --}}
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        {{-- Top bar --}}
        <header class="bg-white border-b border-gray-200 sticky top-0 z-20">
            <div class="flex items-center justify-between h-16 px-6">
                <div>
                    <h1 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('breadcrumb')
                    <nav class="text-xs text-gray-500">@yield('breadcrumb')</nav>
                    @endif
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</span>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        <div class="px-6 pt-4">
            @if(session('success'))
            <div class="alert alert-success mb-4 flex items-center justify-between" x-data="{ show: true }" x-show="show">
                <span>{{ session('success') }}</span>
                <button @click="show = false" class="ml-2 text-green-600 hover:text-green-800">✕</button>
            </div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger mb-4 flex items-center justify-between" x-data="{ show: true }" x-show="show">
                <span>{{ session('error') }}</span>
                <button @click="show = false" class="ml-2 text-red-600 hover:text-red-800">✕</button>
            </div>
            @endif
            @if($errors->any())
            <div class="alert alert-danger mb-4">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        {{-- Page content --}}
        <main class="flex-1 px-6 pb-6">
            @yield('content')
        </main>
    </div>
</div>

<script>
// Simple alpine-like accordion (no dependency needed for basic cases)
document.querySelectorAll('[x-data]').forEach(el => {
    // Alpine.js handles this if loaded
});
</script>
@stack('scripts')
</body>
</html>
