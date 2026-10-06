@extends('layouts.app')

@section('title', 'Harga Khusus Penjamin - ' . $asuransi->nama)
@section('page-title', 'Harga Khusus: ' . $asuransi->nama)

@section('content')
<div class="py-4 space-y-6">
    <div>
        <a href="{{ route('master.asuransi.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Master Asuransi</a>
        <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $asuransi->nama }} ({{ $asuransi->kode }})</h2>
        <p class="text-sm text-gray-500">Atur harga khusus obat untuk pasien dengan penjamin/asuransi ini</p>
    </div>

    {{-- Form Tambah Harga Khusus --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Set Harga Khusus Obat</h3>
        <form method="POST" action="{{ route('master.asuransi.harga.store', $asuransi) }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex-1 min-w-[250px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Obat</label>
                <select name="obat_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Pilih Obat --</option>
                    @foreach($obatList as $obat)
                    <option value="{{ $obat->id }}">{{ $obat->nama }} (Harga Normal: Rp {{ number_format($obat->harga_jual, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Khusus (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="harga_khusus" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Simpan Harga
            </button>
        </form>
    </div>

    {{-- Daftar Harga Khusus --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Daftar Harga Khusus Terdaftar</h3>
        </div>
        <table class="w-full">
            <thead>
                <tr>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-left">Nama Obat</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-right">Harga Normal</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-right">Harga Khusus</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($asuransi->asuransiHarga as $harga)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $harga->obat->nama }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-500 line-through">Rp {{ number_format($harga->obat->harga_jual, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-600">Rp {{ number_format($harga->harga_khusus, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" action="{{ route('master.asuransi.harga.destroy', $harga) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                    onclick="return confirm('Hapus harga khusus ini?')">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada harga khusus untuk asuransi ini</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
