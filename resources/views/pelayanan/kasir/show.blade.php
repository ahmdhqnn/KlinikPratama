@extends('layouts.app')

@section('title', 'Proses Kasir - ' . $kunjungan->pasien->nama)
@section('page-title', 'Pembayaran Tagihan: ' . $kunjungan->pasien->nama)

@section('content')
<div class="py-4 space-y-6" x-data="kasirPage()">
    <div class="flex items-center justify-between">
        <a href="{{ route('pelayanan.kasir.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Antrian Kasir</a>
    </div>

    {{-- Ringkasan Pasien & Tagihan --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-xs text-pink-600 font-semibold uppercase tracking-wider">Kasir Pembayaran</span>
            <h2 class="text-xl font-bold text-gray-800 mt-0.5">{{ $kunjungan->pasien->nama }}</h2>
            <div class="flex items-center space-x-3 text-xs text-gray-500 mt-1">
                <span class="font-mono font-bold text-blue-600">{{ $kunjungan->pasien->no_rm }}</span>
                <span>•</span>
                <span>Poli: <strong>{{ $kunjungan->poliklinik->nama }}</strong></span>
                <span>•</span>
                <span>Dokter: {{ $kunjungan->dokter?->nama ?? '-' }}</span>
                <span>•</span>
                <span class="badge {{ $kunjungan->jenis_bayar === 'bpjs' ? 'badge-green' : 'badge-blue' }}">{{ strtoupper($kunjungan->jenis_bayar) }}</span>
            </div>
        </div>
    </div>

    {{-- Rincian Tagihan & Form Pembayaran --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Tabel Komponen Biaya --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800">Rincian Komponen Tagihan</h3>
                <span class="text-xs text-gray-500">Item terakumulasi otomatis dari tindakan, obat, dan registrasi</span>
            </div>

            <form method="POST" action="{{ route('pelayanan.kasir.store', $kunjungan) }}" id="kasirForm">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-xs">
                                <th class="px-4 py-3 text-left">Komponen Layanan</th>
                                <th class="px-4 py-3 text-center">Kategori</th>
                                <th class="px-4 py-3 text-center">Qty</th>
                                <th class="px-4 py-3 text-right">Tarif Satuan</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php $calcSubtotal = 0; @endphp
                            @forelse($komponenTagihan as $idx => $item)
                            @php
                            $lineTotal = $item['tarif'] * $item['jumlah'];
                            $calcSubtotal += $lineTotal;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    {{ $item['nama'] }}
                                    <input type="hidden" name="items[{{ $idx }}][nama]" value="{{ $item['nama'] }}">
                                    <input type="hidden" name="items[{{ $idx }}][jenis]" value="{{ $item['jenis'] }}">
                                    <input type="hidden" name="items[{{ $idx }}][referensi_id]" value="{{ $item['referensi_id'] ?? '' }}">
                                    <input type="hidden" name="items[{{ $idx }}][jumlah]" value="{{ $item['jumlah'] }}">
                                    <input type="hidden" name="items[{{ $idx }}][tarif]" value="{{ $item['tarif'] }}">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-gray text-[10px] uppercase">{{ $item['jenis'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-center font-mono">{{ $item['jumlah'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">Rp {{ number_format($item['tarif'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800">Rp {{ number_format($lineTotal, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada komponen tagihan</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right font-bold text-gray-700">SUBTOTAL:</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900 font-mono text-base">
                                    Rp {{ number_format($calcSubtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
        </div>

        {{-- Panel Pembayaran --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h3 class="text-base font-semibold text-gray-800 pb-2 border-b border-gray-100">Proses Pembayaran</h3>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Metode Pembayaran <span class="text-red-500">*</span></label>
                <select name="metode_bayar" x-model="metode" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="tunai">Tunai / Cash</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="qris">QRIS</option>
                    <option value="bpjs">BPJS (Klaim)</option>
                    <option value="asuransi">Asuransi Swasta</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Potongan / Diskon (Rp)</label>
                <input type="number" name="diskon" x-model.number="diskon" min="0" placeholder="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono text-right">
            </div>

            <div class="p-4 bg-gray-50 rounded-xl space-y-2 border border-gray-100">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal:</span>
                    <span class="font-mono">Rp {{ number_format($calcSubtotal, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Diskon:</span>
                    <span class="font-mono text-red-600">- Rp <span x-text="formatRupiah(diskon)"></span></span>
                </div>
                <div class="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-200">
                    <span>TOTAL BAYAR:</span>
                    <span class="font-mono text-blue-700">Rp <span x-text="formatRupiah(totalTagihan)"></span></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nominal Diterima (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="bayar" x-model.number="bayar" required min="0"
                       class="w-full px-3 py-2.5 border-2 border-blue-400 rounded-lg text-base font-bold font-mono text-right focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Kembalian Display --}}
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-center">
                <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider block">Kembalian</span>
                <p class="text-2xl font-bold font-mono text-emerald-800 mt-1">
                    Rp <span x-text="formatRupiah(kembalian)"></span>
                </p>
            </div>

            <button type="submit" class="w-full py-3 bg-pink-600 hover:bg-pink-700 text-white font-bold rounded-lg text-sm transition-colors shadow-sm">
                Proses Pembayaran & Terbitkan Kuitansi
            </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function kasirPage() {
    const subtotalAwal = {{ $calcSubtotal }};
    return {
        metode: 'tunai',
        diskon: 0,
        bayar: subtotalAwal,
        get totalTagihan() {
            return Math.max(0, subtotalAwal - (this.diskon || 0));
        },
        get kembalian() {
            return Math.max(0, (this.bayar || 0) - this.totalTagihan);
        },
        formatRupiah(val) {
            return new Intl.NumberFormat('id-ID').format(val || 0);
        }
    };
}
</script>
@endpush
