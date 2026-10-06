@extends('layouts.app')

@section('title', 'Pendaftaran Pasien Lama')
@section('page-title', 'Pendaftaran Pasien Lama')

@section('content')
<div class="max-w-4xl" x-data="pendaftaranLama()">
    <form method="POST" action="{{ route('pendaftaran.store-pasien-lama') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="jenis_pasien" value="lama">

        {{-- Cari Pasien --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Cari Pasien</h3>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cari Pasien <span class="text-red-500">*</span></label>
                <input type="text" 
                       x-model="searchQuery"
                       @input.debounce.300ms="searchPasien"
                       placeholder="Ketik nama, No. RM, atau NIK pasien..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                       autocomplete="off">
                
                <div x-show="searchResults.length > 0" class="mt-2 border border-gray-200 rounded-lg shadow-lg bg-white max-h-64 overflow-y-auto">
                    <template x-for="pasien in searchResults" :key="pasien.id">
                        <div @click="selectPasien(pasien)" 
                             class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800" x-text="pasien.nama"></p>
                                    <p class="text-xs text-gray-500">
                                        <span x-text="'No.RM: ' + pasien.no_rm"></span> | 
                                        <span x-text="pasien.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan'"></span>
                                    </p>
                                </div>
                                <span class="text-xs text-blue-600 font-medium">Pilih →</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Selected Patient Info --}}
            <div x-show="selectedPasien" class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="text-sm font-bold text-blue-800 mb-2">Pasien Terpilih:</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-blue-700 font-medium">Nama:</span>
                        <span class="text-blue-900" x-text="selectedPasien?.nama"></span>
                    </div>
                    <div>
                        <span class="text-blue-700 font-medium">No.RM:</span>
                        <span class="text-blue-900 font-mono" x-text="selectedPasien?.no_rm"></span>
                    </div>
                    <div>
                        <span class="text-blue-700 font-medium">Jenis Kelamin:</span>
                        <span class="text-blue-900" x-text="selectedPasien?.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan'"></span>
                    </div>
                    <div>
                        <span class="text-blue-700 font-medium">No.HP:</span>
                        <span class="text-blue-900" x-text="selectedPasien?.telepon || '-'"></span>
                    </div>
                </div>
                <button type="button" @click="clearSelection" class="mt-3 text-xs text-blue-600 hover:text-blue-800 font-medium">
                    Ganti Pasien
                </button>
            </div>

            <input type="hidden" name="pasien_id" x-model="selectedPasienId">
            @error('pasien_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Data Kunjungan --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6" x-show="selectedPasien">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Data Kunjungan</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poliklinik Tujuan <span class="text-red-500">*</span></label>
                    <select name="poliklinik_id" required id="poliklinik"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('poliklinik_id') border-red-500 @enderror"
                            @change="loadDokter">
                        <option value="">-- Pilih Poliklinik --</option>
                        @foreach($poliklinikList as $poli)
                        <option value="{{ $poli->id }}" {{ old('poliklinik_id') == $poli->id ? 'selected' : '' }}>
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
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asuransi/Penjamin</label>
                    <select name="asuransi_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Umum --</option>
                        @foreach($asuransiList as $asuransi)
                        <option value="{{ $asuransi->id }}" {{ old('asuransi_id') == $asuransi->id ? 'selected' : '' }}>
                            {{ $asuransi->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cara Bayar <span class="text-red-500">*</span></label>
                    <select name="jenis_bayar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="umum" {{ old('jenis_bayar') == 'umum' ? 'selected' : '' }}>Umum</option>
                        <option value="bpjs" {{ old('jenis_bayar') == 'bpjs' ? 'selected' : '' }}>BPJS</option>
                        <option value="asuransi" {{ old('jenis_bayar') == 'asuransi' ? 'selected' : '' }}>Asuransi</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                <textarea name="catatan" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                          placeholder="Catatan tambahan (opsional)">{{ old('catatan') }}</textarea>
            </div>
        </div>

        {{-- Buttons --}}
        <div class="flex gap-3" x-show="selectedPasien">
            <a href="{{ route('pendaftaran.dashboard') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                Daftar Kunjungan
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const hari = @js($hariIni);
    
    function pendaftaranLama() {
        return {
            searchQuery: '',
            searchResults: [],
            selectedPasien: null,
            selectedPasienId: '',
            
            async searchPasien() {
                if (this.searchQuery.length < 2) {
                    this.searchResults = [];
                    return;
                }
                
                try {
                    const response = await fetch(`{{ route('pendaftaran.pasien.search') }}?q=${encodeURIComponent(this.searchQuery)}`);
                    this.searchResults = await response.json();
                } catch (error) {
                    console.error('Error searching pasien:', error);
                }
            },
            
            selectPasien(pasien) {
                this.selectedPasien = pasien;
                this.selectedPasienId = pasien.id;
                this.searchResults = [];
                this.searchQuery = '';
            },
            
            clearSelection() {
                this.selectedPasien = null;
                this.selectedPasienId = '';
                this.searchQuery = '';
            }
        }
    }
    
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
            select.value = @js(old('dokter_id', ''));
        } catch (error) {
            console.error('Error loading dokter:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('poliklinik').value) {
            loadDokter();
        }
    });
</script>
@endpush
@endsection
