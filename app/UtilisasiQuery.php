<?php

namespace App;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ObatBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UtilisasiQuery
{
    public function visits(string $from, string $to): Builder
    {
        return Kunjungan::whereDate('tanggal', '>=', $from)->whereDate('tanggal', '<=', $to)->where('status', '!=', 'batal');
    }

    public function report(string $from, string $to): array
    {
        $visits = $this->visits($from, $to);
        $usage = DB::table('stok_mutasi')->join('obat', 'obat.id', '=', 'stok_mutasi.obat_id')
            ->whereIn('referensi_type', ['Farmasi', 'TindakanBhp', 'FarmasiRetur'])
            ->whereIn('stok_mutasi.jenis', ['keluar', 'retur'])
            ->whereDate('stok_mutasi.created_at', '>=', $from)->whereDate('stok_mutasi.created_at', '<=', $to)
            ->select('obat.id', 'obat.nama', 'obat.jenis', 'obat.satuan_kecil')
            ->selectRaw("SUM(CASE WHEN stok_mutasi.jenis = 'retur' THEN -stok_mutasi.jumlah ELSE stok_mutasi.jumlah END) as quantity")
            ->selectRaw("SUM(CASE WHEN stok_mutasi.jenis = 'retur' THEN -stok_mutasi.jumlah * stok_mutasi.harga ELSE stok_mutasi.jumlah * stok_mutasi.harga END) as cost")
            ->groupBy('obat.id', 'obat.nama', 'obat.jenis', 'obat.satuan_kecil')->orderByDesc('quantity')->get();
        $diagnoses = DB::table('diagnosa')->join('pemeriksaan', 'pemeriksaan.id', '=', 'diagnosa.pemeriksaan_id')
            ->join('kunjungan', 'kunjungan.id', '=', 'pemeriksaan.kunjungan_id')
            ->where('pemeriksaan.status', 'selesai')->where('kunjungan.status', '!=', 'batal')
            ->whereDate('kunjungan.tanggal', '>=', $from)->whereDate('kunjungan.tanggal', '<=', $to)
            ->select('diagnosa.kode_icd10 as code', 'diagnosa.nama_diagnosa as name')
            ->selectRaw('COUNT(DISTINCT pemeriksaan.kunjungan_id) as count')
            ->groupBy('diagnosa.kode_icd10', 'diagnosa.nama_diagnosa')->orderByDesc('count')->orderBy('code')->limit(10)->get();
        $lowStock = Obat::where('is_active', true)
            ->withSum(['batches as usable_stock' => fn ($query) => $query->usable()], 'stok')
            ->whereRaw('(SELECT COALESCE(SUM(stok), 0) FROM obat_batch WHERE obat_batch.obat_id = obat.id AND status = ? AND expired_at > ? AND stok > 0) <= obat.stok_minimum', ['tersedia', today()->toDateString()])
            ->orderBy('nama')->get()->map(fn (Obat $medicine): array => ['name' => $medicine->nama, 'stock' => (float) $medicine->usable_stock, 'minimum' => (float) $medicine->stok_minimum, 'unit' => $medicine->satuan_kecil]);
        $costCenters = DB::table('stok_mutasi')->join('obat', 'obat.id', '=', 'stok_mutasi.obat_id')
            ->leftJoin('kunjungan', 'kunjungan.id', '=', 'stok_mutasi.kunjungan_id')
            ->whereIn('referensi_type', ['Farmasi', 'TindakanBhp', 'FarmasiRetur'])->whereIn('stok_mutasi.jenis', ['keluar', 'retur'])
            ->whereDate('stok_mutasi.created_at', '>=', $from)->whereDate('stok_mutasi.created_at', '<=', $to)
            ->select('kunjungan.unit_kerja', 'stok_mutasi.cost_center')
            ->selectRaw("SUM(CASE WHEN stok_mutasi.jenis = 'retur' THEN -stok_mutasi.jumlah * stok_mutasi.harga ELSE stok_mutasi.jumlah * stok_mutasi.harga END) as cost")
            ->groupBy('kunjungan.unit_kerja', 'stok_mutasi.cost_center')->orderByDesc('cost')->get()
            ->map(fn ($row): array => ['unit' => $row->unit_kerja ?? 'Historis / belum terverifikasi', 'costCenter' => $row->cost_center ?? 'Historis / belum terverifikasi', 'cost' => round((float) $row->cost, 2)]);
        $prescriptions = Farmasi::where('status', 'selesai')->whereHas('items', fn ($query) => $query->where('jumlah_diberikan', '>', 0))->whereDate('dispensed_at', '>=', $from)->whereDate('dispensed_at', '<=', $to)->count();

        return [
            'stats' => [
                'visits' => (clone $visits)->count(), 'patients' => (clone $visits)->distinct()->count('pasien_id'),
                'completed' => (clone $visits)->where('status', 'selesai')->count(), 'prescriptions' => $prescriptions,
                'medicineCost' => round((float) $usage->where('jenis', 'obat')->sum('cost'), 2),
                'supplyCost' => round((float) $usage->where('jenis', 'bhp')->sum('cost'), 2),
                'lowStock' => $lowStock->count(),
                'expiredBatches' => ObatBatch::where('stok', '>', 0)->whereDate('expired_at', '<=', today())->count(),
                'nearExpiryBatches' => ObatBatch::where('stok', '>', 0)->whereDate('expired_at', '>', today())->whereDate('expired_at', '<=', today()->addDays(90))->count(),
                'quarantinedBatches' => ObatBatch::where('stok', '>', 0)->where('status', 'karantina')->count(),
            ],
            'costCenters' => $costCenters,
            'units' => (clone $visits)->select('unit_kerja', 'cost_center')->selectRaw('COUNT(*) as visits')->groupBy('unit_kerja', 'cost_center')->orderByDesc('visits')->get()
                ->map(fn ($row): array => ['unit' => $row->unit_kerja ?? 'Belum terverifikasi', 'costCenter' => $row->cost_center ?? 'Belum terverifikasi', 'visits' => (int) $row->visits]),
            'clinics' => (clone $visits)->join('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
                ->select('poliklinik.nama')->selectRaw('COUNT(*) as visits')->groupBy('poliklinik.nama')->get()
                ->map(fn ($row): array => ['name' => $row->nama, 'visits' => (int) $row->visits]),
            'categories' => (clone $visits)->select('kategori_peserta')->selectRaw('COUNT(*) as visits')->groupBy('kategori_peserta')->get(),
            'diagnoses' => $diagnoses->map(fn ($row): array => ['code' => $row->code, 'name' => $row->name, 'count' => (int) $row->count]),
            'usage' => $usage->map(fn ($row): array => ['id' => $row->id, 'name' => $row->nama, 'type' => $row->jenis, 'unit' => $row->satuan_kecil, 'quantity' => (float) $row->quantity, 'cost' => round((float) $row->cost, 2)]),
            'lowStock' => $lowStock,
        ];
    }
}
