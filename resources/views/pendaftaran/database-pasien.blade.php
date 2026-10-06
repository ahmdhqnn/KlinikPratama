@extends('layouts.app')

@section('title', 'Database Pasien')
@section('page-title', 'Database Pasien')

@section('content')
<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cari Pasien</label>
                    <input type="text" name="search" placeholder="Nama, No.RM, KTP, HP..." value="{{ request('search') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua --</option>
                        <option value="L" {{ request('jenis_kelamin') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ request('jenis_kelamin') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Filter
                </button>
                <a href="{{ route('pendaftaran.database-pasien') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">No.RM</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Nama</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">JK</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Tanggal Lahir</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">No.HP</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Asuransi</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Terdaftar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pasien as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-mono font-semibold text-blue-600">{{ $p->no_rm }}</td>
                        <td class="px-6 py-4 text-sm text-gray-800">{{ $p->nama }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $p->jenis_kelamin === 'L' ? 'L' : 'P' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            {{ $p->tanggal_lahir?->format('d/m/Y') }} 
                            <span class="text-xs text-gray-500">({{ $p->umur }} th)</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $p->telepon ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $p->asuransi?->nama ?? 'Umum' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $p->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">
                            Tidak ada data pasien
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($pasien->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $pasien->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
