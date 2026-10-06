@extends('layouts.app')

@section('title', 'Indikator & BHP Lab - ' . $laboratorium->nama)
@section('page-title', 'Detail Lab: ' . $laboratorium->nama)

@section('content')
<div class="py-4 space-y-6">
    <div>
        <a href="{{ route('master.laboratorium.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Master Laboratorium</a>
        <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $laboratorium->nama }} ({{ $laboratorium->kode }})</h2>
        <p class="text-sm text-gray-500">Tarif: Rp {{ number_format($laboratorium->tarif, 0, ',', '.') }} | Poliklinik: {{ $laboratorium->poliklinik?->nama ?? 'Umum' }}</p>
    </div>

    {{-- Indikator Section --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Tambah Indikator Hasil Pemeriksaan</h3>
        <p class="text-xs text-gray-500 mb-4">Setiap pemeriksaan lab dapat memiliki parameter/indikator tersendiri dengan nilai rujukan (contoh: Golongan Darah, Hemoglobin, dll)</p>

        <form method="POST" action="{{ route('master.laboratorium.indikator.store', $laboratorium) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Indikator <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" required placeholder="Contoh: Hemoglobin, Leukosit"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                    <input type="text" name="satuan" placeholder="Contoh: g/dL, %, mg/dL"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Format Input</label>
                    <select name="format_input" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="number">Angka (Number)</option>
                        <option value="text">Teks Bebas (Text)</option>
                        <option value="select">Pilihan / Dropdown</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nilai Rujukan Min</label>
                    <input type="text" name="nilai_rujukan_min" placeholder="Contoh: 12.0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nilai Rujukan Max</label>
                    <input type="text" name="nilai_rujukan_max" placeholder="Contoh: 17.0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Opsi Dropdown (pisahkan koma)</label>
                    <input type="text" name="pilihan" placeholder="Contoh: A, B, AB, O atau Positif, Negatif"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Tambah Indikator
            </button>
        </form>

        {{-- Daftar Indikator --}}
        <div class="mt-6 border-t border-gray-100 pt-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Daftar Indikator</h4>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-left">Nama</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-left">Satuan</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-left">Nilai Rujukan</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-left">Format</th>
                            <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($laboratorium->indikator as $ind)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2.5 text-sm font-medium text-gray-800">{{ $ind->nama }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-600">{{ $ind->satuan ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-600">
                                @if($ind->nilai_rujukan_min || $ind->nilai_rujukan_max)
                                {{ $ind->nilai_rujukan_min ?? '-' }} - {{ $ind->nilai_rujukan_max ?? '-' }}
                                @else
                                -
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-gray-600">
                                <span class="badge badge-blue">{{ ucfirst($ind->format_input) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <form method="POST" action="{{ route('master.laboratorium.indikator.destroy', $ind) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                            onclick="return confirm('Hapus indikator ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-400 text-sm">Belum ada indikator terdaftar</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- BHP Lab Section --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">BHP Laboratorium</h3>
        <p class="text-xs text-gray-500 mb-4">Bahan Habis Pakai yang akan dipotong otomatis dari stok saat pemeriksaan lab ini dijalankan</p>

        <form method="POST" action="{{ route('master.laboratorium.bhp.store', $laboratorium) }}" class="flex items-end space-x-4">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Obat / Alkes (BHP)</label>
                <select name="obat_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Pilih BHP --</option>
                    @foreach($obatList as $obat)
                    <option value="{{ $obat->id }}">{{ $obat->nama }} (Stok: {{ $obat->stok }} {{ $obat->satuan_kecil }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32">
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                <input type="number" name="jumlah" step="0.01" min="0.01" value="1" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Tambah BHP
            </button>
        </form>

        <div class="mt-6 border-t border-gray-100 pt-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Daftar BHP Lab</h4>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-left">Nama BHP</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-right">Jumlah</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-left">Satuan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-2 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($laboratorium->bhp as $bhp)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-sm font-medium text-gray-800">{{ $bhp->obat->nama }}</td>
                        <td class="px-4 py-2.5 text-sm text-right font-mono">{{ $bhp->jumlah }}</td>
                        <td class="px-4 py-2.5 text-sm text-gray-600">{{ $bhp->obat->satuan_kecil }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <form method="POST" action="{{ route('master.laboratorium.bhp.destroy', $bhp) }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                        onclick="return confirm('Hapus BHP ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-400 text-sm">Belum ada BHP terpasang</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
