@extends('layouts.app')

@section('title', 'Manajemen Pengguna')
@section('page-title', 'Manajemen Pengguna & Akses')

@section('content')
<div class="py-4 space-y-6" x-data="userPage()">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Kelola akun login sistem, peranan hak akses (Administrator, Dokter, Perawat, Kasir, Farmasi, Pendaftaran), dan reset kata sandi</p>
        </div>
        <button @click="openCreate()" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            + Tambah Pengguna
        </button>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <form method="GET" action="{{ route('users.index') }}" class="flex items-center space-x-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau email..."
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <select name="role" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">Semua Peran</option>
                    <option value="admin">Administrator</option>
                    <option value="dokter">Dokter</option>
                    <option value="perawat">Perawat</option>
                    <option value="farmasi">Farmasi</option>
                    <option value="kasir">Kasir</option>
                    <option value="pendaftaran">Pendaftaran</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Email Login</th>
                        <th class="px-4 py-3 text-center">Peran (Role)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                        <td class="px-4 py-3 font-mono text-gray-600">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                            $roleColors = [
                                'admin'       => 'badge-purple',
                                'dokter'      => 'badge-blue',
                                'perawat'     => 'badge-green',
                                'farmasi'     => 'bg-orange-100 text-orange-800',
                                'kasir'       => 'bg-pink-100 text-pink-800',
                                'pendaftaran' => 'badge-yellow',
                            ];
                            @endphp
                            <span class="badge {{ $roleColors[$user->role] ?? 'badge-gray' }} text-xs">
                                {{ $user->role_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $user->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <button @click="openReset({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                        class="text-xs text-yellow-600 hover:text-yellow-800 font-medium">
                                    Reset Password
                                </button>
                                <button @click="openEdit({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->email }}', '{{ $user->role }}', {{ $user->is_active ? 'true' : 'false' }}, '{{ $user->nakes?->id }}')"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Edit
                                </button>
                                @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium"
                                            onclick="return confirm('Hapus pengguna ini?')">Hapus</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada pengguna terdaftar</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $users->links() }}</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800" x-text="isEdit ? 'Edit Pengguna' : 'Tambah Pengguna Baru'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form :action="isEdit ? '{{ url('users') }}/' + editId : '{{ route('users.store') }}'" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PUT" x-bind:disabled="!isEdit">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pengguna <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Login <span class="text-red-500">*</span></label>
                        <input type="email" name="email" x-model="form.email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div x-show="!isEdit">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" x-model="form.password" :required="!isEdit" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Minimal 6 karakter">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Peran Hak Akses <span class="text-red-500">*</span></label>
                        <select name="role" x-model="form.role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="admin">Administrator (Akses Penuh)</option>
                            <option value="dokter">Dokter</option>
                            <option value="perawat">Perawat</option>
                            <option value="farmasi">Farmasi / Apoteker</option>
                            <option value="kasir">Kasir</option>
                            <option value="pendaftaran">Petugas Pendaftaran</option>
                        </select>
                    </div>
                    <div x-show="form.role === 'dokter'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profil Dokter <span class="text-red-500">*</span></label>
                        <select name="nakes_id" x-model="form.nakes_id" :required="form.role === 'dokter'" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Pilih dokter yang terhubung</option>
                            @foreach($dokterList as $dokter)
                            <option value="{{ $dokter->id }}">{{ $dokter->nama }} ({{ $dokter->kode }}){{ $dokter->user_id ? ' - sudah terhubung' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Kunjungan dan rekam medis dokter mengikuti profil ini.</p>
                    </div>
                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="usr_is_active" value="1" x-model="form.is_active" class="w-4 h-4 text-blue-600 rounded">
                        <label for="usr_is_active" class="ml-2 text-sm text-gray-700">Akun Aktif</label>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" @click="showModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"
                            x-text="isEdit ? 'Simpan Perubahan' : 'Simpan'"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reset Password Modal --}}
    <div x-show="showResetModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showResetModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6" @click.stop>
            <h3 class="text-base font-semibold text-gray-800 mb-2">Reset Password</h3>
            <p class="text-xs text-gray-500 mb-4">Reset password untuk: <strong x-text="resetName"></strong></p>
            <form :action="'{{ url('users') }}/' + resetId + '/reset-password'" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Password Baru <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" @click="showResetModal = false" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs">Batal</button>
                    <button type="submit" class="px-4 py-1.5 bg-yellow-600 text-white font-semibold rounded-lg text-xs hover:bg-yellow-700">Simpan Password Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function userPage() {
    return {
        showModal: false,
        showResetModal: false,
        isEdit: false,
        editId: null,
        resetId: null,
        resetName: '',
        form: { name: '', email: '', password: '', role: 'perawat', is_active: true, nakes_id: '' },
        openCreate() {
            this.isEdit = false;
            this.editId = null;
            this.form = { name: '', email: '', password: '', role: 'perawat', is_active: true, nakes_id: '' };
            this.showModal = true;
        },
        openEdit(id, name, email, role, isActive, nakesId) {
            this.isEdit = true;
            this.editId = id;
            this.form = { name, email, password: '', role, is_active: isActive, nakes_id: nakesId || '' };
            this.showModal = true;
        },
        openReset(id, name) {
            this.resetId = id;
            this.resetName = name;
            this.showResetModal = true;
        }
    };
}
</script>
@endpush
