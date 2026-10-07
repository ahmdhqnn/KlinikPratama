@extends('layouts.app')

@section('title', 'Janji Kunjungan')
@section('page-title', 'Janji Kunjungan')

@section('content')
<div class="py-4 space-y-5">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <form class="flex flex-wrap gap-3 items-end">
            <div><label class="block text-xs text-gray-500 mb-1">Dari</label><input type="date" name="dari" value="{{ request('dari', today()->toDateString()) }}" class="px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">Sampai</label><input type="date" name="sampai" value="{{ request('sampai') }}" class="px-3 py-2 border rounded-lg text-sm"></div>
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">Filter</button>
        </form>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3 text-left">Tanggal</th><th class="px-5 py-3 text-left">Pasien</th><th class="px-5 py-3 text-left">Poli</th><th class="px-5 py-3 text-left">Status</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($kunjungan as $item)
                <tr><td class="px-5 py-3">{{ $item->tanggal->format('d/m/Y') }}</td><td class="px-5 py-3">{{ $item->pasien->nama }}<div class="text-xs text-gray-400">{{ $item->pasien->no_rm }}</div></td><td class="px-5 py-3">{{ $item->poliklinik->nama }}</td><td class="px-5 py-3"><span class="badge badge-blue">{{ ucfirst($item->status) }}</span></td><td class="px-5 py-3 text-right"><a href="{{ route('pelayanan.pemeriksaan.show', $item) }}" class="text-purple-600 font-semibold">Pemeriksaan</a></td></tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada janji kunjungan.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-5">{{ $kunjungan->links() }}</div>
    </div>
</div>
@endsection
