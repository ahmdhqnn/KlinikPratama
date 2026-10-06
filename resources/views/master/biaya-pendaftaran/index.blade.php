@extends('layouts.app')

@section('title', 'Biaya Pendaftaran')
@section('page-title', 'Biaya Pendaftaran')

@section('content')
<div class="py-4" x-data="biayaDaftarPage()">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">Atur tarif registrasi berdasarkan poli, jenis pasien (baru/lama), dan dokter spesialis</p>
        </div>
        <button @click="openCreate()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Tarif Registrasi
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Poliklinik</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Dokter (Spesialis)</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Jenis Pasien</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Tarif Pendaftaran</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($biayaPendaftaran as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $biayaPendaftaran->firstItem() + $index }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $item->poliklinik?->nama ?? 'Semua Poli' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->dokter?->nama ?? 'Semua Dokter' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->jenis_pasien === 'baru' ? 'badge-blue' : 'badge-purple' }}">
                                Pasien {{ ucfirst($item->jenis_pasien) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">Rp {{ number_format($item->tarif, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <button @click="openEdit({{ $item->id }}, '{{ $item->poliklinik_id }}', '{{ $item->dokter_id }}', '{{ $item->jenis_pasien }}', {{ $item->tarif }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                <form method="POST" action="{{ route('master.biaya-pendaftaran.destroy', $item) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                            onclick="return confirm('Hapus tarif pendaftaran ini?')">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data biaya pendaftaran</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($biayaPendaftaran->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $biayaPendaftaran->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800" x-text="isEdit ? 'Edit Tarif Pendaftaran' : 'Tambah Tarif Pendaftaran'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="isEdit ? '{{ url('master/biaya-pendaftaran') }}/' + editId : '{{ route('master.biaya-pendaftaran.store') }}'" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PUT" x-bind:disabled="!isEdit">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Poliklinik</label>
                        <select name="poliklinik_id" x-model="form.poliklinik_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Semua Poliklinik --</option>
                            @foreach($poliklinikList as $poli)
                            <option value="{{ $poli->id }}">{{ $poli->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dokter (Opsional - untuk spesialis tertentu)</label>
                        <select name="dokter_id" x-model="form.dokter_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">-- Semua Dokter --</option>
                            @foreach($dokterList as $dokter)
                            <option value="{{ $dokter->id }}">{{ $dokter->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Pasien <span class="text-red-500">*</span></label>
                        <select name="jenis_pasien" x-model="form.jenis_pasien" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="baru">Pasien Baru</option>
                            <option value="lama">Pasien Lama</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tarif Pendaftaran (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" name="tarif" x-model="form.tarif" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
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
function biayaDaftarPage() {
    return {
        showModal: false,
        isEdit: false,
        editId: null,
        form: { poliklinik_id: '', dokter_id: '', jenis_pasien: 'baru', tarif: 0 },
        openCreate() {
            this.isEdit = false;
            this.editId = null;
            this.form = { poliklinik_id: '', dokter_id: '', jenis_pasien: 'baru', tarif: 0 };
            this.showModal = true;
        },
        openEdit(id, poliId, dokterId, jenis, tarif) {
            this.isEdit = true;
            this.editId = id;
            this.form = { poliklinik_id: poliId || '', dokter_id: dokterId || '', jenis_pasien: jenis, tarif };
            this.showModal = true;
        },
    };
}
</script>
@endpush
