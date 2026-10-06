@extends('layouts.app')

@section('title', 'Laporan Top Diagnosa')
@section('page-title', 'Laporan Top Diagnosa')

@section('content')
<div class="space-y-6">
    {{-- Filter Periode --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai', $tanggalMulai->format('Y-m-d')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" value="{{ request('tanggal_selesai', $tanggalSelesai->format('Y-m-d')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Summary Card --}}
    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-sm p-6 text-white">
        <h3 class="text-lg font-bold">Periode Laporan</h3>
        <p class="text-sm mt-1 opacity-90">
            {{ $tanggalMulai->format('d F Y') }} - {{ $tanggalSelesai->format('d F Y') }}
        </p>
        <p class="text-3xl font-bold mt-3">{{ $topDiagnosa->count() }} Diagnosa</p>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Peringkat</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Kode ICD-10</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Nama Diagnosa</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Jumlah Kasus</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700">Persentase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $totalKasus = $topDiagnosa->sum('total');
                    @endphp
                    @forelse($topDiagnosa as $index => $diagnosa)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-center">
                            <div class="w-8 h-8 rounded-full {{ $index < 3 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' : 'bg-gray-200' }} flex items-center justify-center">
                                <span class="text-sm font-bold {{ $index < 3 ? 'text-white' : 'text-gray-700' }}">{{ $index + 1 }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-mono font-semibold text-blue-600">{{ $diagnosa->kode }}</td>
                        <td class="px-6 py-4 text-sm text-gray-800">{{ $diagnosa->nama }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-lg font-bold text-gray-800">{{ $diagnosa->total }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <div class="flex-1 max-w-[150px] bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-600 h-2 rounded-full" style="width: {{ ($diagnosa->total / $totalKasus * 100) }}%"></div>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">{{ number_format($diagnosa->total / $totalKasus * 100, 1) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">
                            Tidak ada data diagnosa pada periode ini
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($topDiagnosa->isNotEmpty())
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-sm font-bold text-gray-800 text-right">Total</td>
                        <td class="px-6 py-4 text-center text-lg font-bold text-gray-800">{{ $totalKasus }}</td>
                        <td class="px-6 py-4 text-center text-sm font-bold text-gray-700">100%</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
