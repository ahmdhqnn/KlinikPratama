@extends('layouts.app')

@section('title', 'Screening Pasien - ' . ($kunjungan->pasien?->nama ?? 'Pasien'))
@section('page-title', 'Screening & Tanda Vital: ' . ($kunjungan->pasien?->nama ?? 'Pasien'))

@section('content')
<div class="py-4 max-w-4xl space-y-6" x-data="{ mode: 'lengkap' }">
    <div class="flex items-center justify-between">
        <a href="{{ route('pelayanan.screening.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Antrian Screening</a>
        <div class="flex items-center space-x-2 bg-gray-100 p-1 rounded-lg">
            <button type="button" @click="mode = 'sederhana'" :class="mode === 'sederhana' ? 'bg-white shadow-sm font-semibold text-gray-800' : 'text-gray-500'" class="px-3 py-1.5 rounded-md text-xs transition-colors">
                Model Sederhana
            </button>
            <button type="button" @click="mode = 'lengkap'" :class="mode === 'lengkap' ? 'bg-white shadow-sm font-semibold text-gray-800' : 'text-gray-500'" class="px-3 py-1.5 rounded-md text-xs transition-colors">
                Model Lengkap (Asesmen)
            </button>
        </div>
    </div>

    {{-- Ringkasan Pasien --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-bold text-gray-800">{{ $kunjungan->pasien?->nama ?? 'Pasien' }}</h3>
            <p class="text-sm text-gray-500 font-mono">{{ $kunjungan->pasien?->no_rm ?? '-' }} | {{ ($kunjungan->pasien?->jenis_kelamin === 'L') ? 'Laki-laki' : 'Perempuan' }}, {{ $kunjungan->pasien?->umur ?? '-' }} th | Gol. Darah: {{ $kunjungan->pasien?->golongan_darah ?? '-' }}</p>
        </div>
        <div class="text-right">
            <span class="badge badge-blue">{{ $kunjungan->poliklinik?->nama ?? '-' }}</span>
            <p class="text-xs text-gray-500 mt-1">Dokter: {{ $kunjungan->dokter?->nama ?? 'Belum ditentukan' }}</p>
        </div>
    </div>

    {{-- Form Screening --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('pelayanan.kunjungan.screening.store', $kunjungan) }}">
            @csrf

            {{-- 1. Tanda Vital --}}
            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
                1. Tanda-Tanda Vital (Vital Signs)
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">TD Sistole (mmHg)</label>
                    <input type="number" name="td_sistole" value="{{ old('td_sistole', $kunjungan->screening?->td_sistole) }}" placeholder="120"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">TD Diastole (mmHg)</label>
                    <input type="number" name="td_diastole" value="{{ old('td_diastole', $kunjungan->screening?->td_diastole) }}" placeholder="80"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nadi (kali/menit)</label>
                    <input type="number" name="nadi" value="{{ old('nadi', $kunjungan->screening?->nadi) }}" placeholder="80"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Suhu Tubuh (°C)</label>
                    <input type="number" step="0.1" name="suhu" value="{{ old('suhu', $kunjungan->screening?->suhu) }}" placeholder="36.5"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Berat Badan (kg)</label>
                    <input type="number" step="0.1" name="berat_badan" value="{{ old('berat_badan', $kunjungan->screening?->berat_badan) }}" placeholder="65"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tinggi Badan (cm)</label>
                    <input type="number" step="0.1" name="tinggi_badan" value="{{ old('tinggi_badan', $kunjungan->screening?->tinggi_badan) }}" placeholder="170"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Lingkar Perut (cm)</label>
                    <input type="number" step="0.1" name="lingkar_perut" value="{{ old('lingkar_perut', $kunjungan->screening?->lingkar_perut) }}" placeholder="80"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">SpO2 (%)</label>
                    <input type="number" name="spo2" value="{{ old('spo2', $kunjungan->screening?->spo2) }}" placeholder="98"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Respirasi (kali/menit)</label>
                    <input type="number" name="respirasi" value="{{ old('respirasi', $kunjungan->screening?->respirasi) }}" placeholder="20"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            {{-- 2. Keluhan Pasien --}}
            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
                2. Keluhan Utama Pasien
            </h4>
            <div class="mb-6">
                <textarea name="keluhan" rows="3" placeholder="Tuliskan keluhan yang dirasakan pasien saat ini..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('keluhan', $kunjungan->screening?->keluhan) }}</textarea>
            </div>

            {{-- 3. Bagian Asesmen Lengkap (bisa disembunyikan dalam mode sederhana) --}}
            <div x-show="mode === 'lengkap'">
                <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
                    3. Asesmen Keperawatan (Model Lengkap)
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Riwayat Penyakit Kronis</label>
                        <textarea name="riwayat_penyakit" rows="2" placeholder="Hipertensi, Diabetes, Asma, dll"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('riwayat_penyakit', $kunjungan->screening?->riwayat_penyakit) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Riwayat Alergi</label>
                        <textarea name="riwayat_alergi" rows="2" placeholder="Obat, Makanan, Cuaca"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('riwayat_alergi', $kunjungan->screening?->riwayat_alergi ?? $kunjungan->pasien?->riwayat_alergi) }}</textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Skrining Risiko Jatuh</label>
                        <select name="risiko_jatuh" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="rendah" {{ ($kunjungan->screening?->risiko_jatuh === 'rendah') ? 'selected' : '' }}>Risiko Rendah</option>
                            <option value="sedang" {{ ($kunjungan->screening?->risiko_jatuh === 'sedang') ? 'selected' : '' }}>Risiko Sedang</option>
                            <option value="tinggi" {{ ($kunjungan->screening?->risiko_jatuh === 'tinggi') ? 'selected' : '' }}>Risiko Tinggi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Skala Nyeri (0-10)</label>
                        <select name="risiko_nyeri" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="0 - Tidak Nyeri">0 - Tidak Nyeri</option>
                            <option value="1-3 - Nyeri Ringan">1-3 - Nyeri Ringan</option>
                            <option value="4-6 - Nyeri Sedang">4-6 - Nyeri Sedang</option>
                            <option value="7-10 - Nyeri Berat">7-10 - Nyeri Berat</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Skrining Status Gizi</label>
                        <select name="skrining_gizi" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="normal">Status Gizi Normal</option>
                            <option value="kurang">Gizi Kurang</option>
                            <option value="lebih">Gizi Lebih / Obesitas</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Jenis Alergi</label>
                        <input type="text" name="alergi_jenis" value="{{ old('alergi_jenis', $kunjungan->screening?->alergi_jenis) }}" placeholder="Makanan, obat, atau lainnya"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Reaksi Alergi (opsional)</label>
                        <input type="text" name="alergi_reaksi" value="{{ old('alergi_reaksi', $kunjungan->screening?->alergi_reaksi) }}" placeholder="Gatal-gatal, sesak, dll."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nama Penyakit</label>
                        <input type="text" name="penyakit_nama" value="{{ old('penyakit_nama', $kunjungan->screening?->penyakit_nama) }}" placeholder="Hipertensi, diabetes, dll."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Keterangan Penyakit</label>
                        <input type="text" name="penyakit_keterangan" value="{{ old('penyakit_keterangan', $kunjungan->screening?->penyakit_keterangan) }}" placeholder="Sejak kapan atau catatan lain"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>

                <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
                    Skrining Visual / Triase Awal
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    @foreach([
                        'nyeri_dada' => ['Nyeri dada', ['tidak' => 'Tidak', 'ya' => 'Ya']],
                        'kejang' => ['Kejang', ['tidak' => 'Tidak', 'ya' => 'Ya']],
                        'nadi_teraba' => ['Keterabaan nadi', ['teraba' => 'Teraba', 'tidak_teraba' => 'Tidak teraba']],
                        'pola_pernapasan' => ['Pola pernapasan', ['normal' => 'Normal', 'tidak_normal' => 'Tidak normal']],
                        'kesadaran' => ['Kesadaran', ['sadar' => 'Sadar', 'menurun' => 'Menurun', 'tidak_sadar' => 'Tidak sadar']],
                        'kondisi_psikiatri' => ['Kondisi psikiatri', ['normal' => 'Normal', 'terganggu' => 'Terganggu']],
                        'risiko_jatuh_visual' => ['Risiko jatuh', ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi']],
                    ] as $name => [$label, $options])
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ $label }}</label>
                        <select name="{{ $name }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            @foreach($options as $value => $optionLabel)
                            <option value="{{ $value }}" @selected(old($name, $kunjungan->screening?->{$name}) === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Petugas Screening --}}
            <div class="mb-6 pt-4 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">Petugas Screening (Perawat / Bidan)</label>
                <select name="petugas_id" class="w-full md:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">-- Pilih Petugas --</option>
                    @foreach($petugasList as $petugas)
                    <option value="{{ $petugas->id }}" {{ ($kunjungan->screening?->petugas_id == $petugas->id) ? 'selected' : '' }}>
                         {{ $petugas?->nama ?? '-' }} ({{ ucfirst($petugas?->jabatan ?? '') }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-100">
                <a href="{{ route('pelayanan.screening.index') }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Simpan & Teruskan ke Dokter
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
