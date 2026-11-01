<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function index(): Response
    {
        $groups = [
            [
                'title' => 'Pelayanan klinik',
                'description' => 'Ringkasan pendaftaran dan tindakan klinis lintas petugas pada poli aktif.',
                'reports' => [
                    ['title' => 'Kunjungan pasien', 'description' => 'Filter tanggal, poli, dan status; tersedia ekspor.', 'href' => route('laporan.kunjungan')],
                    ['title' => 'Pengkajian awal', 'description' => 'Riwayat pengkajian perawat lintas poli.', 'href' => route('admin.laporan.pengkajian')],
                    ['title' => 'Tindakan perawat', 'description' => 'Instruksi dokter, status tindakan, hasil, dan pelaksana.', 'href' => route('admin.laporan.tindakan-perawat')],
                    ['title' => 'Tindakan dan obat dokter', 'description' => 'Catatan tindakan serta resep dari seluruh dokter.', 'href' => route('admin.laporan.dokter', ['jenis' => 'tindakan'])],
                    ['title' => 'Resep dokter', 'description' => 'Rincian obat yang diresepkan dari seluruh dokter.', 'href' => route('admin.laporan.dokter', ['jenis' => 'obat'])],
                ],
            ],
            [
                'title' => 'Farmasi dan persediaan',
                'description' => 'Tiga alur farmasi tetap dilaporkan terpisah agar transaksi tidak tercampur.',
                'reports' => [
                    ['title' => 'Penyerahan resep klinik', 'description' => 'Penyerahan resep internal dan jumlah yang diberikan.', 'href' => route('farmasi.laporan')],
                    ['title' => 'Obat langsung', 'description' => 'Pemberian obat tanpa transaksi resep kunjungan.', 'href' => route('farmasi.laporan.pemberian-langsung')],
                    ['title' => 'Resep luar', 'description' => 'Resep yang ditebus di luar klinik.', 'href' => route('farmasi.laporan.resep-luar')],
                    ['title' => 'Stok dan batch', 'description' => 'Persediaan layak pakai dan item di bawah stok minimum.', 'href' => route('laporan.stok')],
                ],
            ],
        ];

        if (config('clinic.modules.laboratory', false)) {
            $groups[] = [
                'title' => 'Penunjang',
                'description' => 'Laporan modul yang sedang aktif di klinik.',
                'reports' => [
                    ['title' => 'Laboratorium', 'description' => 'Hasil pemeriksaan laboratorium.', 'href' => route('laporan.laboratorium')],
                ],
            ];
        }

        return Inertia::render('admin/laporan', ['groups' => $groups]);
    }
}
