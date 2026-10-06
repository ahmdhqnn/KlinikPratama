@extends('layouts.app')

@section('title', 'Master Nakes / SDM')
@section('page-title', 'Tenaga Kesehatan & Karyawan')

@section('content')
<div class="py-4" x-data="nakesPage()">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">Kelola data seluruh SDM klinik (Medis & Non-Medis) dengan multi-role support</p>
        </div>
        <button @click="openCreate()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Nakes / SDM
        </button>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('master.nakes.index') }}" class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, kode nakes..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                </div>
                <select name="kategori" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Kategori</option>
                    <option value="medis" {{ request('kategori') === 'medis' ? 'selected' : '' }}>Medis</option>
                    <option value="non_medis" {{ request('kategori') === 'non_medis' ? 'selected' : '' }}>Non-Medis</option>
                </select>
                <select name="jabatan" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Jabatan</option>
                    <option value="dokter">Dokter</option>
                    <option value="perawat">Perawat</option>
                    <option value="bidan">Bidan</option>
                    <option value="lab">Petugas Lab</option>
                    <option value="farmasi">Farmasi</option>
                    <option value="kasir">Kasir</option>
                    <option value="pendaftaran">Pendaftaran</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Kode</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Kategori</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Jabatan</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">SIP / STR</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Akun Login</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Status</th>
                        <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($nakes as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $nakes->firstItem() + $index }}</td>
                        <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800">{{ $item->kode }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $item->nama }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="badge {{ $item->kategori === 'medis' ? 'badge-blue' : 'badge-gray' }}">
                                {{ $item->kategori === 'medis' ? 'Medis' : 'Non-Medis' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 font-medium">{{ ucfirst($item->jabatan) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 font-mono">
                            {{ $item->no_sip ?? ($item->no_str ?? '-') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            @if($item->user)
                            <span class="text-green-600 font-medium">✓ {{ $item->user->email }}</span>
                            @else
                            <span class="text-gray-400">Belum ada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <button @click="openEdit({{ $item->id }}, '{{ $item->kode }}', '{{ addslashes($item->nama) }}', '{{ $item->jenis_kelamin }}', '{{ $item->kategori }}', '{{ $item->jabatan }}', '{{ $item->no_sip ?? '' }}', '{{ $item->no_str ?? '' }}', '{{ $item->telepon ?? '' }}', {{ $item->is_active ? 'true' : 'false' }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                <form method="POST" action="{{ route('master.nakes.destroy', $item) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                            onclick="return confirm('Hapus data nakes ini?')">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data tenaga kesehatan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($nakes->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $nakes->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 overflow-y-auto max-h-[90vh]" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800" x-text="isEdit ? 'Edit Data SDM' : 'Tambah Tenaga Medis / Non-Medis'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="isEdit ? '{{ url('master/nakes') }}/' + editId : '{{ route('master.nakes.store') }}'" method="POST">
                @csrf
                <span x-show="isEdit"><input type="hidden" name="_method" value="PUT"></span>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode SDM <span class="text-red-500">*</span></label>
                            <input type="text" name="kode" x-model="form.kode" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="DKT001, PRT001">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
                            <select name="jenis_kelamin" x-model="form.jenis_kelamin" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">-- Pilih --</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap (beserta gelar) <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" x-model="form.nama" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="dr. Nama Lengkap, Sp.A">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                            <select name="kategori" x-model="form.kategori" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="medis">Tenaga Medis</option>
                                <option value="non_medis">Non-Medis</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan <span class="text-red-500">*</span></label>
                            <select name="jabatan" x-model="form.jabatan" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="dokter">Dokter</option>
                                <option value="perawat">Perawat</option>
                                <option value="bidan">Bidan</option>
                                <option value="lab">Petugas Lab</option>
                                <option value="farmasi">Farmasi / Apoteker</option>
                                <option value="kasir">Kasir</option>
                                <option value="pendaftaran">Petugas Pendaftaran</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. SIP (Surat Izin Praktik)</label>
                            <input type="text" name="no_sip" x-model="form.no_sip" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. STR</label>
                            <input type="text" name="no_str" x-model="form.no_str" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon / WhatsApp</label>
                        <input type="text" name="telepon" x-model="form.telepon" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>

                    {{-- Section Buat Akun Login (hanya saat create) --}}
                    <div x-show="!isEdit" class="border-t border-gray-100 pt-3">
                        <div class="flex items-center mb-3">
                            <input type="checkbox" name="buat_akun" id="buat_akun" value="1" x-model="buatAkun" class="w-4 h-4 text-blue-600 rounded">
                            <label for="buat_akun" class="ml-2 text-sm font-medium text-gray-700">Buat Akun Login untuk Nakes Ini</label>
                        </div>
                        <div x-show="buatAkun" class="space-y-3 pl-6 border-l-2 border-blue-200">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Email Login</label>
                                <input type="email" name="email" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" placeholder="email@klinik.com">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Password (default: password)</label>
                                <input type="password" name="password" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="nakes_is_active" value="1" x-model="form.is_active" class="w-4 h-4 text-blue-600 rounded">
                        <label for="nakes_is_active" class="ml-2 text-sm text-gray-700">Aktif</label>
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
function nakesPage() {
    return {
        showModal: false,
        isEdit: false,
        editId: null,
        buatAkun: false,
        form: { kode: '', nama: '', jenis_kelamin: '', kategori: 'medis', jabatan: 'dokter', no_sip: '', no_str: '', telepon: '', is_active: true },
        openCreate() {
            this.isEdit = false;
            this.editId = null;
            this.buatAkun = false;
            this.form = { kode: '', nama: '', jenis_kelamin: '', kategori: 'medis', jabatan: 'dokter', no_sip: '', no_str: '', telepon: '', is_active: true };
            this.showModal = true;
        },
        openEdit(id, kode, nama, jk, kategori, jabatan, sip, str, telp, isActive) {
            this.isEdit = true;
            this.editId = id;
            this.form = { kode, nama, jenis_kelamin: jk || '', kategori, jabatan, no_sip: sip || '', no_str: str || '', telepon: telp || '', is_active: isActive };
            this.showModal = true;
        },
    };
}
</script>
@endpush
