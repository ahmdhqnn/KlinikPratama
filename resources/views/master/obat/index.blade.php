@extends('layouts.app')

@section('title', 'Master Obat & BHP')
@section('page-title', 'Master Obat & BHP')

@section('content')
<div class="py-4" x-data="obatPage()">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <p class="text-sm text-gray-500">Kelola master obat, BHP, kode KFA SATUSEHAT, konversi satuan, dan stok</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Template Excel --}}
            <a href="{{ route('master.obat.template') }}" class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Template Excel
            </a>
            {{-- Import Excel --}}
            <button @click="showImportModal = true" class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                Import Excel
            </button>
            {{-- Export Excel --}}
            <a href="{{ route('master.obat.export') }}" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </a>
            {{-- Tambah Obat --}}
            <button @click="openCreate()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Obat
            </button>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('master.obat.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px] relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, kode obat, kode KFA..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                </div>
                <select name="jenis" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Jenis</option>
                    <option value="obat" {{ request('jenis') === 'obat' ? 'selected' : '' }}>Obat</option>
                    <option value="bhp" {{ request('jenis') === 'bhp' ? 'selected' : '' }}>BHP</option>
                </select>
                <label class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="stok_rendah" value="1" {{ request('stok_rendah') ? 'checked' : '' }} class="mr-2 rounded">
                    Stok Kritis / Habis
                </label>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Kode / KFA</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama Obat</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Satuan (Besar/Kecil)</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Harga Beli</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Harga Jual</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Stok</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($obat as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $obat->firstItem() + $index }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="font-mono font-semibold text-gray-800">{{ $item->kode }}</span>
                            @if($item->kode_kfa)
                            <span class="text-xs text-blue-600 block font-mono" title="Kode KFA SATUSEHAT">KFA: {{ $item->kode_kfa }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-gray-800">{{ $item->nama }}</div>
                            <span class="badge {{ $item->jenis === 'obat' ? 'badge-blue' : 'badge-purple' }} text-[10px]">
                                {{ strtoupper($item->jenis) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $item->satuan_besar ?? '-' }} / {{ $item->satuan_kecil ?? '-' }}
                            @if($item->konversi_satuan > 1)
                            <span class="text-xs text-gray-400 block">(1 {{ $item->satuan_besar }} = {{ $item->konversi_satuan }} {{ $item->satuan_kecil }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600">Rp {{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($item->stok <= 0)
                            <span class="badge badge-red font-bold">0 Habis</span>
                            @elseif($item->stok <= $item->stok_minimum)
                            <span class="badge badge-yellow font-bold">{{ $item->stok }} Kritis</span>
                            @else
                            <span class="badge badge-green font-semibold">{{ $item->stok }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('master.obat.stok', $item) }}" class="text-emerald-600 hover:text-emerald-800 text-xs font-medium" title="Riwayat Stok & Tambah Stok">
                                    Stok
                                </a>
                                <button @click="openEdit({{ $item->id }}, '{{ $item->kode }}', '{{ $item->kode_kfa ?? '' }}', '{{ addslashes($item->nama) }}', '{{ $item->satuan_besar ?? '' }}', '{{ $item->satuan_kecil ?? '' }}', {{ $item->konversi_satuan }}, {{ $item->harga_beli }}, {{ $item->harga_jual }}, '{{ addslashes($item->indikasi ?? '') }}', '{{ addslashes($item->kandungan ?? '') }}', {{ $item->stok_minimum }}, '{{ $item->jenis }}', {{ $item->is_active ? 'true' : 'false' }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                <form method="POST" action="{{ route('master.obat.destroy', $item) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                            onclick="return confirm('Hapus obat ini?')">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data obat</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($obat->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $obat->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6 overflow-y-auto max-h-[90vh]" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800" x-text="isEdit ? 'Edit Obat' : 'Tambah Obat Baru'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="isEdit ? '{{ url('master/obat') }}/' + editId : '{{ route('master.obat.store') }}'" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PUT" x-bind:disabled="!isEdit">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode Obat <span class="text-red-500">*</span></label>
                            <input type="text" name="kode" x-model="form.kode" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode KFA (SATUSEHAT)</label>
                            <input type="text" name="kode_kfa" x-model="form.kode_kfa" placeholder="93000..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Obat / Alkes <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" x-model="form.nama" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Paracetamol 500mg">
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan Besar</label>
                            <input type="text" name="satuan_besar" x-model="form.satuan_besar" placeholder="Box, Strip, Botol" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan Kecil</label>
                            <input type="text" name="satuan_kecil" x-model="form.satuan_kecil" placeholder="Tablet, Kapsul, ml" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Isi per Sat. Besar</label>
                            <input type="number" name="konversi_satuan" x-model="form.konversi_satuan" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Beli (Rp) <span class="text-red-500">*</span></label>
                            <input type="number" name="harga_beli" x-model="form.harga_beli" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual (Rp) <span class="text-red-500">*</span></label>
                            <input type="number" name="harga_jual" x-model="form.harga_jual" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div x-show="!isEdit">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stok Awal <span class="text-red-500">*</span></label>
                            <input type="number" name="stok" x-model="form.stok" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stok Minimum <span class="text-red-500">*</span></label>
                            <input type="number" name="stok_minimum" x-model="form.stok_minimum" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
                            <select name="jenis" x-model="form.jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="obat">Obat</option>
                                <option value="bhp">BHP</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Indikasi</label>
                            <textarea name="indikasi" x-model="form.indikasi" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Indikasi penggunaan obat"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kandungan</label>
                            <textarea name="kandungan" x-model="form.kandungan" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Zat aktif / komposisi"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="obt_is_active" value="1" x-model="form.is_active" class="w-4 h-4 text-blue-600 rounded">
                        <label for="obt_is_active" class="ml-2 text-sm text-gray-700">Aktif</label>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" @click="showModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"
                            x-text="isEdit ? 'Simpan Perubahan' : 'Simpan'"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Import Modal --}}
    <div x-show="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showImportModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Import Data Obat via Excel</h3>
                <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form method="POST" action="{{ route('master.obat.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">Unggah file Excel sesuai format template yang telah disediakan.</p>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg text-xs text-blue-700">
                        <p class="font-semibold mb-1">Tips Import:</p>
                        <p>Pastikan kode obat unik dan nama kolom sesuai dengan template Excel.</p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" @click="showImportModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">Import Data</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function obatPage() {
    return {
        showModal: false,
        showImportModal: false,
        isEdit: false,
        editId: null,
        form: { kode: '', kode_kfa: '', nama: '', satuan_besar: '', satuan_kecil: '', konversi_satuan: 1, harga_beli: 0, harga_jual: 0, stok: 0, stok_minimum: 10, jenis: 'obat', indikasi: '', kandungan: '', is_active: true },
        openCreate() {
            this.isEdit = false;
            this.editId = null;
            this.form = { kode: '', kode_kfa: '', nama: '', satuan_besar: '', satuan_kecil: '', konversi_satuan: 1, harga_beli: 0, harga_jual: 0, stok: 0, stok_minimum: 10, jenis: 'obat', indikasi: '', kandungan: '', is_active: true };
            this.showModal = true;
        },
        openEdit(id, kode, kfa, nama, sb, sk, konversi, beli, jual, ind, kand, min, jenis, isActive) {
            this.isEdit = true;
            this.editId = id;
            this.form = { kode, kode_kfa: kfa || '', nama, satuan_besar: sb || '', satuan_kecil: sk || '', konversi_satuan: konversi, harga_beli: beli, harga_jual: jual, stok: 0, stok_minimum: min, jenis, indikasi: ind || '', kandungan: kand || '', is_active: isActive };
            this.showModal = true;
        },
    };
}
</script>
@endpush
