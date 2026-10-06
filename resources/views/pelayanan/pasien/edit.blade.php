@extends('layouts.app')

@section('title', 'Edit Pasien - ' . $pasien->nama)
@section('page-title', 'Edit Data Pasien: ' . $pasien->nama)

@section('content')
<div class="py-4 max-w-4xl">
    <div class="mb-4">
        <a href="{{ route('pelayanan.pasien.show', $pasien) }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Profil Pasien</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="{{ route('pelayanan.pasien.update', $pasien) }}">
            @csrf
            @method('PUT')

            <h3 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">1. Data Pribadi Pasien</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap Pasien <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" value="{{ old('nama', $pasien->nama) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIK (Nomor Induk Kependudukan)</label>
                    <input type="text" name="nik" value="{{ old('nik', $pasien->nik) }}" maxlength="16"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $pasien->tanggal_lahir?->toDateString()) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Jenis Kelamin --</option>
                        <option value="L" {{ old('jenis_kelamin', $pasien->jenis_kelamin) === 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('jenis_kelamin', $pasien->jenis_kelamin) === 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Golongan Darah</label>
                    <select name="golongan_darah" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Golongan Darah --</option>
                        <option value="A" {{ old('golongan_darah', $pasien->golongan_darah) === 'A' ? 'selected' : '' }}>A</option>
                        <option value="B" {{ old('golongan_darah', $pasien->golongan_darah) === 'B' ? 'selected' : '' }}>B</option>
                        <option value="AB" {{ old('golongan_darah', $pasien->golongan_darah) === 'AB' ? 'selected' : '' }}>AB</option>
                        <option value="O" {{ old('golongan_darah', $pasien->golongan_darah) === 'O' ? 'selected' : '' }}>O</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agama</label>
                    <select name="agama" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Pilih Agama --</option>
                        <option value="Islam" {{ old('agama', $pasien->agama) === 'Islam' ? 'selected' : '' }}>Islam</option>
                        <option value="Kristen" {{ old('agama', $pasien->agama) === 'Kristen' ? 'selected' : '' }}>Kristen</option>
                        <option value="Katolik" {{ old('agama', $pasien->agama) === 'Katolik' ? 'selected' : '' }}>Katolik</option>
                        <option value="Hindu" {{ old('agama', $pasien->agama) === 'Hindu' ? 'selected' : '' }}>Hindu</option>
                        <option value="Buddha" {{ old('agama', $pasien->agama) === 'Buddha' ? 'selected' : '' }}>Buddha</option>
                        <option value="Konghucu" {{ old('agama', $pasien->agama) === 'Konghucu' ? 'selected' : '' }}>Konghucu</option>
                    </select>
                </div>
            </div>

            <h3 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">2. Kontak & Alamat</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon / WhatsApp</label>
                    <input type="text" name="telepon" value="{{ old('telepon', $pasien->telepon) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pekerjaan</label>
                    <input type="text" name="pekerjaan" value="{{ old('pekerjaan', $pasien->pekerjaan) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap</label>
                    <textarea name="alamat" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('alamat', $pasien->alamat) }}</textarea>
                </div>
            </div>

            <h3 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">3. Penjamin & Asuransi</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Penjamin / Asuransi</label>
                    <select name="asuransi_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Umum (Bayar Sendiri) --</option>
                        @foreach($asuransiList as $asuransi)
                        <option value="{{ $asuransi->id }}" {{ old('asuransi_id', $pasien->asuransi_id) == $asuransi->id ? 'selected' : '' }}>
                            {{ $asuransi->nama }} ({{ strtoupper($asuransi->jenis) }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Kartu Asuransi / BPJS</label>
                    <input type="text" name="no_asuransi" value="{{ old('no_asuransi', $pasien->no_asuransi) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono">
                </div>
            </div>

            <h3 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">4. Riwayat Medis Penting</h3>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Riwayat Alergi (Obat / Makanan)</label>
                <textarea name="riwayat_alergi" rows="2" class="w-full px-3 py-2 border border-red-300 rounded-lg text-sm bg-red-50 focus:bg-white">{{ old('riwayat_alergi', $pasien->riwayat_alergi) }}</textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-100">
                <a href="{{ route('pelayanan.pasien.show', $pasien) }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Simpan Perubahan Data Pasien
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
