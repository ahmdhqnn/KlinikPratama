@extends('layouts.app')

@section('title', 'Edit Kunjungan - ' . $kunjungan->no_kunjungan)
@section('page-title', 'Edit Kunjungan: ' . $kunjungan->no_kunjungan)

@section('content')
<div class="py-4 max-w-2xl">
    <div class="mb-4">
        <a href="{{ route('pelayanan.kunjungan.show', $kunjungan) }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Detail Kunjungan</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        {{-- Info Pasien (Read-only) --}}
        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-between">
            <div>
                <span class="text-xs text-gray-500 block">Pasien Terdaftar</span>
                <span class="font-bold text-gray-800 text-base">{{ $kunjungan->pasien?->nama ?? '-' }}</span>
                <span class="text-xs font-mono text-gray-500 block">{{ $kunjungan->pasien?->no_rm ?? '-' }}</span>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500 block">No. Kunjungan</span>
                <span class="font-mono font-bold text-blue-600">{{ $kunjungan->no_kunjungan }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('pelayanan.kunjungan.update', $kunjungan) }}">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                {{-- Poliklinik Tujuan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poliklinik Tujuan <span class="text-red-500">*</span></label>
                    <select name="poliklinik_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ old('poliklinik_id', $kunjungan->poliklinik_id) == $poli->id ? 'selected' : '' }}>
                            {{ $poli->nama }} ({{ ucfirst($poli->jenis) }})
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Dokter Tujuan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dokter Tujuan (Pemeriksa)</label>
                    <select name="dokter_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Dokter (Bisa ditentukan nanti) --</option>
                        @foreach($dokterList as $dokter)
                        <option value="{{ $dokter->id }}" {{ old('dokter_id', $kunjungan->dokter_id) == $dokter->id ? 'selected' : '' }}>
                            {{ $dokter->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tanggal Kunjungan --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kunjungan <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', $kunjungan->tanggal->toDateString()) }}" required
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Pasien <span class="text-red-500">*</span></label>
                        <select name="jenis_pasien" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="lama" {{ old('jenis_pasien', $kunjungan->jenis_pasien) === 'lama' ? 'selected' : '' }}>Pasien Lama</option>
                            <option value="baru" {{ old('jenis_pasien', $kunjungan->jenis_pasien) === 'baru' ? 'selected' : '' }}>Pasien Baru</option>
                        </select>
                    </div>
                </div>

                {{-- Penjamin & Cara Bayar --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cara Bayar <span class="text-red-500">*</span></label>
                        <select name="jenis_bayar" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="umum" {{ old('jenis_bayar', $kunjungan->jenis_bayar) === 'umum' ? 'selected' : '' }}>Umum (Bayar Sendiri)</option>
                            <option value="bpjs" {{ old('jenis_bayar', $kunjungan->jenis_bayar) === 'bpjs' ? 'selected' : '' }}>BPJS Kesehatan</option>
                            <option value="asuransi" {{ old('jenis_bayar', $kunjungan->jenis_bayar) === 'asuransi' ? 'selected' : '' }}>Asuransi / Korporasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Penjamin / Asuransi</label>
                        <select name="asuransi_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Pilih Asuransi --</option>
                            @foreach($asuransiList as $asuransi)
                            <option value="{{ $asuransi->id }}" {{ old('asuransi_id', $kunjungan->asuransi_id) == $asuransi->id ? 'selected' : '' }}>{{ $asuransi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Catatan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Pendaftaran</label>
                    <textarea name="catatan" rows="2" placeholder="Catatan khusus atau rujukan awal"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('catatan', $kunjungan->catatan) }}</textarea>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-8 pt-4 border-t border-gray-100">
                <a href="{{ route('pelayanan.kunjungan.show', $kunjungan) }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
