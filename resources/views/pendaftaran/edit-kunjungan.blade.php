@extends('layouts.app')

@section('title', 'Edit Kunjungan')
@section('page-title', 'Edit Kunjungan')

@section('content')
<div class="max-w-4xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Informasi Pasien</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500 font-medium">Nama</p>
                <p class="text-gray-800 font-semibold">{{ $kunjungan->pasien?->nama ?? '-' }}</p>
            </div>
            <div>
                <p class="text-gray-500 font-medium">No.RM</p>
                <p class="text-gray-800 font-mono font-semibold">{{ $kunjungan->pasien?->no_rm ?? '-' }}</p>
            </div>
            <div>
                <p class="text-gray-500 font-medium">Jenis Kelamin</p>
                <p class="text-gray-800 font-semibold">{{ $kunjungan->pasien?->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
            </div>
            <div>
                <p class="text-gray-500 font-medium">Umur</p>
                <p class="text-gray-800 font-semibold">{{ $kunjungan->pasien?->umur ?? '-' }} tahun</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('pendaftaran.update-kunjungan', $kunjungan) }}" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="jenis_pasien" value="{{ $kunjungan->jenis_pasien }}">

        {{-- Data Kunjungan --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Data Kunjungan</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poliklinik Tujuan <span class="text-red-500">*</span></label>
                    <select name="poliklinik_id" required id="poliklinik"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('poliklinik_id') border-red-500 @enderror"
                            @change="loadDokter">
                        <option value="">-- Pilih Poliklinik --</option>
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ old('poliklinik_id', $kunjungan->poliklinik_id) == $poli->id ? 'selected' : '' }}>
                            {{ $poli->nama }}
                        </option>
                        @endforeach
                    </select>
                    @error('poliklinik_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dokter</label>
                    <select name="dokter_id" id="dokter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Pilih Dokter --</option>
                        @if($kunjungan->dokter)
                        <option value="{{ $kunjungan->dokter->id }}" selected>{{ $kunjungan->dokter->nama }}</option>
                        @endif
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asuransi/Penjamin</label>
                    <select name="asuransi_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Umum --</option>
                        @foreach($asuransiList as $asuransi)
                        <option value="{{ $asuransi->id }}" {{ old('asuransi_id', $kunjungan->asuransi_id) == $asuransi->id ? 'selected' : '' }}>
                            {{ $asuransi->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cara Bayar <span class="text-red-500">*</span></label>
                    <select name="jenis_bayar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="umum" {{ old('jenis_bayar', $kunjungan->jenis_bayar) == 'umum' ? 'selected' : '' }}>Umum</option>
                        <option value="bpjs" {{ old('jenis_bayar', $kunjungan->jenis_bayar) == 'bpjs' ? 'selected' : '' }}>BPJS</option>
                        <option value="asuransi" {{ old('jenis_bayar', $kunjungan->jenis_bayar) == 'asuransi' ? 'selected' : '' }}>Asuransi</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                <textarea name="catatan" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('catatan', $kunjungan->catatan) }}</textarea>
            </div>
        </div>

        {{-- Buttons --}}
        <div class="flex gap-3">
            <a href="{{ route('pendaftaran.laporan-kunjungan') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const hari = @js($hariIni);
    
    async function loadDokter() {
        const poliklinikId = document.getElementById('poliklinik').value;
        if (!poliklinikId) {
            document.getElementById('dokter').innerHTML = '<option value="">-- Pilih Dokter --</option>';
            return;
        }

        try {
            const response = await fetch(`{{ route('pendaftaran.dokter.by-poli') }}?poliklinik_id=${poliklinikId}&hari=${hari}`);
            const dokter = await response.json();
            
            const select = document.getElementById('dokter');
            select.replaceChildren(new Option('-- Pilih Dokter --', ''));
            dokter.forEach(d => {
                select.add(new Option(d.nama, d.id));
            });
            
            @if($kunjungan->dokter_id)
            document.getElementById('dokter').value = '{{ $kunjungan->dokter_id }}';
            @endif
        } catch (error) {
            console.error('Error loading dokter:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('poliklinik').value) {
            loadDokter();
        }
    });
</script>
@endpush
@endsection
