@extends('layouts.app')

@section('title', 'Kunjungan Per-Poli')
@section('page-title', 'Kunjungan Per-Poli')

@section('content')
<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Poliklinik</label>
                    <select name="poliklinik_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ $selectedPoli == $poli->id ? 'selected' : '' }}>
                            {{ $poli->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ request('tanggal', today()->format('Y-m-d')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="menunggu" {{ request('status') == 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                        <option value="screening" {{ request('status') == 'screening' ? 'selected' : '' }}>Screening</option>
                        <option value="pemeriksaan" {{ request('status') == 'pemeriksaan' ? 'selected' : '' }}>Pemeriksaan</option>
                        <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                        <option value="batal" {{ request('status') == 'batal' ? 'selected' : '' }}>Batal</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Filter
            </button>
        </form>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Total Kunjungan</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $kunjungan->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Menunggu</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $kunjungan->where('status', 'menunggu')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Screening</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ $kunjungan->where('status', 'screening')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Pemeriksaan</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ $kunjungan->where('status', 'pemeriksaan')->count() }}</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">No</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Pasien</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">No.RM</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Dokter</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-800">{{ $index + 1 }}</td>
                        <td class="px-6 py-4 text-sm text-gray-800">{{ $item->pasien?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ $item->pasien?->no_rm ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $item->dokter?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="badge {{ match($item->status) {
                                'menunggu' => 'badge-yellow',
                                'screening' => 'badge-blue',
                                'pemeriksaan' => 'badge-purple',
                                'selesai' => 'badge-green',
                                default => 'badge-gray'
                            } }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($item->status !== 'batal')
                            <a href="{{ route('pendaftaran.cetak-antrian', $item) }}" 
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                Cetak Antrian
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">
                            Tidak ada kunjungan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
