@extends('layouts.app')

@section('title', 'Cetak Antrian')
@section('page-title', 'Cetak Antrian')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-8 text-center">
        {{-- Header --}}
        <div class="mb-8 pb-8 border-b-2 border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800">{{ config('app.name') }}</h2>
            <p class="text-sm text-gray-500 mt-1">Nomor Antrian</p>
        </div>

        {{-- Antrian Number --}}
        <div class="mb-8">
            <div class="bg-blue-600 text-white rounded-lg p-8 mb-4">
                <p class="text-sm font-medium opacity-90">Nomor Antrian</p>
                <p class="text-6xl font-bold font-mono">{{ $antrian }}</p>
            </div>
            <p class="text-sm text-gray-500">Nomor Kunjungan: {{ $kunjungan->no_kunjungan }}</p>
        </div>

        {{-- Patient Info --}}
        <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left space-y-3">
            <div class="border-b border-gray-200 pb-3">
                <p class="text-xs text-gray-500 font-medium">NAMA PASIEN</p>
                <p class="text-lg font-bold text-gray-800">{{ $kunjungan->pasien?->nama ?? '-' }}</p>
            </div>
            <div class="border-b border-gray-200 pb-3">
                <p class="text-xs text-gray-500 font-medium">NO. REKAM MEDIS</p>
                <p class="text-lg font-bold font-mono text-gray-800">{{ $kunjungan->pasien?->no_rm ?? '-' }}</p>
            </div>
            <div class="border-b border-gray-200 pb-3">
                <p class="text-xs text-gray-500 font-medium">POLIKLINIK TUJUAN</p>
                <p class="text-lg font-bold text-gray-800">{{ $kunjungan->poliklinik?->nama ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">DOKTER</p>
                <p class="text-lg font-bold text-gray-800">{{ $kunjungan->dokter?->nama ?? 'Belum Ditentukan' }}</p>
            </div>
        </div>

        {{-- Instructions --}}
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8 text-left">
            <p class="text-xs font-bold text-yellow-800 mb-2">⚠️ PETUNJUK:</p>
            <ul class="text-xs text-yellow-800 space-y-1 list-disc list-inside">
                <li>Harap tiba 10 menit sebelum waktu yang ditentukan</li>
                <li>Bawa kartu identitas dan hasil pemeriksaan sebelumnya</li>
                <li>Tunjukkan nomor antrian ini kepada petugas</li>
            </ul>
        </div>

        {{-- Date & Time --}}
        <div class="text-sm text-gray-600 mb-8">
            <p>{{ now()->isoFormat('dddd, D MMMM Y HH:mm') }}</p>
        </div>

        {{-- Print Button --}}
        <div class="flex gap-3 justify-center">
            <button onclick="window.print()" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                🖨️ Cetak
            </button>
            <a href="{{ route('pendaftaran.laporan-kunjungan') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                Kembali
            </a>
        </div>
    </div>
</div>

<style media="print">
    body {
        margin: 0;
        padding: 0;
    }
    .flex {
        display: flex;
    }
    .max-w-2xl {
        max-width: 100%;
    }
    button {
        display: none;
    }
    a {
        display: none;
    }
</style>
@endsection
