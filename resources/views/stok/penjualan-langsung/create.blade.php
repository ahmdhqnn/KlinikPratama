@extends('layouts.app')

@section('title', 'Transaksi Penjualan Obat Baru')
@section('page-title', 'Penjualan Obat Langsung (Kasir Apotek)')

@section('content')
<div class="py-4 max-w-4xl" x-data="penjualanLangsungForm()">
    <div class="mb-4">
        <a href="{{ route('stok.penjualan-langsung.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Riwayat Penjualan</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="{{ route('stok.penjualan-langsung.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pembeli (Opsional)</label>
                    <input type="text" name="nama_pembeli" placeholder="Umum / Nama Pembeli"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Metode Pembayaran <span class="text-red-500">*</span></label>
                    <select name="metode_bayar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="tunai">Tunai / Cash</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                    </select>
                </div>
            </div>

            {{-- Items --}}
            <h3 class="text-base font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-100">Daftar Obat yang Dijual</h3>
            <div class="overflow-x-auto mb-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-3 py-2 text-left">Obat</th>
                            <th class="px-3 py-2 text-center w-28">Jumlah</th>
                            <th class="px-3 py-2 text-right w-36">Harga Jual</th>
                            <th class="px-3 py-2 text-right w-36">Subtotal</th>
                            <th class="px-3 py-2 text-center w-12">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="idx">
                            <tr>
                                <td class="px-3 py-2">
                                    <select :name="'items[' + idx + '][obat_id]'" x-model="item.obat_id" @change="updateHarga(idx)" required
                                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm">
                                        <option value="">-- Pilih Obat --</option>
                                        @foreach($obatList as $obat)
                                        <option value="{{ $obat->id }}" data-harga="{{ $obat->harga_jual }}">{{ $obat->nama }} (Stok: {{ $obat->stok }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="number" :name="'items[' + idx + '][jumlah]'" x-model.number="item.jumlah" min="1" required
                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-center text-sm font-mono">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" :name="'items[' + idx + '][harga]'" x-model.number="item.harga" min="0" required
                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-right text-sm font-mono">
                                </td>
                                <td class="px-3 py-2 text-right font-semibold font-mono text-gray-800">
                                    Rp <span x-text="formatRupiah(item.jumlah * item.harga)"></span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <button type="button" @click="addItem()" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 mb-6">
                + Tambah Obat
            </button>

            {{-- Perhitungan Bayar & Kembalian --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-5 bg-gray-50 rounded-xl mb-6">
                <div>
                    <span class="text-xs text-gray-500 block">Total Tagihan:</span>
                    <p class="text-xl font-bold font-mono text-blue-700 mt-1">Rp <span x-text="formatRupiah(totalPenjualan)"></span></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nominal Bayar (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="bayar" x-model.number="bayar" required min="0"
                           class="w-full px-3 py-2 border-2 border-blue-400 rounded-lg text-base font-bold font-mono text-right">
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-500 block">Kembalian:</span>
                    <p class="text-xl font-bold font-mono text-emerald-700 mt-1">Rp <span x-text="formatRupiah(kembalian)"></span></p>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-100">
                <a href="{{ route('stok.penjualan-langsung.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Simpan Transaksi & Cetak Nota
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function penjualanLangsungForm() {
    return {
        items: [{ obat_id: '', jumlah: 1, harga: 0 }],
        bayar: 0,
        addItem() {
            this.items.push({ obat_id: '', jumlah: 1, harga: 0 });
        },
        removeItem(idx) {
            if (this.items.length > 1) {
                this.items.splice(idx, 1);
            }
        },
        updateHarga(idx) {
            const selectEl = document.querySelector(`select[name="items[${idx}][obat_id]"]`);
            if (selectEl) {
                const opt = selectEl.options[selectEl.selectedIndex];
                this.items[idx].harga = parseFloat(opt.getAttribute('data-harga')) || 0;
            }
        },
        get totalPenjualan() {
            return this.items.reduce((sum, item) => sum + ((item.jumlah || 0) * (item.harga || 0)), 0);
        },
        get kembalian() {
            return Math.max(0, (this.bayar || 0) - this.totalPenjualan);
        },
        formatRupiah(val) {
            return new Intl.NumberFormat('id-ID').format(val || 0);
        }
    };
}
</script>
@endpush
