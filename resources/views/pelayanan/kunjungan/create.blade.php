@extends('layouts.app')

@section('title', 'Pendaftaran Kunjungan Pasien')
@section('page-title', 'Buka Kunjungan Pasien')

@section('content')
<div class="py-4 max-w-2xl">
    <div class="mb-4">
        <a href="{{ route('pelayanan.kunjungan.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Daftar Kunjungan</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="{{ route('pelayanan.kunjungan.store') }}">
            @csrf

            <div class="space-y-5">
                {{-- Pilih Pasien --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Pasien <span class="text-red-500">*</span></label>
                    <select name="pasien_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Cari / Pilih Pasien --</option>
                        @foreach($pasienList as $p)
                        <option value="{{ $p->id }}" {{ (old('pasien_id') == $p->id || (isset($pasien) && $pasien->id == $p->id)) ? 'selected' : '' }}>
                            {{ $p->no_rm }} - {{ $p->nama }} ({{ $p->jenis_kelamin ?? '-' }}, {{ $p->tanggal_lahir?->format('d/m/Y') }})
                        </option>
                        @endforeach
                    </select>
                    <div class="mt-1 text-right">
                        <a href="{{ route('pelayanan.pasien.create') }}" class="text-xs text-blue-600 hover:text-blue-800">+ Pasien Baru Belum Terdaftar?</a>
                    </div>
                </div>

                {{-- Poliklinik Tujuan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poliklinik Tujuan <span class="text-red-500">*</span></label>
                    <select name="poliklinik_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Poliklinik --</option>
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ old('poliklinik_id') == $poli->id ? 'selected' : '' }}>
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
                        <option value="{{ $dokter->id }}" {{ old('dokter_id') == $dokter->id ? 'selected' : '' }}>
                            {{ $dokter->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tanggal Kunjungan --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kunjungan <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', today()->toDateString()) }}" required
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Pasien <span class="text-red-500">*</span></label>
                        <select name="jenis_pasien" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="baru" {{ old('jenis_pasien', (isset($pasien) && $pasien->kunjungan->count() > 0) ? 'lama' : 'baru') === 'baru' ? 'selected' : '' }}>Pasien Baru</option>
                            <option value="lama" {{ old('jenis_pasien', (isset($pasien) && $pasien->kunjungan->count() > 0) ? 'lama' : 'baru') === 'lama' ? 'selected' : '' }}>Pasien Lama</option>
                        </select>
                    </div>
                </div>

                {{-- Penjamin & Cara Bayar --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cara Bayar <span class="text-red-500">*</span></label>
                        <select name="jenis_bayar" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                            @php $defaultBayar = old('jenis_bayar', $pasien?->asuransi?->jenis ?? 'umum'); @endphp
                            <option value="umum" {{ $defaultBayar === 'umum' ? 'selected' : '' }}>Umum (Bayar Sendiri)</option>
                            <option value="bpjs" {{ $defaultBayar === 'bpjs' ? 'selected' : '' }}>BPJS Kesehatan</option>
                            <option value="asuransi" {{ in_array($defaultBayar, ['asuransi', 'perusahaan']) ? 'selected' : '' }}>Asuransi / Korporasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Penjamin / Asuransi</label>
                        <select name="asuransi_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Pilih Asuransi --</option>
                            @foreach($asuransiList as $asuransi)
                            <option value="{{ $asuransi->id }}" {{ old('asuransi_id', $pasien?->asuransi_id) == $asuransi->id ? 'selected' : '' }}>{{ $asuransi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Catatan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Pendaftaran</label>
                    <textarea name="catatan" rows="2" placeholder="Catatan khusus atau rujukan awal"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('catatan') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-8 pt-4 border-t border-gray-100">
                <a href="{{ route('pelayanan.kunjungan.index') }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Daftarkan Kunjungan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
