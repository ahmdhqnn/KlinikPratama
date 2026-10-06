@extends('layouts.app')

@section('title', 'Detail Pasien - ' . $pasien->nama)
@section('page-title', 'Profil Pasien')

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('pelayanan.pasien.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Database Pasien</a>
        <div class="flex items-center space-x-3">
            <a href="{{ route('pelayanan.kunjungan.create', ['pasien_id' => $pasien->id]) }}"
               class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                + Buka Kunjungan
            </a>
            <a href="{{ route('pelayanan.pasien.rekam-medis', $pasien) }}"
               class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700">
                Lihat Rekam Medis Lengkap
            </a>
            <a href="{{ route('pelayanan.pasien.edit', $pasien) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                Edit Data
            </a>
        </div>
    </div>

    {{-- Detail Card --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-center pb-6 border-b border-gray-100">
                <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-3">
                    {{ strtoupper(substr($pasien->nama, 0, 1)) }}
                </div>
                <h2 class="text-lg font-bold text-gray-800">{{ $pasien->nama }}</h2>
                <p class="font-mono text-blue-600 font-semibold">{{ $pasien->no_rm }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}, {{ $pasien->umur }} tahun</p>
            </div>

            <div class="pt-4 space-y-3 text-sm">
                <div>
                    <span class="text-gray-400 text-xs block">NIK</span>
                    <span class="font-mono text-gray-800">{{ $pasien->nik ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 text-xs block">Tanggal Lahir</span>
                    <span class="text-gray-800">{{ $pasien->tanggal_lahir?->isoFormat('D MMMM Y') ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 text-xs block">Golongan Darah</span>
                    <span class="font-bold text-gray-800">{{ $pasien->golongan_darah ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 text-xs block">Telepon</span>
                    <span class="text-gray-800">{{ $pasien->telepon ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 text-xs block">Alamat</span>
                    <span class="text-gray-800">{{ $pasien->alamat ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 text-xs block">Penjamin</span>
                    <span class="badge {{ $pasien->asuransi?->jenis === 'bpjs' ? 'badge-green' : 'badge-blue' }}">
                        {{ $pasien->asuransi?->nama ?? 'Umum (Bayar Sendiri)' }}
                    </span>
                    @if($pasien->no_asuransi)
                    <span class="text-xs font-mono text-gray-500 block mt-1">No: {{ $pasien->no_asuransi }}</span>
                    @endif
                </div>

                @if($pasien->riwayat_alergi)
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <span class="text-xs font-bold text-red-600 uppercase tracking-wider block">⚠️ Riwayat Alergi</span>
                    <p class="text-sm text-red-800 mt-1 font-medium">{{ $pasien->riwayat_alergi }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Riwayat Kunjungan --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800">Riwayat Kunjungan Pasien</h3>
                <span class="text-xs text-gray-500">Total: {{ $pasien->kunjungan->count() }} kunjungan</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">No. Kunjungan</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Tanggal</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Poli</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Dokter</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Status</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($pasien->kunjungan as $kunjungan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800">{{ $kunjungan->no_kunjungan }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $kunjungan->tanggal->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $kunjungan->poliklinik->nama }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $kunjungan->dokter?->nama ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $kunjungan->status === 'selesai' ? 'badge-green' : 'badge-yellow' }}">
                                    {{ ucfirst($kunjungan->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('pelayanan.kunjungan.show', $kunjungan) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                    Detail
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Pasien belum pernah melakukan kunjungan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
