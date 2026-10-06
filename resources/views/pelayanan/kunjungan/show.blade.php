@extends('layouts.app')

@section('title', 'Detail Kunjungan - ' . $kunjungan->no_kunjungan)
@section('page-title', 'Detail Kunjungan: ' . $kunjungan->no_kunjungan)

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('pelayanan.kunjungan.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Daftar Kunjungan</a>
        <div class="flex items-center space-x-3">
            @if($kunjungan->status === 'menunggu')
            <a href="{{ route('pelayanan.screening.show', $kunjungan) }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Proses Screening →
            </a>
            @elseif($kunjungan->status === 'pemeriksaan')
            <a href="{{ route('pelayanan.pemeriksaan.show', $kunjungan) }}" class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700">
                Lanjut Periksa Dokter →
            </a>
            @elseif($kunjungan->status === 'farmasi')
            <a href="{{ route('pelayanan.farmasi.show', $kunjungan) }}" class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700">
                Proses Farmasi →
            </a>
            @elseif($kunjungan->status === 'kasir')
            <a href="{{ route('pelayanan.kasir.show', $kunjungan) }}" class="px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg hover:bg-pink-700">
                Proses Kasir →
            </a>
            @elseif($kunjungan->status === 'selesai' && $kunjungan->tagihan)
            <a href="{{ route('pelayanan.kasir.kuitansi', $kunjungan->tagihan) }}" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                Cetak Kuitansi
            </a>
            @endif
        </div>
    </div>

    {{-- Info Kunjungan --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h3 class="text-base font-semibold text-gray-800 pb-2 border-b border-gray-100">Informasi Kunjungan</h3>
            <div>
                <span class="text-xs text-gray-400 block">No. Kunjungan</span>
                <span class="font-mono font-bold text-gray-800">{{ $kunjungan->no_kunjungan }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Tanggal Kunjungan</span>
                <span class="text-gray-800">{{ $kunjungan->tanggal->isoFormat('dddd, D MMMM Y') }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Status Kunjungan</span>
                <span class="badge badge-blue text-sm mt-1">{{ ucfirst($kunjungan->status) }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Poliklinik Tujuan</span>
                <span class="font-semibold text-gray-800">{{ $kunjungan->poliklinik?->nama ?? '-' }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Dokter Pemeriksa</span>
                <span class="text-gray-800">{{ $kunjungan->dokter?->nama ?? 'Belum ditentukan' }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Cara Bayar</span>
                <span class="badge {{ $kunjungan->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }}">
                    {{ strtoupper($kunjungan->jenis_bayar) }}
                </span>
            </div>
            @if($kunjungan->catatan)
            <div>
                <span class="text-xs text-gray-400 block">Catatan</span>
                <p class="text-sm text-gray-600">{{ $kunjungan->catatan }}</p>
            </div>
            @endif
        </div>

        {{-- Profil Pasien Singkat --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">Identitas Pasien</h3>
                <a href="{{ route('pelayanan.pasien.rekam-medis', $kunjungan->pasien) }}" class="text-xs text-purple-600 hover:underline">Lihat RME →</a>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Nama Pasien</span>
                <a href="{{ route('pelayanan.pasien.show', $kunjungan->pasien) }}" class="font-bold text-blue-600 hover:underline">
                    {{ $kunjungan->pasien->nama }}
                </a>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">No. Rekam Medis</span>
                <span class="font-mono font-bold text-gray-800">{{ $kunjungan->pasien->no_rm }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Jenis Kelamin / Umur</span>
                <span class="text-gray-800">{{ $kunjungan->pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}, {{ $kunjungan->pasien->umur }} tahun</span>
            </div>
            <div>
                <span class="text-xs text-gray-400 block">Golongan Darah</span>
                <span class="font-bold text-gray-800">{{ $kunjungan->pasien->golongan_darah ?? '-' }}</span>
            </div>
            @if($kunjungan->pasien->riwayat_alergi)
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                <span class="text-xs font-bold text-red-600 uppercase block">⚠️ Alergi:</span>
                <span class="text-xs font-medium text-red-800">{{ $kunjungan->pasien->riwayat_alergi }}</span>
            </div>
            @endif
        </div>

        {{-- Status Alur Pelayanan --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-base font-semibold text-gray-800 pb-2 border-b border-gray-100 mb-4">Progres Pelayanan</h3>
            <div class="space-y-4">
                @php
                $steps = [
                    ['label' => '1. Pendaftaran', 'done' => true],
                    ['label' => '2. Screening Tanda Vital', 'done' => (bool)$kunjungan->screening],
                    ['label' => '3. Pemeriksaan Dokter', 'done' => (bool)$kunjungan->pemeriksaan && $kunjungan->pemeriksaan->status === 'selesai'],
                    ['label' => '4. Farmasi / Resep', 'done' => (bool)$kunjungan->farmasi && $kunjungan->farmasi->status === 'selesai'],
                    ['label' => '5. Pembayaran Kasir', 'done' => $kunjungan->status === 'selesai'],
                ];
                @endphp
                @foreach($steps as $step)
                <div class="flex items-center space-x-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $step['done'] ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500' }}">
                        {{ $step['done'] ? '✓' : '•' }}
                    </div>
                    <span class="text-sm {{ $step['done'] ? 'font-medium text-gray-800' : 'text-gray-400' }}">{{ $step['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
