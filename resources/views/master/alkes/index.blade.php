@extends('layouts.app')

@section('title', 'Master Alat Kesehatan (Alkes)')
@section('page-title', 'Alat Kesehatan (Alkes)')

@section('content')
<div class="py-4" x-data="alkesPage()">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">Kelola master alat kesehatan dan perlengkapan medis (sumber BHP tindakan)</p>
        </div>
        <button @click="openCreate()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Alkes
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('master.alkes.index') }}" class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode alkes..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Kode</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama Alkes</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Satuan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Harga Beli</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Harga Jual</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Stok</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alkes as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $alkes->firstItem() + $index }}</td>
                        <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800">{{ $item->kode }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $item->nama }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->satuan ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600">Rp {{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->stok <= $item->stok_minimum ? 'badge-yellow' : 'badge-green' }}">
                                {{ $item->stok }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <button @click="openEdit({{ $item->id }}, '{{ $item->kode }}', '{{ addslashes($item->nama) }}', '{{ $item->satuan ?? '' }}', {{ $item->stok }}, {{ $item->stok_minimum }}, {{ $item->harga_beli }}, {{ $item->harga_jual }}, {{ $item->is_active ? 'true' : 'false' }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                <form method="POST" action="{{ route('master.alkes.destroy', $item) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                            onclick="return confirm('Hapus alkes ini?')">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data alat kesehatan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($alkes->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $alkes->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6 overflow-y-auto max-h-[90vh]" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800" x-text="isEdit ? 'Edit Alkes' : 'Tambah Alkes Baru'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form :action="isEdit ? '{{ url('master/alkes') }}/' + editId : '{{ route('master.alkes.store') }}'" method="POST">
                @csrf
                <span x-show="isEdit"><input type="hidden" name="_method" value="PUT"></span>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
                            <input type="text" name="kode" x-model="form.kode" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                            <input type="text" name="satuan" x-model="form.satuan" placeholder="Pcs, Pasang, Set" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Alkes <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" x-model="form.nama" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Sarung Tangan, Jarum Suntik">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stok Awal</label>
                            <input type="number" name="stok" x-model="form.stok" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stok Minimum</label>
                            <input type="number" name="stok_minimum" x-model="form.stok_minimum" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Beli (Rp)</label>
                            <input type="number" name="harga_beli" x-model="form.harga_beli" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual (Rp)</label>
                            <input type="number" name="harga_jual" x-model="form.harga_jual" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="alk_is_active" value="1" x-model="form.is_active" class="w-4 h-4 text-blue-600 rounded">
                        <label for="alk_is_active" class="ml-2 text-sm text-gray-700">Aktif</label>
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
</div>
@endsection

@push('scripts')
<script>
function alkesPage() {
    return {
        showModal: false,
        isEdit: false,
        editId: null,
        form: { kode: '', nama: '', satuan: '', stok: 0, stok_minimum: 5, harga_beli: 0, harga_jual: 0, is_active: true },
        openCreate() {
            this.isEdit = false;
            this.editId = null;
            this.form = { kode: '', nama: '', satuan: '', stok: 0, stok_minimum: 5, harga_beli: 0, harga_jual: 0, is_active: true };
            this.showModal = true;
        },
        openEdit(id, kode, nama, satuan, stok, min, beli, jual, isActive) {
            this.isEdit = true;
            this.editId = id;
            this.form = { kode, nama, satuan: satuan || '', stok, stok_minimum: min, harga_beli: beli, harga_jual: jual, is_active: isActive };
            this.showModal = true;
        },
    };
}
</script>
@endpush
