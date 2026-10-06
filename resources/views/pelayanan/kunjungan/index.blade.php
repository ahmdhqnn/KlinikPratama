@extends('layouts.app')

@section('title', 'Daftar Kunjungan Pasien')
@section('page-title', 'Pendaftaran & Kunjungan')

@section('content')
<div class="py-4 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500">Kelola antrian pendaftaran kunjungan pasien rawat jalan</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('pelayanan.antrian') }}" class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700">
                📺 Layar Antrian
            </a>
            <a href="{{ route('pelayanan.kunjungan.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                + Buka Kunjungan Baru
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('pelayanan.kunjungan.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px] relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama pasien, No. RM..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                </div>
                <input type="date" name="tanggal" value="{{ request('tanggal', today()->toDateString()) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="menunggu" {{ request('status') === 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                    <option value="screening" {{ request('status') === 'screening' ? 'selected' : '' }}>Screening</option>
                    <option value="pemeriksaan" {{ request('status') === 'pemeriksaan' ? 'selected' : '' }}>Pemeriksaan</option>
                    <option value="farmasi" {{ request('status') === 'farmasi' ? 'selected' : '' }}>Farmasi</option>
                    <option value="kasir" {{ request('status') === 'kasir' ? 'selected' : '' }}>Kasir</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="batal" {{ request('status') === 'batal' ? 'selected' : '' }}>Batal</option>
                </select>
                <select name="poliklinik_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Poli</option>
                    @foreach($poliklinikList as $poli)
                    <option value="{{ $poli->id }}" {{ request('poliklinik_id') == $poli->id ? 'selected' : '' }}>{{ $poli->nama }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No. Kunjungan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Pasien</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Poli Tujuan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Dokter</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Jenis Bayar</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Alur / Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kunjungan as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-bold text-gray-800">
                            <a href="{{ route('pelayanan.kunjungan.show', $item) }}" class="text-blue-600 hover:underline">
                                {{ $item->no_kunjungan }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-gray-800">{{ $item->pasien->nama }}</div>
                            <span class="text-xs text-gray-400 font-mono">{{ $item->pasien->no_rm }} ({{ $item->pasien->jenis_kelamin ?? '-' }})</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $item->poliklinik->nama }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->dokter?->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }}">
                                {{ strtoupper($item->jenis_bayar) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
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
                            <span class="badge {{ $statusColors[$item->status] ?? 'badge-gray' }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center space-x-1.5">
                                {{-- Flow Actions based on status --}}
                                @if($item->status === 'menunggu')
                                <a href="{{ route('pelayanan.screening.show', $item) }}"
                                   class="text-xs px-2 py-1 bg-blue-50 text-blue-700 font-semibold rounded hover:bg-blue-100">
                                    Screening →
                                </a>
                                @elseif($item->status === 'pemeriksaan')
                                <a href="{{ route('pelayanan.pemeriksaan.show', $item) }}"
                                   class="text-xs px-2 py-1 bg-purple-50 text-purple-700 font-semibold rounded hover:bg-purple-100">
                                    Periksa Dokter →
                                </a>
                                @elseif($item->status === 'farmasi')
                                <a href="{{ route('pelayanan.farmasi.show', $item) }}"
                                   class="text-xs px-2 py-1 bg-orange-50 text-orange-700 font-semibold rounded hover:bg-orange-100">
                                    Farmasi →
                                </a>
                                @elseif($item->status === 'kasir')
                                <a href="{{ route('pelayanan.kasir.show', $item) }}"
                                   class="text-xs px-2 py-1 bg-pink-50 text-pink-700 font-semibold rounded hover:bg-pink-100">
                                    Kasir →
                                </a>
                                @elseif($item->status === 'selesai' && $item->tagihan)
                                <a href="{{ route('pelayanan.kasir.kuitansi', $item->tagihan) }}"
                                   class="text-xs px-2 py-1 bg-green-50 text-green-700 font-semibold rounded hover:bg-green-100">
                                    Kuitansi
                                </a>
                                @endif

                                <a href="{{ route('pelayanan.kunjungan.show', $item) }}"
                                   class="text-xs text-gray-500 hover:text-gray-700 px-1 py-1">Detail</a>

                                @if(!in_array($item->status, ['selesai', 'batal']))
                                <form method="POST" action="{{ route('pelayanan.kunjungan.batal', $item) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-1 py-1"
                                            onclick="return confirm('Batalkan kunjungan ini?')">Batal</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada kunjungan pada tanggal yang dipilih</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($kunjungan->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $kunjungan->links() }}</div>
        @endif
    </div>
</div>
@endsection
