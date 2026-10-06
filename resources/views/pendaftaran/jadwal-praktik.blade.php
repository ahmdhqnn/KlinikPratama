@extends('layouts.app')

@section('title', 'Jadwal Praktik Dokter')
@section('page-title', 'Jadwal Praktik Dokter')

@section('content')
<div class="space-y-6">
    {{-- Form Tambah Jadwal --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Tambah Jadwal Praktik</h3>
        <form method="POST" action="{{ route('pendaftaran.store-jadwal-praktik') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Dokter <span class="text-red-500">*</span></label>
                    <select name="dokter_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('dokter_id') border-red-500 @enderror">
                        <option value="">-- Pilih Dokter --</option>
                        @foreach($dokterList as $dokter)
                        <option value="{{ $dokter->id }}">{{ $dokter->nama }}</option>
                        @endforeach
                    </select>
                    @error('dokter_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Poliklinik <span class="text-red-500">*</span></label>
                    <select name="poliklinik_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('poliklinik_id') border-red-500 @enderror">
                        <option value="">-- Pilih Poli --</option>
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}">{{ $poli->nama }}</option>
                        @endforeach
                    </select>
                    @error('poliklinik_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Hari <span class="text-red-500">*</span></label>
                    <select name="hari" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('hari') border-red-500 @enderror">
                        <option value="">-- Pilih Hari --</option>
                        @foreach($hariList as $hari)
                        <option value="{{ $hari }}">{{ ucfirst($hari) }}</option>
                        @endforeach
                    </select>
                    @error('hari') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Jam Mulai <span class="text-red-500">*</span></label>
                    <input type="time" name="jam_mulai" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('jam_mulai') border-red-500 @enderror">
                    @error('jam_mulai') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Jam Selesai <span class="text-red-500">*</span></label>
                    <input type="time" name="jam_selesai" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('jam_selesai') border-red-500 @enderror">
                    @error('jam_selesai') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                + Tambah Jadwal
            </button>
        </form>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Dokter</label>
                    <select name="dokter_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua Dokter --</option>
                        @foreach($dokterList as $dokter)
                        <option value="{{ $dokter->id }}" {{ request('dokter_id') == $dokter->id ? 'selected' : '' }}>{{ $dokter->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Poliklinik</label>
                    <select name="poliklinik_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua Poli --</option>
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ request('poliklinik_id') == $poli->id ? 'selected' : '' }}>{{ $poli->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Hari</label>
                    <select name="hari" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Semua Hari --</option>
                        @foreach($hariList as $hari)
                        <option value="{{ $hari }}" {{ request('hari') == $hari ? 'selected' : '' }}>{{ ucfirst($hari) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Filter
                </button>
                <a href="{{ route('pendaftaran.jadwal-praktik') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Dokter</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Poliklinik</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Hari</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Jam Praktik</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($jadwal as $j)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-800">{{ $j->dokter?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $j->poliklinik?->nama ?? '-' }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="badge badge-blue">{{ ucfirst($j->hari) }}</span>
                        </td>
                        <td class="px-6 py-4 text-center text-sm font-mono text-gray-700">
                            {{ substr($j->jam_mulai, 0, 5) }} - {{ substr($j->jam_selesai, 0, 5) }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="badge {{ $j->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $j->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <form method="POST" action="{{ route('pendaftaran.destroy-jadwal-praktik', $j) }}" 
                                  style="display: inline;"
                                  onsubmit="return confirm('Hapus jadwal ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">
                                    Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">
                            Tidak ada jadwal praktik
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
