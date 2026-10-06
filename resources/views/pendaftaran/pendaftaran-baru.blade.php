@extends('layouts.app')

@section('title', 'Pendaftaran Pasien Baru')
@section('page-title', 'Pendaftaran Pasien Baru')

@section('content')
<div class="max-w-4xl">
    <form method="POST" action="{{ route('pendaftaran.store-pasien-baru') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="jenis_pasien" value="baru">

        {{-- Data Identitas Pasien --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Data Identitas Pasien</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pasien <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" value="{{ old('nama') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('nama') border-red-500 @enderror"
                           placeholder="Nama lengkap pasien">
                    @error('nama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. KTP</label>
                    <input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('nik') border-red-500 @enderror"
                           placeholder="16 digit nomor KTP">
                    @error('nik') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                           placeholder="Kota/Kabupaten lahir">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" required max="{{ today()->format('Y-m-d') }}" id="tanggal-lahir"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('tanggal_lahir') border-red-500 @enderror"
                           onchange="calculateAge()">
                    @error('tanggal_lahir') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p id="umur-pasien" class="mt-1 text-xs text-gray-500" aria-live="polite"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin <span class="text-red-500">*</span></label>
                    <select name="jenis_kelamin" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm @error('jenis_kelamin') border-red-500 @enderror">
                        <option value="">-- Pilih Jenis Kelamin --</option>
                        <option value="L" {{ old('jenis_kelamin') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('jenis_kelamin') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    @error('jenis_kelamin') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Golongan Darah</label>
                    <select name="golongan_darah" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Pilih Golongan --</option>
                        <option value="A" {{ old('golongan_darah') == 'A' ? 'selected' : '' }}>A</option>
                        <option value="B" {{ old('golongan_darah') == 'B' ? 'selected' : '' }}>B</option>
                        <option value="AB" {{ old('golongan_darah') == 'AB' ? 'selected' : '' }}>AB</option>
                        <option value="O" {{ old('golongan_darah') == 'O' ? 'selected' : '' }}>O</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agama</label>
                    <select name="agama" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">-- Pilih Agama --</option>
                        <option value="Islam" {{ old('agama') == 'Islam' ? 'selected' : '' }}>Islam</option>
                        <option value="Kristen" {{ old('agama') == 'Kristen' ? 'selected' : '' }}>Kristen</option>
                        <option value="Katolik" {{ old('agama') == 'Katolik' ? 'selected' : '' }}>Katolik</option>
                        <option value="Hindu" {{ old('agama') == 'Hindu' ? 'selected' : '' }}>Hindu</option>
                        <option value="Buddha" {{ old('agama') == 'Buddha' ? 'selected' : '' }}>Buddha</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Ibu Kandung</label>
                    <input type="text" name="nama_ibu" value="{{ old('nama_ibu') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                           placeholder="Nama ibu kandung">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                    <input type="tel" name="telepon" value="{{ old('telepon') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                           placeholder="08xxxxxxxxxx">
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="alamat" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                          placeholder="Alamat lengkap pasien"></textarea>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                <input type="text" name="rt" value="{{ old('rt') }}" placeholder="RT" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <input type="text" name="rw" value="{{ old('rw') }}" placeholder="RW" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <input type="text" name="kelurahan" value="{{ old('kelurahan') }}" placeholder="Kelurahan/Desa" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <input type="text" name="kecamatan" value="{{ old('kecamatan') }}" placeholder="Kecamatan" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Riwayat Alergi</label>
                <textarea name="riwayat_alergi" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                          placeholder="Alergi obat, makanan, dll (jika ada)"></textarea>
            </div>
        </div>

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
                          placeholder="Catatan tambahan (opsional)"></textarea>
            </div>
        </div>

        {{-- Buttons --}}
        <div class="flex gap-3">
            <a href="{{ route('pendaftaran.dashboard') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Daftar Pasien
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
            select.value = @js(old('dokter_id', ''));
        } catch (error) {
            console.error('Error loading dokter:', error);
        }
    }

    function calculateAge() {
        const value = document.getElementById('tanggal-lahir').value;
        const output = document.getElementById('umur-pasien');
        if (!value) {
            output.textContent = '';
            return;
        }
        const birthDate = new Date(`${value}T00:00:00`);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        output.textContent = age >= 0 ? `Umur pasien: ${age} tahun` : '';
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('poliklinik').value) {
            loadDokter();
        }
    });

    calculateAge();
</script>
@endpush
@endsection
