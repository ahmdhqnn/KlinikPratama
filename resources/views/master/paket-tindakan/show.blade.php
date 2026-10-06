@extends('layouts.app')

@section('title', 'Detail Paket - ' . $paketTindakan->nama)
@section('page-title', 'Detail Paket Tindakan: ' . $paketTindakan->nama)

@section('content')
<div class="py-4 space-y-6" x-data="{ jenisItem: 'tindakan' }">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('master.paket-tindakan.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Kembali ke Daftar Paket</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $paketTindakan->nama }} ({{ $paketTindakan->kode }})</h2>
            <p class="text-sm text-gray-500">Tarif Paket: <strong class="text-gray-800 font-mono">Rp {{ number_format($paketTindakan->tarif, 0, ',', '.') }}</strong> | Status: <span class="badge {{ $paketTindakan->is_active ? 'badge-green' : 'badge-red' }}">{{ $paketTindakan->is_active ? 'Aktif' : 'Nonaktif' }}</span></p>
        </div>
    </div>

    {{-- Form Tambah Item ke Paket --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Tambah Layanan ke Paket Ini</h3>
        <form method="POST" action="{{ route('master.paket-tindakan.item.store', $paketTindakan) }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Layanan</label>
                    <select name="jenis" x-model="jenisItem" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="tindakan">Tindakan Medis</option>
                        <option value="lab">Pemeriksaan Laboratorium</option>
                    </select>
                </div>

                <div class="md:col-span-2" x-show="jenisItem === 'tindakan'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Tindakan Medis</label>
                    <select name="tindakan_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Tindakan --</option>
                        @foreach($tindakanList as $tindakan)
                        <option value="{{ $tindakan->id }}">{{ $tindakan->nama }} (Rp {{ number_format($tindakan->tarif, 0, ',', '.') }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2" x-show="jenisItem === 'lab'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Pemeriksaan Lab</label>
                    <select name="laboratorium_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Lab --</option>
                        @foreach($labList as $lab)
                        <option value="{{ $lab->id }}">{{ $lab->nama }} (Rp {{ number_format($lab->tarif, 0, ',', '.') }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        + Tambah ke Paket
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Daftar Item dalam Paket --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Daftar Layanan Termasuk dalam Paket</h3>
            <span class="text-xs text-gray-500">Total: {{ $paketTindakan->items->count() }} layanan</span>
        </div>
        <table class="w-full">
            <thead>
                <tr>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">No</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Jenis</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-left">Nama Layanan</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-right">Tarif Standar</th>
                    <th class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @php $totalTarifStandar = 0; @endphp
                @forelse($paketTindakan->items as $index => $item)
                @php
                    $nama = $item->jenis === 'tindakan' ? ($item->tindakan?->nama ?? '-') : ($item->laboratorium?->nama ?? '-');
                    $tarif = $item->jenis === 'tindakan' ? ($item->tindakan?->tarif ?? 0) : ($item->laboratorium?->tarif ?? 0);
                    $totalTarifStandar += $tarif;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="badge {{ $item->jenis === 'tindakan' ? 'badge-blue' : 'badge-green' }}">
                            {{ $item->jenis === 'tindakan' ? 'Tindakan Medis' : 'Laboratorium' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $nama }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700">Rp {{ number_format($tarif, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" action="{{ route('master.paket-tindakan.item.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium"
                                    onclick="return confirm('Hapus item ini dari paket?')">
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada layanan yang dimasukkan ke paket ini</td></tr>
                @endforelse
            </tbody>
            @if($paketTindakan->items->count())
            <tfoot class="border-t-2 border-gray-100 bg-gray-50 text-xs font-semibold">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right text-gray-600">Total Tarif Standar Terpisah:</td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-800">Rp {{ number_format($totalTarifStandar, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="3" class="px-4 py-2 text-right text-blue-700">Hemat Paket:</td>
                    <td class="px-4 py-2 text-right font-mono font-bold text-emerald-600">Rp {{ number_format(max(0, $totalTarifStandar - $paketTindakan->tarif), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
