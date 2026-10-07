@extends('layouts.app')

@section('title', 'Pemeriksaan - ' . $kunjungan->pasien->nama)
@section('page-title', 'Pemeriksaan Dokter: ' . $kunjungan->pasien->nama)

@section('content')
<div class="py-4 space-y-6" x-data="pemeriksaanPage()">
    {{-- Header Pasien Info Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-purple-100 text-purple-700 rounded-xl flex items-center justify-center font-bold text-lg">
                    {{ strtoupper(substr($kunjungan->pasien->nama, 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">{{ $kunjungan->pasien->nama }}</h2>
                    <div class="flex items-center space-x-3 text-xs text-gray-500 mt-0.5">
                        <span class="font-mono font-semibold text-blue-600">{{ $kunjungan->pasien->no_rm }}</span>
                        <span>•</span>
                        <span>{{ $kunjungan->pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}, {{ $kunjungan->pasien->umur }} th</span>
                        <span>•</span>
                        <span>Gol: <strong>{{ $kunjungan->pasien->golongan_darah ?? '-' }}</strong></span>
                        <span>•</span>
                        <span class="badge {{ $kunjungan->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }} text-[10px]">{{ strtoupper($kunjungan->jenis_bayar) }}</span>
                    </div>
                </div>
            </div>

            {{-- Vital Signs Quick View (from Screening) --}}
            @if($kunjungan->screening)
            <div class="flex items-center space-x-2 bg-gray-50 p-2.5 rounded-lg border border-gray-100 text-xs">
                <span class="font-bold text-gray-700">Tanda Vital:</span>
                <span class="badge badge-gray">TD: {{ $kunjungan->screening->td_sistole ?? '-' }}/{{ $kunjungan->screening->td_diastole ?? '-' }}</span>
                <span class="badge badge-gray">N: {{ $kunjungan->screening->nadi ?? '-' }}x</span>
                <span class="badge badge-gray">S: {{ $kunjungan->screening->suhu ?? '-' }}°C</span>
                <span class="badge badge-gray">SpO2: {{ $kunjungan->screening->spo2 ?? '-' }}%</span>
            </div>
            @endif

            <div class="flex items-center space-x-2">
                <a href="{{ route('pelayanan.pasien.rekam-medis', $kunjungan->pasien) }}" target="_blank"
                   class="px-3 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200">
                    📜 Riwayat RME
                </a>
                <form method="POST" action="{{ route('pelayanan.pemeriksaan.selesai', $kunjungan) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 shadow-sm"
                            onclick="return confirm('Selesaikan pemeriksaan dokter? Pasien akan diteruskan ke tahap berikutnya.')">
                        ✓ Selesai Periksa
                    </button>
                </form>
            </div>
        </div>

        {{-- Warning Alergi Pasien --}}
        @if($kunjungan->pasien->riwayat_alergi)
        <div class="mt-3 p-2.5 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-2 text-xs text-red-800">
            <span class="font-bold">⚠️ PERINGATAN ALERGI:</span>
            <span>{{ $kunjungan->pasien->riwayat_alergi }}</span>
        </div>
        @endif
    </div>

    {{-- Tabs Navigasi Pemeriksaan --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 flex overflow-x-auto">
            <button type="button" @click="activeTab = 'anamnesis'"
                    :class="activeTab === 'anamnesis' ? 'border-b-2 border-purple-600 text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm whitespace-nowrap transition-colors">
                1. Anamnesis & Fisik
            </button>
            <button type="button" @click="activeTab = 'diagnosa'"
                    :class="activeTab === 'diagnosa' ? 'border-b-2 border-purple-600 text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm whitespace-nowrap transition-colors flex items-center">
                2. Diagnosa ICD-10
                <span class="badge badge-purple ml-2 text-[10px]">{{ $kunjungan->pemeriksaan?->diagnosa->count() ?? 0 }}</span>
            </button>
            <button type="button" @click="activeTab = 'tindakan'"
                    :class="activeTab === 'tindakan' ? 'border-b-2 border-purple-600 text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm whitespace-nowrap transition-colors flex items-center">
                3. Tindakan Medis
                <span class="badge badge-blue ml-2 text-[10px]">{{ $kunjungan->tindakanKunjungan->count() }}</span>
            </button>
            <button type="button" @click="activeTab = 'resep'"
                    :class="activeTab === 'resep' ? 'border-b-2 border-purple-600 text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm whitespace-nowrap transition-colors flex items-center">
                4. Resep Obat
                <span class="badge badge-orange ml-2 text-[10px]">{{ $kunjungan->resep?->resepObat->count() ?? 0 }}</span>
            </button>
            <button type="button" @click="activeTab = 'surat'"
                    :class="activeTab === 'surat' ? 'border-b-2 border-purple-600 text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm whitespace-nowrap transition-colors">
                5. Surat Medis
            </button>
            <button type="button" @click="activeTab = 'rujukan'"
                    :class="activeTab === 'rujukan' ? 'border-b-2 border-purple-600 text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm whitespace-nowrap transition-colors">
                6. Rujukan Internal
            </button>
        </div>

        {{-- Tab 1: Anamnesis & Pemeriksaan Fisik --}}
        <div x-show="activeTab === 'anamnesis'" class="p-6">
            <form method="POST" action="{{ route('pelayanan.pemeriksaan.store', $kunjungan) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dokter Pemeriksa</label>
                        <select name="dokter_id" class="w-full md:w-80 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Pilih Dokter --</option>
                            @foreach($dokterList as $d)
                            <option value="{{ $d->id }}" {{ ($kunjungan->pemeriksaan?->dokter_id ?? $kunjungan->dokter_id) == $d->id ? 'selected' : '' }}>
                                {{ $d->nama }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Anamnesis (Keluhan, Riwayat Penyakit Sekarang)</label>
                            {{-- AI Diagnostic Assistant Button (from KlikMedis tutorial) --}}
                            <button type="button" @click="suggestIcd10()" class="text-xs bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold px-2.5 py-1 rounded-md flex items-center">
                                ✨ Rekomendasi ICD-10 Berdasarkan Anamnesis
                            </button>
                        </div>
                        <textarea name="anamnesis" x-model="anamnesisText" rows="4" placeholder="Pasien mengeluh demam sejak 3 hari, batuk berdahak, pilek..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>

                    {{-- AI Suggestion Box --}}
                    <div x-show="showAiSuggestions" class="p-4 bg-indigo-50 border border-indigo-200 rounded-xl space-y-2" style="display:none">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-indigo-900 flex items-center">
                                🤖 Rekomendasi Diagnosis AI (Berdasarkan Kata Kunci Anamnesis):
                            </p>
                            <button type="button" @click="showAiSuggestions = false" class="text-indigo-400 hover:text-indigo-600 text-xs">✕</button>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <template x-for="item in aiSuggestions" :key="item.kode">
                                <button type="button" @click="applySuggestion(item)"
                                        class="px-2.5 py-1 bg-white hover:bg-indigo-600 hover:text-white border border-indigo-200 text-indigo-800 rounded-md text-xs font-medium transition-colors shadow-sm">
                                    <span class="font-mono font-bold" x-text="item.kode"></span> - <span x-text="item.nama"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pemeriksaan Fisik</label>
                        <textarea name="pemeriksaan_fisik" rows="3" placeholder="Kepala, Leher, Thorax, Abdomen, Ekstremitas..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">{{ old('pemeriksaan_fisik', $kunjungan->pemeriksaan?->pemeriksaan_fisik) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jadwal Kontrol Berikutnya (Janji Kontrol)</label>
                            <input type="date" name="kontrol_berikutnya" value="{{ old('kontrol_berikutnya', $kunjungan->pemeriksaan?->kontrol_berikutnya) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Khusus Dokter</label>
                            <input type="text" name="catatan" value="{{ old('catatan', $kunjungan->pemeriksaan?->catatan) }}" placeholder="Catatan internal"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Edukasi Pasien</label>
                        <textarea name="edukasi" rows="3" placeholder="Anjuran pola makan, cara penggunaan obat, larangan, dan jadwal kontrol"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">{{ old('edukasi', $kunjungan->pemeriksaan?->edukasi) }}</textarea>
                    </div>

                    <div class="flex justify-end pt-3">
                        <button type="submit" class="px-5 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">
                            Simpan Anamnesis & Fisik
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Tab 2: Diagnosa ICD-10 --}}
        <div x-show="activeTab === 'diagnosa'" class="p-6 space-y-6" style="display:none">
            {{-- Form Tambah Diagnosa --}}
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Tambah Diagnosis ICD-10</h4>
                <form method="POST" action="{{ route('pelayanan.pemeriksaan.diagnosa.store', $kunjungan) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Kode ICD-10 <span class="text-red-500">*</span></label>
                            <input type="text" name="kode_icd10" x-model="selectedIcdCode" required placeholder="J00, A09, I10"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono uppercase">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nama Diagnosis <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_diagnosa" x-model="selectedIcdName" required placeholder="Common cold, Hipertensi..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Jenis Diagnosis</label>
                            <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="utama">Diagnosis Utama</option>
                                <option value="tambahan">Diagnosis Tambahan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nama Obat Luar</label>
                            <input type="text" name="nama_obat" placeholder="Isi jika resep luar"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>

                    {{-- Search helper --}}
                    <div class="relative">
                        <input type="text" placeholder="Ketik untuk mencari referensi ICD-10..." @input="searchIcd($event.target.value)"
                               class="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-xs bg-white">
                        <div x-show="icdSearchResults.length > 0" class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
                            <template x-for="r in icdSearchResults" :key="r.kode">
                                <div @click="pickIcd(r.kode, r.nama)" class="px-3 py-2 hover:bg-purple-50 cursor-pointer text-xs flex justify-between border-b border-gray-50">
                                    <span class="font-mono font-bold text-purple-700" x-text="r.kode"></span>
                                    <span class="text-gray-700" x-text="r.nama"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700">
                            + Tambah Diagnosis
                        </button>
                    </div>
                </form>
            </div>

            {{-- Daftar Diagnosa yang Sudah Dimasukkan --}}
            <div>
                <h4 class="text-sm font-bold text-gray-800 mb-3">Diagnosis Pasien Saat Ini</h4>
                <div class="space-y-2">
                    @forelse($kunjungan->pemeriksaan?->diagnosa ?? [] as $diag)
                    <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="font-mono font-bold text-purple-700 bg-purple-50 px-2 py-1 rounded">{{ $diag->kode_icd10 }}</span>
                            <span class="text-sm font-medium text-gray-800">{{ $diag->nama_diagnosa }}</span>
                            <span class="badge {{ $diag->jenis === 'utama' ? 'badge-blue' : 'badge-gray' }} text-xs">
                                {{ ucfirst($diag->jenis) }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('pelayanan.pemeriksaan.diagnosa.destroy', $diag) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium" onclick="return confirm('Hapus diagnosis ini?')">Hapus</button>
                        </form>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">Belum ada diagnosis ditambahkan</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab 3: Tindakan Medis --}}
        <div x-show="activeTab === 'tindakan'" class="p-6 space-y-6" style="display:none">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Input Tindakan Medis</h4>
                <form method="POST" action="{{ route('pelayanan.pemeriksaan.tindakan.store', $kunjungan) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Tindakan</label>
                        <select name="tindakan_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Pilih Tindakan Medis --</option>
                            @foreach($tindakanList as $tindakan)
                            <option value="{{ $tindakan->id }}">{{ $tindakan->nama }} (Rp {{ number_format($tindakan->tarif, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Jumlah</label>
                        <input type="number" name="jumlah" value="1" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700">
                        + Tambah Tindakan
                    </button>
                </form>
            </div>

            <div>
                <h4 class="text-sm font-bold text-gray-800 mb-3">Tindakan yang Diberikan</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-left">Nama Tindakan</th>
                            <th class="px-4 py-2 text-center">Jumlah</th>
                            <th class="px-4 py-2 text-right">Tarif</th>
                            <th class="px-4 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($kunjungan->tindakanKunjungan as $tk)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $tk->tindakan?->nama ?? 'Tindakan dihapus' }}</td>
                            <td class="px-4 py-3 text-center">{{ $tk->jumlah }}x</td>
                            <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($tk->tarif, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" action="{{ route('pelayanan.pemeriksaan.tindakan.destroy', $tk) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs" onclick="return confirm('Hapus tindakan ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-400 text-xs">Belum ada tindakan medis</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 4: Resep Obat --}}
        <div x-show="activeTab === 'resep'" class="p-6 space-y-6" style="display:none">
            {{-- Form Resep Obat Jadi & Racikan & Resep Luar --}}
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Tambah Resep Obat (Obat Jadi / Racikan / Resep Luar)</h4>
                <form method="POST" action="{{ route('pelayanan.pemeriksaan.resep.store', $kunjungan) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Jenis Resep</label>
                            <select name="jenis" x-model="resepJenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="jadi">Obat Jadi</option>
                                <option value="racikan">Obat Racikan</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Obat (Stok Klinik)</label>
                            <select name="obat_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">-- Pilih Obat --</option>
                                @foreach($obatList as $obat)
                                <option value="{{ $obat->id }}">{{ $obat->nama }} (Stok: {{ $obat->stok }} {{ $obat->satuan_kecil }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Jumlah</label>
                            <input type="number" name="jumlah" value="10" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Aturan Pakai (Signa)</label>
                            <input type="text" name="aturan_pakai" placeholder="Contoh: 3 x 1 tablet sesudah makan"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Catatan Khusus</label>
                            <input type="text" name="catatan" placeholder="Contoh: Habiskan, bila demam"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>

                    {{-- Resep Luar Checkbox --}}
                    <div class="flex items-center">
                        <input type="checkbox" name="is_resep_luar" id="is_resep_luar" value="1" class="w-4 h-4 text-orange-600 rounded">
                        <label for="is_resep_luar" class="ml-2 text-xs font-medium text-orange-700">Resep Luar (Obat tidak tersedia di klinik, dicetak untuk ditebus di apotek luar)</label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-xs font-semibold hover:bg-orange-700">
                            + Masukkan ke Resep
                        </button>
                    </div>
                </form>
            </div>

            {{-- Daftar Obat dalam Resep --}}
            <div>
                <h4 class="text-sm font-bold text-gray-800 mb-3">Daftar Obat dalam Resep Pasien</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-left">Nama Obat</th>
                            <th class="px-4 py-2 text-center">Jenis</th>
                            <th class="px-4 py-2 text-center">Jumlah</th>
                            <th class="px-4 py-2 text-left">Aturan Pakai</th>
                            <th class="px-4 py-2 text-center">Tipe</th>
                            <th class="px-4 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($kunjungan->resep?->resepObat ?? [] as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $item->nama_obat }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $item->jenis === 'jadi' ? 'badge-blue' : 'badge-purple' }} text-[10px]">
                                    {{ ucfirst($item->jenis) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-bold">{{ $item->jumlah }} {{ $item->satuan }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $item->aturan_pakai ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($item->is_resep_luar)
                                <span class="badge badge-yellow text-[10px]">Resep Luar</span>
                                @else
                                <span class="badge badge-green text-[10px]">Klinik</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" action="{{ route('pelayanan.pemeriksaan.resep.destroy', $item) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs" onclick="return confirm('Hapus obat ini dari resep?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-4 text-center text-gray-400 text-xs">Belum ada obat dalam resep</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 5: Surat Medis --}}
        <div x-show="activeTab === 'surat'" class="p-6 space-y-6" style="display:none">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Buat Surat Keterangan Medis</h4>
                <form method="POST" action="{{ route('pelayanan.pemeriksaan.surat.store', $kunjungan) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Jenis Surat <span class="text-red-500">*</span></label>
                            <select name="jenis" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="sakit">Surat Keterangan Sakit</option>
                                <option value="sehat">Surat Keterangan Sehat</option>
                                <option value="rujukan">Surat Rujukan Eksternal</option>
                                <option value="lainnya">Surat Keterangan Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nomor Surat</label>
                            <input type="text" name="nomor_surat" placeholder="Contoh: SKD/001/X/2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Surat <span class="text-red-500">*</span></label>
                            <input type="date" name="tanggal" value="{{ today()->toDateString() }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Isi Keterangan Surat</label>
                        <textarea name="konten" rows="3" placeholder="Menerangkan bahwa pasien beristirahat selama 3 (tiga) hari..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700">
                            + Buat Surat Medis
                        </button>
                    </div>
                </form>
            </div>

            <div>
                <h4 class="text-sm font-bold text-gray-800 mb-3">Surat Medis yang Diterbitkan</h4>
                <div class="space-y-2">
                    @forelse($kunjungan->suratMedis as $surat)
                    <div class="p-3 bg-white border border-gray-200 rounded-lg flex items-center justify-between text-sm">
                        <div>
                            <span class="badge badge-purple uppercase text-xs">{{ $surat->jenis }}</span>
                            <span class="font-medium text-gray-800 ml-2">No: {{ $surat->nomor_surat ?? '-' }}</span>
                            <p class="text-xs text-gray-500 mt-1">{{ $surat->konten }}</p>
                        </div>
                        <span class="text-xs text-gray-400">{{ $surat->tanggal?->format('d/m/Y') ?? '-' }}</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">Belum ada surat medis diterbitkan</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab 6: Rujukan Internal --}}
        <div x-show="activeTab === 'rujukan'" class="p-6 space-y-6" style="display:none">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Buat Rujukan Internal (Antar Poli)</h4>
                <form method="POST" action="{{ route('pelayanan.pemeriksaan.rujukan.store', $kunjungan) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Rujuk ke Poliklinik <span class="text-red-500">*</span></label>
                        <select name="ke_poli_id" required class="w-full md:w-80 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Pilih Poliklinik Tujuan --</option>
                            @foreach($poliklinikList as $p)
                            @if($p->id !== $kunjungan->poliklinik_id)
                            <option value="{{ $p->id }}">{{ $p->nama }}</option>
                            @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Catatan Konsul / Alasan Rujukan</label>
                        <textarea name="catatan" rows="2" placeholder="Mohon konsultasi dan penanganan lebih lanjut untuk..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700">
                            + Kirim Rujukan Internal
                        </button>
                    </div>
                </form>
            </div>

            <div>
                <h4 class="text-sm font-bold text-gray-800 mb-3">Riwayat Rujukan Internal</h4>
                <div class="space-y-2">
                    @forelse($kunjungan->rujukanInternal as $rujukan)
                    <div class="p-3 bg-white border border-gray-200 rounded-lg flex items-center justify-between text-sm">
                        <div>
                            <span class="font-medium text-gray-800">Dari: {{ $rujukan->dariPoli?->nama ?? '-' }} → Ke: {{ $rujukan->kePoli?->nama ?? '-' }}</span>
                            <p class="text-xs text-gray-500 mt-1">{{ $rujukan->catatan ?? '-' }}</p>
                        </div>
                        <span class="badge badge-yellow text-xs">{{ ucfirst($rujukan->status) }}</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 italic py-4 text-center">Tidak ada rujukan internal</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pemeriksaanPage() {
    return {
        activeTab: 'anamnesis',
        anamnesisText: `{{ addslashes($kunjungan->pemeriksaan?->anamnesis ?? $kunjungan->screening?->keluhan ?? '') }}`,
        showAiSuggestions: false,
        aiSuggestions: [],
        selectedIcdCode: '',
        selectedIcdName: '',
        resepJenis: 'jadi',
        icdSearchResults: [],

        suggestIcd10() {
            if (!this.anamnesisText) {
                alert('Tuliskan keluhan / anamnesis terlebih dahulu.');
                return;
            }

            // Keyword-based AI assistant matching common symptoms
            const text = this.anamnesisText.toLowerCase();
            const suggestions = [];

            if (text.includes('demam') || text.includes('panas')) {
                suggestions.push({ kode: 'R50.9', nama: 'Demam, tidak spesifik' });
            }
            if (text.includes('batuk') || text.includes('dahak')) {
                suggestions.push({ kode: 'R05', nama: 'Batuk' });
                suggestions.push({ kode: 'J06.9', nama: 'Infeksi saluran napas atas akut' });
            }
            if (text.includes('pilek') || text.includes('flu') || text.includes('hidung')) {
                suggestions.push({ kode: 'J00', nama: 'Nasofaringitis akut (common cold)' });
            }
            if (text.includes('diare') || text.includes('mencret') || text.includes('mual') || text.includes('muntah')) {
                suggestions.push({ kode: 'A09', nama: 'Diare dan gastroenteritis' });
            }
            if (text.includes('lambung') || text.includes('maag') || text.includes('nyeri perut')) {
                suggestions.push({ kode: 'K30', nama: 'Dispepsia fungsional' });
                suggestions.push({ kode: 'K21', nama: 'Penyakit refluks gastroesofagus' });
            }
            if (text.includes('tensi') || text.includes('darah tinggi') || text.includes('pusing')) {
                suggestions.push({ kode: 'I10', nama: 'Hipertensi esensial' });
                suggestions.push({ kode: 'R51', nama: 'Sakit kepala' });
            }
            if (text.includes('gula') || text.includes('kencing manis') || text.includes('diabetes')) {
                suggestions.push({ kode: 'E11', nama: 'Diabetes melitus tipe 2' });
            }
            if (suggestions.length === 0) {
                suggestions.push({ kode: 'Z00.0', nama: 'Pemeriksaan umum / check-up' });
                suggestions.push({ kode: 'B34.9', nama: 'Infeksi virus, tidak spesifik' });
            }

            this.aiSuggestions = suggestions;
            this.showAiSuggestions = true;
        },

        applySuggestion(item) {
            this.selectedIcdCode = item.kode;
            this.selectedIcdName = item.nama;
            this.activeTab = 'diagnosa';
        },

        searchIcd(query) {
            if (query.length < 2) {
                this.icdSearchResults = [];
                return;
            }
            fetch(`{{ route('pelayanan.icd10.search') }}?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => this.icdSearchResults = data);
        },

        pickIcd(kode, nama) {
            this.selectedIcdCode = kode;
            this.selectedIcdName = nama;
            this.icdSearchResults = [];
        }
    };
}
</script>
@endpush
