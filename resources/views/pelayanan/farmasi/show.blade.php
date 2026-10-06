@extends('layouts.app')

@section('title', 'Dispensing Obat - ' . $kunjungan->pasien->nama)
@section('page-title', 'Farmasi: ' . $kunjungan->pasien->nama)

@section('content')
<div class="py-4 space-y-6" x-data="{ showEtiket: false, etiketItem: null }">
    <div class="flex items-center justify-between">
        <a href="{{ route('pelayanan.farmasi.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Antrian Farmasi</a>
    </div>

    {{-- Info Pasien & Resep --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-xs text-orange-600 font-semibold uppercase tracking-wider">Dispensing Farmasi</span>
            <h2 class="text-xl font-bold text-gray-800 mt-0.5">{{ $kunjungan->pasien->nama }}</h2>
            <div class="flex items-center space-x-3 text-xs text-gray-500 mt-1">
                <span class="font-mono font-bold text-blue-600">{{ $kunjungan->pasien->no_rm }}</span>
                <span>•</span>
                <span>No. Resep: <strong class="font-mono text-gray-800">{{ $kunjungan->resep?->no_resep }}</strong></span>
                <span>•</span>
                <span>Dokter: {{ $kunjungan->dokter?->nama ?? '-' }}</span>
            </div>
        </div>

        @if($kunjungan->pasien->riwayat_alergi)
        <div class="bg-red-50 border border-red-200 rounded-lg p-2.5 text-xs text-red-800">
            <span class="font-bold">⚠️ Alergi:</span> {{ $kunjungan->pasien->riwayat_alergi }}
        </div>
        @endif
    </div>

    {{-- Form Verifikasi Penyerahan Obat --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Verifikasi & Penyiapan Obat</h3>
            <span class="text-xs text-gray-500">Stok obat akan otomatis berkurang setelah selesai diproses</span>
        </div>

        <form method="POST" action="{{ route('pelayanan.farmasi.store', $kunjungan) }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-3 text-left">Nama Obat</th>
                            <th class="px-4 py-3 text-center">Permintaan Dokter</th>
                            <th class="px-4 py-3 text-center">Stok Tersedia</th>
                            <th class="px-4 py-3 text-center">Jumlah Diserahkan</th>
                            <th class="px-4 py-3 text-left">Aturan Pakai (Signa)</th>
                            <th class="px-4 py-3 text-center">Etiket</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($kunjungan->resep?->resepObat ?? [] as $index => $item)
                        @php
                        $obat = $item->obat;
                        $stokCukup = $obat && $obat->stok >= $item->jumlah;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $item->nama_obat }}</div>
                                <span class="badge {{ $item->jenis === 'jadi' ? 'badge-blue' : 'badge-purple' }} text-[10px]">
                                    {{ ucfirst($item->jenis) }}
                                </span>
                                @if($item->is_resep_luar)
                                <span class="badge badge-yellow text-[10px] ml-1">Resep Luar</span>
                                @endif
                                <input type="hidden" name="items[{{ $index }}][resep_obat_id]" value="{{ $item->id }}">
                                <input type="hidden" name="items[{{ $index }}][obat_id]" value="{{ $item->obat_id ?? 0 }}">
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-semibold text-gray-800">
                                {{ $item->jumlah }} {{ $item->satuan }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono">
                                @if($obat)
                                <span class="font-semibold {{ $stokCukup ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $obat->stok }} {{ $obat->satuan_kecil }}
                                </span>
                                @else
                                <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <input type="number" step="0.01" name="items[{{ $index }}][jumlah_diberikan]"
                                       value="{{ $item->jumlah }}" min="0" required
                                       class="w-24 px-2 py-1 text-center border border-gray-300 rounded-md font-mono text-sm">
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" name="items[{{ $index }}][aturan_pakai]"
                                       value="{{ $item->aturan_pakai }}" placeholder="3 x 1 tablet"
                                       class="w-full px-2 py-1 border border-gray-300 rounded-md text-xs">
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{-- Tombol Cetak Etiket Obat (from KlikMedis tutorial) --}}
                                <button type="button"
                                        @click="etiketItem = { nama: '{{ addslashes($item->nama_obat) }}', signa: '{{ addslashes($item->aturan_pakai ?? '') }}', pasien: '{{ addslashes($kunjungan->pasien?->nama ?? 'Pasien') }}', tgl: '{{ date('d/m/Y') }}' }; showEtiket = true"
                                        class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded">
                                    🏷️ Etiket
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada resep obat</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-6 border-t border-gray-100 flex flex-wrap items-center justify-between gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Catatan Tambahan Farmasi</label>
                    <input type="text" name="catatan" placeholder="Catatan dispensing atau informasi penyimpanan"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="flex items-center space-x-3">
                    <button type="submit" class="px-5 py-2.5 bg-gray-100 text-gray-800 text-sm font-semibold rounded-lg hover:bg-gray-200">
                        Simpan Draf
                    </button>
                </div>
            </div>
        </form>

        {{-- Selesai Dispensing Button --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-500">Klik tombol di kanan untuk memotong stok obat dan mengalirkan pasien ke Kasir</span>
            <form method="POST" action="{{ route('pelayanan.farmasi.selesai', $kunjungan) }}">
                @csrf
                <button type="submit" class="px-6 py-2.5 bg-green-600 text-white font-bold text-sm rounded-lg hover:bg-green-700 shadow-sm"
                        onclick="return confirm('Selesaikan penyerahan obat? Stok obat akan dipotong otomatis dan pasien diteruskan ke kasir.')">
                    ✓ Selesaikan & Lanjut ke Kasir
                </button>
            </form>
        </div>
    </div>

    {{-- Modal Cetak Etiket Obat (Preview Etiket) --}}
    <div x-show="showEtiket" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showEtiket = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-gray-800">Pratinjau Etiket Obat</h3>
                <button @click="showEtiket = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            {{-- Label Etiket Style --}}
            <div class="border-2 border-blue-600 rounded-lg p-4 bg-white space-y-2 text-center" id="etiket-print">
                <p class="font-bold text-sm text-blue-800">{{ config('app.name') }}</p>
                <p class="text-[10px] text-gray-500">INSTALASI FARMASI</p>
                <div class="border-t border-b border-gray-200 py-1.5 my-1 text-xs">
                    <p class="font-semibold text-gray-800" x-text="etiketItem?.pasien"></p>
                    <p class="text-[10px] text-gray-400" x-text="etiketItem?.tgl"></p>
                </div>
                <p class="font-bold text-base text-gray-900" x-text="etiketItem?.nama"></p>
                <div class="bg-blue-50 py-1.5 px-3 rounded font-bold text-xs text-blue-900" x-text="etiketItem?.signa"></div>
                <p class="text-[9px] text-gray-400 italic">Semoga lekas sembuh</p>
            </div>

            <div class="flex justify-end space-x-3 mt-4">
                <button type="button" @click="showEtiket = false" class="px-3 py-1.5 border border-gray-300 rounded text-xs">Tutup</button>
                <button type="button" onclick="window.print()" class="px-4 py-1.5 bg-blue-600 text-white rounded text-xs font-semibold">
                    🖨️ Cetak
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
