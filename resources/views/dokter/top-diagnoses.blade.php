@extends('layouts.app')

@section('title', 'Top Diagnosa')
@section('page-title', 'Laporan Top Diagnosa')

@section('content')
<div class="py-4 space-y-5">
    <form class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-wrap gap-3 items-end"><div><label class="block text-xs text-gray-500 mb-1">Dari</label><input type="date" name="dari" value="{{ $dari->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm"></div><div><label class="block text-xs text-gray-500 mb-1">Sampai</label><input type="date" name="sampai" value="{{ $sampai->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm"></div><button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">Tampilkan</button></form>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3 text-left">Peringkat</th><th class="px-5 py-3 text-left">ICD-10</th><th class="px-5 py-3 text-left">Diagnosis</th><th class="px-5 py-3 text-right">Jumlah</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($diagnosa as $index => $item)<tr><td class="px-5 py-3">{{ $diagnosa->firstItem() + $index }}</td><td class="px-5 py-3 font-mono text-purple-600">{{ $item->kode_icd10 }}</td><td class="px-5 py-3">{{ $item->nama_diagnosa }}</td><td class="px-5 py-3 text-right font-bold">{{ $item->jumlah }}</td></tr>@empty<tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">Belum ada data diagnosis.</td></tr>@endforelse</tbody></table><div class="p-5">{{ $diagnosa->links() }}</div></div>
</div>
@endsection
