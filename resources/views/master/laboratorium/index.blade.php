@extends('layouts.app')

@section('title', 'Master Laboratorium')
@section('page-title', 'Master Laboratorium')

@section('content')
<div class="py-4" x-data="labPage()">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">Kelola pemeriksaan laboratorium, indikator hasil, nilai rujukan, dan BHP lab</p>
        </div>
        <button @click="openCreate()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Lab
        </button>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('master.laboratorium.index') }}" class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode pemeriksaan lab..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
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
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama Pemeriksaan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Poliklinik</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Tarif</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($laboratorium as $index => $lab)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $laboratorium->firstItem() + $index }}</td>
                        <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800">{{ $lab->kode }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $lab->nama }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $lab->poliklinik?->nama ?? 'Umum' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">Rp {{ number_format($lab->tarif, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $lab->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $lab->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('master.laboratorium.show', $lab) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                    Indikator & BHP
                                </a>
                                <button @click="openEdit({{ $lab->id }}, '{{ $lab->kode }}', '{{ addslashes($lab->nama) }}', '{{ $lab->poliklinik_id }}', {{ $lab->tarif }}, '{{ addslashes($lab->deskripsi ?? '') }}', {{ $lab->is_active ? 'true' : 'false' }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('master.laboratorium.destroy', $lab) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                            onclick="return confirm('Hapus lab ini?')">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data pemeriksaan lab</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($laboratorium->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $laboratorium->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800" x-text="isEdit ? 'Edit Lab' : 'Tambah Lab'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="isEdit ? '{{ url('master/laboratorium') }}/' + editId : '{{ route('master.laboratorium.store') }}'" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PUT" x-bind:disabled="!isEdit">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
                        <input type="text" name="kode" x-model="form.kode" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pemeriksaan <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" x-model="form.nama" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Poliklinik</label>
                        <select name="poliklinik_id" x-model="form.poliklinik_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Semua Poli</option>
                            @foreach($poliklinikList as $poli)
                            <option value="{{ $poli->id }}">{{ $poli->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tarif (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" name="tarif" x-model="form.tarif" required min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" x-model="form.deskripsi" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="lab_is_active" value="1" x-model="form.is_active"
                               class="w-4 h-4 text-blue-600 rounded">
                        <label for="lab_is_active" class="ml-2 text-sm text-gray-700">Aktif</label>
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
function labPage() {
    return {
        showModal: false,
        isEdit: false,
        editId: null,
        form: { kode: '', nama: '', poliklinik_id: '', tarif: 0, deskripsi: '', is_active: true },
        openCreate() {
            this.isEdit = false;
            this.editId = null;
            this.form = { kode: '', nama: '', poliklinik_id: '', tarif: 0, deskripsi: '', is_active: true };
            this.showModal = true;
        },
        openEdit(id, kode, nama, poliId, tarif, deskripsi, isActive) {
            this.isEdit = true;
            this.editId = id;
            this.form = { kode, nama, poliklinik_id: poliId || '', tarif, deskripsi: deskripsi || '', is_active: isActive };
            this.showModal = true;
        },
    };
}
</script>
@endpush
