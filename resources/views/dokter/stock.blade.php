@extends('layouts.app')

@section('title', 'Stok Obat')
@section('page-title', 'Lihat Stok Obat')

@section('content')
<div class="py-4"><div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3 text-left">Kode</th><th class="px-5 py-3 text-left">Nama Obat</th><th class="px-5 py-3 text-left">Satuan</th><th class="px-5 py-3 text-right">Stok</th><th class="px-5 py-3 text-right">Minimum</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($obat as $item)<tr><td class="px-5 py-3 font-mono">{{ $item->kode }}</td><td class="px-5 py-3 font-medium">{{ $item->nama }}</td><td class="px-5 py-3">{{ $item->satuan_kecil }}</td><td class="px-5 py-3 text-right font-semibold {{ $item->stok <= $item->stok_minimum ? 'text-red-600' : 'text-green-600' }}">{{ number_format($item->stok, 2) }}</td><td class="px-5 py-3 text-right">{{ number_format($item->stok_minimum, 2) }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada data obat.</td></tr>@endforelse</tbody></table><div class="p-5">{{ $obat->links() }}</div></div></div>
@endsection
