@extends('layouts.app')

@section('title', 'Ruang Poli - ' . $poliklinik->nama)
@section('page-title', 'Ruang Poli: ' . $poliklinik->nama)

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('master.poliklinik.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Poliklinik</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $poliklinik->nama }} ({{ $poliklinik->kode }})</h2>
        </div>
    </div>

    {{-- Form Tambah Ruang --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Tambah Ruang Periksa Baru</h3>
        <form method="POST" action="{{ route('master.poliklinik.ruang.store', $poliklinik) }}" class="flex items-end space-x-4">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Ruang</label>
                <input type="text" name="nama" required placeholder="Contoh: Ruang Poli 1, Ruang Tindakan"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Tambah Ruang
            </button>
        </form>
    </div>

    {{-- Daftar Ruang --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Daftar Ruang Periksa</h3>
        </div>
        <table class="w-full">
            <thead>
                <tr>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama Ruang</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($poliklinik->ruangPoli as $index => $ruang)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $ruang->nama }}</td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" action="{{ route('master.poliklinik.ruang.destroy', $ruang) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                    onclick="return confirm('Hapus ruang poli ini?')">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada ruang periksa terdaftar</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
