@extends('layouts.app')

@section('title', 'Laporan Kunjungan Pasien')
@section('page-title', 'Laporan Kunjungan Pasien')

@section('content')
<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cari Pasien</label>
                    <input type="text" name="search" placeholder="Nama atau No.RM" value="{{ request('search') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ request('tanggal') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Poliklinik</label>
                    <select name="poliklinik_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua Poli --</option>
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ request('poliklinik_id') == $poli->id ? 'selected' : '' }}>
                            {{ $poli->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="menunggu" {{ request('status') == 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                        <option value="screening" {{ request('status') == 'screening' ? 'selected' : '' }}>Screening</option>
                        <option value="pemeriksaan" {{ request('status') == 'pemeriksaan' ? 'selected' : '' }}>Pemeriksaan</option>
                        <option value="farmasi" {{ request('status') == 'farmasi' ? 'selected' : '' }}>Farmasi</option>
                        <option value="kasir" {{ request('status') == 'kasir' ? 'selected' : '' }}>Kasir</option>
                        <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                        <option value="batal" {{ request('status') == 'batal' ? 'selected' : '' }}>Batal</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Filter
                </button>
                <a href="{{ route('pendaftaran.laporan-kunjungan') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">No.Kunjungan</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Pasien</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">No.RM</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Poliklinik</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Dokter</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Tanggal</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-mono font-semibold text-gray-800">{{ $item->no_kunjungan }}</td>
                        <td class="px-6 py-4 text-sm text-gray-800">{{ $item->pasien?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ $item->pasien?->no_rm ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $item->poliklinik?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $item->dokter?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $item->tanggal?->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="badge {{ match($item->status) {
                                'menunggu' => 'badge-yellow',
                                'screening' => 'badge-blue',
                                'pemeriksaan' => 'badge-purple',
                                'farmasi' => 'badge-indigo',
                                'kasir' => 'badge-green',
                                'selesai' => 'badge-success',
                                'batal' => 'badge-red',
                                default => 'badge-gray'
                            } }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                @if($item->status !== 'batal')
                                <a href="{{ route('pendaftaran.cetak-antrian', $item) }}" 
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium"
                                   title="Cetak Antrian">
                                    🖨️
                                </a>
                                @endif
                                @if($item->status !== 'selesai' && $item->status !== 'batal')
                                <a href="{{ route('pendaftaran.edit-kunjungan', $item) }}" 
                                   class="text-green-600 hover:text-green-800 text-xs font-medium"
                                   title="Edit Kunjungan">
                                    ✏️
                                </a>
                                @endif
                                @if($item->status !== 'selesai' && $item->status !== 'batal')
                                <form method="POST" action="{{ route('pendaftaran.batal-kunjungan', $item) }}" 
                                      style="display: inline;" 
                                      onsubmit="return confirm('Batalkan kunjungan ini?')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium" title="Batalkan">
                                        ✕
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-400 text-sm">
                            Tidak ada data kunjungan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($kunjungan->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $kunjungan->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
