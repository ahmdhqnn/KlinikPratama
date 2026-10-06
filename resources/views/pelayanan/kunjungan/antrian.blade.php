@extends('layouts.app')

@section('title', 'Layar Antrian Pasien')
@section('page-title', 'Layar Antrian Hari Ini')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between no-print">
        <p class="text-sm text-gray-500">Antrian pasien aktif hari ini: {{ now()->isoFormat('dddd, D MMMM Y') }}</p>
        <button onclick="location.reload()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg">
            🔄 Refresh Antrian
        </button>
    </div>

    {{-- Board per Status --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        {{-- 1. Menunggu / Screening --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-yellow-500 px-4 py-3 text-white flex items-center justify-between">
                <span class="font-bold text-sm">1. Menunggu / Screening</span>
                <span class="badge bg-white text-yellow-800 font-bold">
                    {{ $kunjungan->whereIn('status', ['menunggu', 'screening'])->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3 max-h-[70vh] overflow-y-auto">
                @forelse($kunjungan->whereIn('status', ['menunggu', 'screening']) as $item)
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-xs font-bold text-gray-800">{{ $item->no_kunjungan }}</span>
                        <span class="badge badge-yellow text-[10px]">{{ ucfirst($item->status) }}</span>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $item->pasien->nama }}</p>
                    <p class="text-xs text-gray-500">{{ $item->poliklinik->nama }}</p>
                    <div class="mt-2 pt-2 border-t border-yellow-200 flex justify-end">
                        <a href="{{ route('pelayanan.screening.show', $item) }}" class="text-xs text-yellow-800 font-semibold hover:underline">
                            Proses Screening →
                        </a>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-400 text-xs py-8">Tidak ada antrian</p>
                @endforelse
            </div>
        </div>

        {{-- 2. Pemeriksaan Dokter --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-purple-600 px-4 py-3 text-white flex items-center justify-between">
                <span class="font-bold text-sm">2. Ruang Dokter</span>
                <span class="badge bg-white text-purple-800 font-bold">
                    {{ $kunjungan->where('status', 'pemeriksaan')->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3 max-h-[70vh] overflow-y-auto">
                @forelse($kunjungan->where('status', 'pemeriksaan') as $item)
                <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-xs font-bold text-gray-800">{{ $item->no_kunjungan }}</span>
                        <span class="badge badge-purple text-[10px]">{{ $item->poliklinik->nama }}</span>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $item->pasien->nama }}</p>
                    <p class="text-xs text-gray-500">Dokter: {{ $item->dokter?->nama ?? '-' }}</p>
                    <div class="mt-2 pt-2 border-t border-purple-200 flex justify-end">
                        <a href="{{ route('pelayanan.pemeriksaan.show', $item) }}" class="text-xs text-purple-800 font-semibold hover:underline">
                            Mulai Periksa →
                        </a>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-400 text-xs py-8">Tidak ada antrian</p>
                @endforelse
            </div>
        </div>

        {{-- 3. Farmasi / Apotek --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-orange-500 px-4 py-3 text-white flex items-center justify-between">
                <span class="font-bold text-sm">3. Farmasi</span>
                <span class="badge bg-white text-orange-800 font-bold">
                    {{ $kunjungan->where('status', 'farmasi')->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3 max-h-[70vh] overflow-y-auto">
                @forelse($kunjungan->where('status', 'farmasi') as $item)
                <div class="p-3 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-xs font-bold text-gray-800">{{ $item->no_kunjungan }}</span>
                        <span class="badge bg-orange-200 text-orange-800 text-[10px]">Siap Racik</span>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $item->pasien->nama }}</p>
                    <p class="text-xs text-gray-500">{{ $item->poliklinik->nama }}</p>
                    <div class="mt-2 pt-2 border-t border-orange-200 flex justify-end">
                        <a href="{{ route('pelayanan.farmasi.show', $item) }}" class="text-xs text-orange-800 font-semibold hover:underline">
                            Racik Obat →
                        </a>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-400 text-xs py-8">Tidak ada antrian</p>
                @endforelse
            </div>
        </div>

        {{-- 4. Kasir --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-pink-600 px-4 py-3 text-white flex items-center justify-between">
                <span class="font-bold text-sm">4. Kasir</span>
                <span class="badge bg-white text-pink-800 font-bold">
                    {{ $kunjungan->where('status', 'kasir')->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3 max-h-[70vh] overflow-y-auto">
                @forelse($kunjungan->where('status', 'kasir') as $item)
                <div class="p-3 bg-pink-50 border border-pink-200 rounded-lg">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-xs font-bold text-gray-800">{{ $item->no_kunjungan }}</span>
                        <span class="badge {{ $item->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }} text-[10px]">{{ strtoupper($item->jenis_bayar) }}</span>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $item->pasien->nama }}</p>
                    <p class="text-xs text-gray-500">{{ $item->poliklinik->nama }}</p>
                    <div class="mt-2 pt-2 border-t border-pink-200 flex justify-end">
                        <a href="{{ route('pelayanan.kasir.show', $item) }}" class="text-xs text-pink-800 font-semibold hover:underline">
                            Proses Pembayaran →
                        </a>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-400 text-xs py-8">Tidak ada antrian</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
