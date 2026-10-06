@extends('layouts.app')

@section('title', 'Database Pasien')
@section('page-title', 'Database Pasien')

@section('content')
<div class="py-4" x-data="{ showImportModal: false, showGabungModal: false }">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <p class="text-sm text-gray-500">Kelola data seluruh pasien klinik, riwayat rekam medis, dan penggabungan berkas</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Template Excel --}}
            <a href="{{ route('pelayanan.pasien.template') }}" class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Template
            </a>
            {{-- Import Excel --}}
            <button @click="showImportModal = true" class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                Import Excel
            </button>
            {{-- Export Excel --}}
            <a href="{{ route('pelayanan.pasien.export') }}" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </a>
            {{-- Gabung RM --}}
            <button @click="showGabungModal = true" class="inline-flex items-center px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700" title="Gabung rekam medis ganda">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Gabung RM
            </button>
            {{-- Daftar Pasien Baru --}}
            <a href="{{ route('pelayanan.pasien.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Pasien Baru
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('pelayanan.pasien.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px] relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama pasien, No. RM, NIK, atau no. telepon..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                </div>
                <select name="asuransi_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Penjamin</option>
                    @foreach($asuransiList as $asuransi)
                    <option value="{{ $asuransi->id }}" {{ request('asuransi_id') == $asuransi->id ? 'selected' : '' }}>{{ $asuransi->nama }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No. RM</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama Pasien</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">JK / Umur</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Telepon</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Alamat</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Penjamin</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pasien as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-bold text-blue-600">
                            <a href="{{ route('pelayanan.pasien.show', $item) }}" class="hover:underline">{{ $item->no_rm }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-gray-800">{{ $item->nama }}</div>
                            @if($item->nik)
                            <span class="text-xs text-gray-400 font-mono">NIK: {{ $item->nik }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-center text-gray-600">
                            <span class="font-semibold">{{ $item->jenis_kelamin ?? '-' }}</span> / {{ $item->umur }} th
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->telepon ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">{{ $item->alamat ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($item->asuransi)
                            <span class="badge {{ $item->asuransi->jenis === 'bpjs' ? 'badge-green' : 'badge-blue' }}">
                                {{ $item->asuransi->nama }}
                            </span>
                            @else
                            <span class="badge badge-gray">Umum</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('pelayanan.kunjungan.create', ['pasien_id' => $item->id]) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-semibold bg-blue-50 px-2 py-1 rounded">
                                    + Daftar
                                </a>
                                <a href="{{ route('pelayanan.pasien.rekam-medis', $item) }}"
                                   class="text-purple-600 hover:text-purple-800 text-xs font-medium">
                                    RME
                                </a>
                                <a href="{{ route('pelayanan.pasien.edit', $item) }}"
                                   class="text-gray-600 hover:text-gray-800 text-xs font-medium">
                                    Edit
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data pasien terdaftar</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pasien->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $pasien->links() }}</div>
        @endif
    </div>

    {{-- Import Modal --}}
    <div x-show="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showImportModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Import Data Pasien via Excel</h3>
                <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form method="POST" action="{{ route('pelayanan.pasien.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">Unggah file Excel untuk mendaftarkan pasien secara massal.</p>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="w-full text-sm text-gray-500">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" @click="showImportModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">Import Data</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Gabung RM Modal --}}
    <div x-show="showGabungModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showGabungModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Gabung Rekam Medis (Merge Pasien)</h3>
                <button @click="showGabungModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form method="POST" action="{{ route('pelayanan.pasien.gabung') }}">
                @csrf
                <div class="space-y-4">
                    <p class="text-xs text-gray-500">Gunakan fitur ini jika satu pasien tidak sengaja memiliki 2 nomor rekam medis. Seluruh riwayat kunjungan dari Pasien yang Dihapus akan dipindahkan ke Pasien Utama.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Pasien Utama (Yang Dipertahankan)</label>
                        <input type="number" name="pasien_utama_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Contoh: 1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Pasien Duplikat (Yang Dihapus)</label>
                        <input type="number" name="pasien_hapus_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Contoh: 2">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" @click="showGabungModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700"
                            onclick="return confirm('Apakah Anda yakin ingin menggabungkan kedua rekam medis ini?')">
                        Gabung Berkas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
