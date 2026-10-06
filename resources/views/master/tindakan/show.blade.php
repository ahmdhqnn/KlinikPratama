@extends('layouts.app')

@section('title', 'BHP Tindakan - ' . $tindakan->nama)
@section('page-title', 'BHP Tindakan: ' . $tindakan->nama)

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('master.tindakan.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Tindakan Medis</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $tindakan->nama }} ({{ $tindakan->kode }})</h2>
            <p class="text-sm text-gray-500">Bahan Habis Pakai (BHP) yang akan otomatis dipotong saat tindakan dilakukan</p>
        </div>
    </div>

    {{-- Form Tambah BHP --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Tambah BHP ke Tindakan Ini</h3>
        <form method="POST" action="{{ route('master.tindakan.bhp.store', $tindakan) }}" class="flex items-end space-x-4">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Obat / Alkes (BHP)</label>
                <select name="obat_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Pilih Obat / Alkes --</option>
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
    </div>

    {{-- Daftar BHP --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Daftar BHP Terpasang</h3>
        </div>
        <table class="w-full">
            <thead>
                <tr>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama BHP / Obat</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Jumlah Digunakan</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Satuan</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tindakan->bhp as $index => $bhp)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $bhp->obat->nama }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono">{{ $bhp->jumlah }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $bhp->obat->satuan_kecil }}</td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" action="{{ route('master.tindakan.bhp.destroy', $bhp) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                    onclick="return confirm('Hapus BHP ini dari tindakan?')">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada BHP terpasang pada tindakan ini</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
