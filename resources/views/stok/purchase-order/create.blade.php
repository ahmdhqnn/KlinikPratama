@extends('layouts.app')

@section('title', 'Buat Purchase Order Baru')
@section('page-title', 'Buat Purchase Order (PO)')

@section('content')
<div class="py-4 max-w-4xl" x-data="poForm()">
    <div class="mb-4">
        <a href="{{ route('stok.purchase-order.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Daftar PO</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="{{ route('stok.purchase-order.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Supplier / PBF <span class="text-red-500">*</span></label>
                    <input type="text" name="supplier" required placeholder="PT. Kimia Farma, dll"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tujuan Depo Penyimpanan <span class="text-red-500">*</span></label>
                    <select name="depo_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @foreach($depoList as $depo)
                        <option value="{{ $depo->id }}">{{ $depo->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal PO <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal" value="{{ today()->toDateString() }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            {{-- Items Table --}}
            <h3 class="text-base font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-100">Daftar Obat yang Dipesan</h3>
            <div class="overflow-x-auto mb-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-3 py-2 text-left">Obat</th>
                            <th class="px-3 py-2 text-center w-28">Jumlah</th>
                            <th class="px-3 py-2 text-right w-36">Harga Satuan</th>
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
                                        <option value="{{ $obat->id }}" data-harga="{{ $obat->harga_beli }}">{{ $obat->nama }}</option>
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
                + Tambah Baris Obat
            </button>

            <div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl mb-6">
                <span class="font-bold text-gray-700">TOTAL PURCHASE ORDER:</span>
                <span class="text-xl font-bold font-mono text-blue-700">Rp <span x-text="formatRupiah(totalPo)"></span></span>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Tambahan</label>
                <textarea name="catatan" rows="2" placeholder="Catatan untuk supplier atau instruksi pengiriman"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
            </div>

            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('stok.purchase-order.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Simpan Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function poForm() {
    return {
        items: [{ obat_id: '', jumlah: 10, harga: 0 }],
        addItem() {
            this.items.push({ obat_id: '', jumlah: 10, harga: 0 });
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
        get totalPo() {
            return this.items.reduce((sum, item) => sum + ((item.jumlah || 0) * (item.harga || 0)), 0);
        },
        formatRupiah(val) {
            return new Intl.NumberFormat('id-ID').format(val || 0);
        }
    };
}
</script>
@endpush
