@extends('layouts.app')

@section('title', 'Database Pasien')
@section('page-title', 'Database Pasien')

@section('content')
<div class="py-4 space-y-5">
    <form class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex gap-3">
        <input name="search" value="{{ request('search') }}" placeholder="Cari nama, No. RM, atau NIK" class="flex-1 px-3 py-2 border rounded-lg text-sm">
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">Cari</button>
    </form>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3 text-left">No. RM</th><th class="px-5 py-3 text-left">Nama</th><th class="px-5 py-3 text-left">Jenis Kelamin</th><th class="px-5 py-3 text-left">Jumlah Kunjungan</th><th></th></tr></thead>
        <tbody class="divide-y divide-gray-100">@forelse($pasien as $item)<tr><td class="px-5 py-3 font-mono text-blue-600">{{ $item->no_rm }}</td><td class="px-5 py-3 font-medium">{{ $item->nama }}</td><td class="px-5 py-3">{{ $item->jenis_kelamin ?? '-' }}</td><td class="px-5 py-3">{{ $item->jumlah_kunjungan }}</td><td class="px-5 py-3 text-right"><a href="{{ route('pelayanan.pasien.rekam-medis', $item) }}" class="text-purple-600 font-semibold">Lihat RME</a></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada pasien.</td></tr>@endforelse</tbody></table>
        <div class="p-5">{{ $pasien->links() }}</div>
    </div>
</div>
@endsection
