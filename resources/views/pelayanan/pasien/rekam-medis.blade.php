@extends('layouts.app')

@section('title', 'Rekam Medis Elektronik - ' . $pasien->nama)
@section('page-title', 'Rekam Medis: ' . $pasien->nama)

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between no-print">
        <a href="{{ route('pelayanan.pasien.show', $pasien) }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Profil Pasien</a>
        <button onclick="window.print()" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900">
            🖨️ Cetak Rekam Medis
        </button>
    </div>

    {{-- Identitas Pasien Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-xs text-blue-600 font-semibold uppercase tracking-wider">Berkas Rekam Medis Pasien</span>
                <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ $pasien->nama }}</h2>
                <div class="flex items-center space-x-4 mt-2 text-sm text-gray-600">
                    <span class="font-mono font-bold text-blue-600">{{ $pasien->no_rm }}</span>
                    <span>•</span>
                    <span>NIK: {{ $pasien->nik ?? '-' }}</span>
                    <span>•</span>
                    <span>{{ $pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}, {{ $pasien->umur }} th</span>
                    <span>•</span>
                    <span>Gol. Darah: <strong>{{ $pasien->golongan_darah ?? '-' }}</strong></span>
                </div>
            </div>
            @if($pasien->riwayat_alergi)
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
                <span class="font-bold text-xs uppercase block text-red-600">⚠️ Riwayat Alergi</span>
                <p class="font-medium mt-0.5">{{ $pasien->riwayat_alergi }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Timeline Rekam Medis --}}
    <div class="space-y-6">
        @forelse($pasien->kunjungan as $kunjungan)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Header Kunjungan --}}
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center space-x-3">
                    <span class="font-mono font-bold text-gray-800">{{ $kunjungan->no_kunjungan }}</span>
                    <span class="badge badge-blue">{{ $kunjungan->poliklinik->nama }}</span>
                    <span class="text-sm text-gray-500">Dokter: <strong>{{ $kunjungan->dokter?->nama ?? '-' }}</strong></span>
                </div>
                <div class="flex items-center space-x-3 text-sm text-gray-500">
                    <span>{{ $kunjungan->tanggal->isoFormat('dddd, D MMMM Y') }}</span>
                    <span class="badge badge-green">{{ ucfirst($kunjungan->status) }}</span>
                </div>
            </div>

            <div class="p-6 space-y-6">
                {{-- Vital Signs (Screening) --}}
                @if($kunjungan->screening)
                <div>
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Tanda Vital & Keluhan</h4>
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-3">
                        <div class="bg-gray-50 p-2.5 rounded-lg text-center">
                            <span class="text-xs text-gray-500 block">Tekanan Darah</span>
                            <span class="font-semibold text-gray-800 text-sm">
                                {{ $kunjungan->screening->td_sistole ?? '-' }}/{{ $kunjungan->screening->td_diastole ?? '-' }} mmHg
                            </span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded-lg text-center">
                            <span class="text-xs text-gray-500 block">Nadi</span>
                            <span class="font-semibold text-gray-800 text-sm">{{ $kunjungan->screening->nadi ?? '-' }} x/m</span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded-lg text-center">
                            <span class="text-xs text-gray-500 block">Suhu</span>
                            <span class="font-semibold text-gray-800 text-sm">{{ $kunjungan->screening->suhu ?? '-' }} °C</span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded-lg text-center">
                            <span class="text-xs text-gray-500 block">SpO2</span>
                            <span class="font-semibold text-gray-800 text-sm">{{ $kunjungan->screening->spo2 ?? '-' }} %</span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded-lg text-center">
                            <span class="text-xs text-gray-500 block">BB / TB</span>
                            <span class="font-semibold text-gray-800 text-sm">{{ $kunjungan->screening->berat_badan ?? '-' }} kg / {{ $kunjungan->screening->tinggi_badan ?? '-' }} cm</span>
                        </div>
                        <div class="bg-gray-50 p-2.5 rounded-lg text-center">
                            <span class="text-xs text-gray-500 block">Respirasi</span>
                            <span class="font-semibold text-gray-800 text-sm">{{ $kunjungan->screening->respirasi ?? '-' }} x/m</span>
                        </div>
                    </div>
                    @if($kunjungan->screening->keluhan)
                    <p class="text-sm text-gray-700"><strong>Keluhan:</strong> {{ $kunjungan->screening->keluhan }}</p>
                    @endif
                </div>
                @endif

                {{-- Anamnesis & Diagnosa --}}
                @if($kunjungan->pemeriksaan)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Anamnesis & Pemeriksaan Fisik</h4>
                        <div class="bg-gray-50 p-4 rounded-lg text-sm space-y-2">
                            <p><strong>Anamnesis:</strong> {{ $kunjungan->pemeriksaan->anamnesis ?? '-' }}</p>
                            <p><strong>Pemeriksaan Fisik:</strong> {{ $kunjungan->pemeriksaan->pemeriksaan_fisik ?? '-' }}</p>
                            <p><strong>Edukasi:</strong> {{ $kunjungan->pemeriksaan->edukasi ?? '-' }}</p>
                            <p><strong>Catatan Dokter:</strong> {{ $kunjungan->pemeriksaan->catatan ?? '-' }}</p>
                            @if($kunjungan->pemeriksaan->kontrol_berikutnya)
                            <p class="text-blue-600 font-semibold">📅 Janji Kontrol: {{ date('d/m/Y', strtotime($kunjungan->pemeriksaan->kontrol_berikutnya)) }}</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Diagnosa (ICD-10)</h4>
                        <div class="space-y-2">
                            @forelse($kunjungan->pemeriksaan->diagnosa as $diag)
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg text-sm">
                                <div>
                                    <span class="font-mono font-bold text-blue-700 mr-2">{{ $diag->kode_icd10 }}</span>
                                    <span class="text-gray-800 font-medium">{{ $diag->nama_diagnosa }}</span>
                                </div>
                                <span class="badge {{ $diag->jenis === 'utama' ? 'badge-blue' : 'badge-gray' }} text-xs">
                                    {{ ucfirst($diag->jenis) }}
                                </span>
                            </div>
                            @empty
                            <p class="text-sm text-gray-400 italic">Belum ada diagnosa</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                @endif

                {{-- Resep Obat & Tindakan --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Obat yang diberikan --}}
                    <div>
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Resep Obat</h4>
                        @if($kunjungan->resep && $kunjungan->resep->resepObat->count())
                        <div class="border border-gray-100 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Nama Obat</th>
                                        <th class="px-3 py-2 text-center">Jumlah</th>
                                        <th class="px-3 py-2 text-left">Aturan Pakai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($kunjungan->resep->resepObat as $item)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-gray-800">{{ $item->nama_obat }}</td>
                                        <td class="px-3 py-2 text-center font-mono">{{ $item->jumlah }} {{ $item->satuan }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $item->aturan_pakai ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <p class="text-sm text-gray-400 italic">Tidak ada resep obat</p>
                        @endif
                    </div>

                    {{-- Tindakan Medis --}}
                    <div>
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Tindakan Medis</h4>
                        @if($kunjungan->tindakanKunjungan->count())
                        <div class="space-y-1">
                            @foreach($kunjungan->tindakanKunjungan as $tk)
                            <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg text-sm">
                                <span class="font-medium text-gray-800">{{ $tk->tindakan->nama }}</span>
                                <span class="text-xs text-gray-500">{{ $tk->jumlah }}x</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-gray-400 italic">Tidak ada tindakan</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
            Pasien belum memiliki riwayat rekam medis
        </div>
        @endforelse
    </div>
</div>
@endsection
