@extends('layouts.app')

@section('title', 'Kartu Stok - ' . $obat->nama)
@section('page-title', 'Kartu Stok: ' . $obat->nama)

@section('content')
<div class="py-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('master.obat.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Master Obat</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $obat->nama }} ({{ $obat->kode }})</h2>
            <p class="text-sm text-gray-500">Stok Saat Ini: <span class="font-bold text-gray-800">{{ $obat->stok }} {{ $obat->satuan_kecil }}</span> | Stok Minimum: {{ $obat->stok_minimum }} {{ $obat->satuan_kecil }}</p>
        </div>
    </div>

    {{-- Form Tambah Stok Manual --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Penambahan Stok Manual</h3>
        <form method="POST" action="{{ route('master.obat.tambah-stok', $obat) }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah ({{ $obat->satuan_kecil }}) <span class="text-red-500">*</span></label>
                <input type="number" name="jumlah" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan / Alasan</label>
                <input type="text" name="keterangan" placeholder="Contoh: Stok opname, Koreksi fisik" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Tambah Stok
            </button>
        </form>
    </div>

    {{-- Kartu Riwayat Mutasi Stok --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Riwayat Mutasi Stok (Kartu Stok)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Tanggal</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Jenis Mutasi</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Stok Sebelum</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Jumlah</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Stok Sesudah</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($mutasi as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->jenis === 'masuk' ? 'badge-green' : 'badge-red' }}">
                                {{ ucfirst($item->jenis) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-500">{{ $item->stok_sebelum }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-semibold {{ $item->jenis === 'masuk' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $item->jenis === 'masuk' ? '+' : '-' }}{{ $item->jumlah }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold text-gray-800">{{ $item->stok_sesudah }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->keterangan ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada riwayat mutasi stok</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($mutasi->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $mutasi->links() }}</div>
        @endif
    </div>
</div>
@endsection
