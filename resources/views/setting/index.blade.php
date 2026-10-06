@extends('layouts.app')

@section('title', 'Pengaturan Informasi Klinik')
@section('page-title', 'Pengaturan Profil Klinik')

@section('content')
<div class="py-4 max-w-4xl space-y-6">
    {{-- Form Identitas Klinik --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h3 class="text-base font-semibold text-gray-800 mb-1">Identitas & Profil Klinik</h3>
        <p class="text-xs text-gray-500 mb-6">Informasi ini akan tercetak pada kop surat medis, resep dokter, dan kuitansi pembayaran kasir</p>

        <form method="POST" action="{{ route('setting.update') }}" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Klinik <span class="text-red-500">*</span></label>
                <input type="text" name="nama_klinik" value="{{ old('nama_klinik', $setting->nama_klinik) }}" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tagline / Semboyan</label>
                <input type="text" name="tagline" value="{{ old('tagline', $setting->tagline) }}" placeholder="Melayani dengan Sepenuh Hati"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                    <input type="text" name="telepon" value="{{ old('telepon', $setting->telepon) }}" placeholder="021-..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Resmi</label>
                    <input type="email" name="email" value="{{ old('email', $setting->email) }}" placeholder="info@klinik.com"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap</label>
                <textarea name="alamat" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('alamat', $setting->alamat) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kepala Klinik / Penanggung Jawab</label>
                    <input type="text" name="kepala_klinik" value="{{ old('kepala_klinik', $setting->kepala_klinik) }}" placeholder="dr. Nama Kepala"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIP / SIP Kepala Klinik</label>
                    <input type="text" name="nip_kepala" value="{{ old('nip_kepala', $setting->nip_kepala) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-700 shadow-sm">
                    Simpan Perubahan Profil
                </button>
            </div>
        </form>
    </div>

    {{-- Form Upload Logo (PNG Required) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h3 class="text-base font-semibold text-gray-800 mb-1">Logo Klinik</h3>
        <p class="text-xs text-gray-500 mb-4">Sesuai ketentuan, logo wajib berformat <strong>PNG transparan</strong> (maksimal 2MB)</p>

        @if($setting->logo)
        <div class="mb-4 p-4 border border-gray-200 rounded-lg w-40 text-center bg-gray-50">
            <img src="{{ asset('storage/' . $setting->logo) }}" alt="Logo Klinik" class="max-h-24 mx-auto">
            <span class="text-[10px] text-gray-400 block mt-2">Logo Aktif Saat Ini</span>
        </div>
        @endif

        <form method="POST" action="{{ route('setting.logo') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex-1 min-w-[250px]">
                <input type="file" name="logo" accept="image/png" required
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Upload Logo PNG
            </button>
        </form>
    </div>
</div>
@endsection
