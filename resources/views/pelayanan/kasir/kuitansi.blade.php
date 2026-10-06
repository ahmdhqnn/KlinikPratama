@extends('layouts.app')

@section('title', 'Kuitansi - ' . $tagihan->no_tagihan)
@section('page-title', 'Kuitansi Pembayaran: ' . $tagihan->no_tagihan)

@section('content')
<div class="py-4 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between no-print">
        <a href="{{ route('pelayanan.kasir.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Kasir</a>
        <button onclick="window.print()" class="px-5 py-2.5 bg-blue-600 text-white font-semibold text-sm rounded-lg hover:bg-blue-700 shadow-sm flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Cetak Kuitansi
        </button>
    </div>

    {{-- Kuitansi Paper Layout --}}
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8" id="kuitansi-print">
        {{-- Header Klinik --}}
        <div class="flex items-center justify-between pb-6 border-b-2 border-gray-800">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 uppercase tracking-wide">{{ config('app.name') }}</h1>
                <p class="text-xs text-gray-500 mt-1">Sistem Rekam Medis & Pelayanan Klinik Pratama</p>
                <p class="text-xs text-gray-500">Jl. Contoh No. 1, Telp: 021-12345678</p>
            </div>
            <div class="text-right">
                <span class="inline-block px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded uppercase tracking-wider">LUNAS</span>
                <p class="font-mono text-xs text-gray-400 mt-1">{{ $tagihan->no_tagihan }}</p>
                <p class="text-xs text-gray-600">{{ $tagihan->created_at->isoFormat('D MMMM Y, HH:mm') }}</p>
            </div>
        </div>

        {{-- Info Pasien & Kunjungan --}}
        <div class="grid grid-cols-2 gap-4 py-4 text-xs border-b border-gray-100">
            <div>
                <span class="text-gray-400 block uppercase tracking-wider text-[10px]">Data Pasien</span>
                <p class="font-bold text-sm text-gray-800">{{ $tagihan->kunjungan->pasien->nama }}</p>
                <p class="font-mono text-gray-600">No. RM: {{ $tagihan->kunjungan->pasien->no_rm }}</p>
                <p class="text-gray-500">{{ $tagihan->kunjungan->pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}, {{ $tagihan->kunjungan->pasien->umur }} th</p>
            </div>
            <div class="text-right">
                <span class="text-gray-400 block uppercase tracking-wider text-[10px]">Layanan</span>
                <p class="font-bold text-gray-800">{{ $tagihan->kunjungan->poliklinik->nama }}</p>
                <p class="text-gray-600">Dokter: {{ $tagihan->kunjungan->dokter?->nama ?? '-' }}</p>
                <p class="text-gray-500">Metode: <strong class="uppercase">{{ $tagihan->metode_bayar }}</strong></p>
            </div>
        </div>

        {{-- Rincian Item Tagihan --}}
        <div class="py-4">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-200 font-bold uppercase text-gray-600">
                        <th class="py-2 text-left">Deskripsi Layanan</th>
                        <th class="py-2 text-center">Qty</th>
                        <th class="py-2 text-right">Tarif</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($tagihan->items as $item)
                    <tr>
                        <td class="py-2.5 font-medium text-gray-800">{{ $item->nama }}</td>
                        <td class="py-2.5 text-center font-mono">{{ $item->jumlah }}</td>
                        <td class="py-2.5 text-right font-mono text-gray-600">Rp {{ number_format($item->tarif, 0, ',', '.') }}</td>
                        <td class="py-2.5 text-right font-mono font-semibold text-gray-800">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-800 font-bold text-sm">
                    <tr>
                        <td colspan="3" class="pt-3 text-right text-gray-600">Subtotal:</td>
                        <td class="pt-3 text-right font-mono">Rp {{ number_format($tagihan->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($tagihan->diskon > 0)
                    <tr>
                        <td colspan="3" class="py-1 text-right text-red-600">Diskon:</td>
                        <td class="py-1 text-right font-mono text-red-600">- Rp {{ number_format($tagihan->diskon, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="text-base font-bold text-gray-900">
                        <td colspan="3" class="py-2 text-right">TOTAL AKHIR:</td>
                        <td class="py-2 text-right font-mono text-blue-700">Rp {{ number_format($tagihan->total, 0, ',', '.') }}</td>
                    </tr>
                    <tr class="text-xs text-gray-600">
                        <td colspan="3" class="py-1 text-right">Bayar ({{ strtoupper($tagihan->metode_bayar) }}):</td>
                        <td class="py-1 text-right font-mono">Rp {{ number_format($tagihan->bayar, 0, ',', '.') }}</td>
                    </tr>
                    <tr class="text-xs text-gray-600">
                        <td colspan="3" class="py-1 text-right">Kembalian:</td>
                        <td class="py-1 text-right font-mono font-bold text-emerald-600">Rp {{ number_format($tagihan->kembalian, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Footer Tanda Tangan --}}
        <div class="mt-8 pt-6 border-t border-dashed border-gray-300 grid grid-cols-2 gap-4 text-center text-xs">
            <div>
                <p class="text-gray-400">Pasien / Keluarga</p>
                <div class="h-16"></div>
                <p class="font-semibold text-gray-800">({{ $tagihan->kunjungan->pasien->nama }})</p>
            </div>
            <div>
                <p class="text-gray-400">Petugas Kasir</p>
                <div class="h-16"></div>
                <p class="font-semibold text-gray-800">({{ auth()->user()->name }})</p>
            </div>
        </div>

        <p class="text-center text-[10px] text-gray-400 mt-8 italic">Terima kasih atas kunjungan Anda. Semoga lekas sembuh.</p>
    </div>
</div>
@endsection
