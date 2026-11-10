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
                'description' => 'Laporan kunjungan dan pelayanan klinis lintas poliklinik.',
                'reports' => [
                    ['title' => 'Kunjungan pasien', 'description' => 'Jumlah kunjungan menurut periode, poliklinik, dan status.', 'href' => route('laporan.kunjungan')],
                    ['title' => 'Pengkajian awal', 'description' => 'Riwayat pengkajian perawat lintas poli.', 'href' => route('admin.laporan.pengkajian')],
                    ['title' => 'Tindakan perawat', 'description' => 'Instruksi dokter, status tindakan, hasil, dan pelaksana.', 'href' => route('admin.laporan.tindakan-perawat')],
                    ['title' => 'Pemeriksaan dokter', 'description' => 'Pemeriksaan draf dan final dari seluruh dokter.', 'href' => route('admin.laporan.pemeriksaan')],
                    ['title' => 'Tindakan dokter', 'description' => 'Tindakan yang dicatat pada pemeriksaan dokter.', 'href' => route('admin.laporan.dokter', ['jenis' => 'tindakan'])],
                    ['title' => 'Resep dokter', 'description' => 'Rincian obat yang diresepkan dari seluruh dokter.', 'href' => route('admin.laporan.dokter', ['jenis' => 'obat'])],
                    ['title' => 'Rincian kunjungan pendaftaran', 'description' => 'Daftar kunjungan dengan akses ke detail dan cetak antrean.', 'href' => route('pendaftaran.laporan-kunjungan')],
                    ['title' => 'Diagnosis terbanyak', 'description' => 'Peringkat diagnosis berdasarkan periode kunjungan.', 'href' => route('pendaftaran.laporan-top-diagnosa')],
                ],
            ],
            [
                'title' => 'Farmasi dan persediaan',
                'description' => 'Laporan penyerahan obat, resep luar, dan ketersediaan stok.',
                'reports' => [
                    ['title' => 'Penyerahan obat', 'description' => 'Penyerahan resep internal dan jumlah yang diberikan.', 'href' => route('farmasi.laporan')],
                    ['title' => 'Obat langsung', 'description' => 'Pemberian obat tanpa transaksi resep kunjungan.', 'href' => route('farmasi.laporan.pemberian-langsung')],
                    ['title' => 'Resep luar', 'description' => 'Resep yang ditebus di luar klinik.', 'href' => route('farmasi.laporan.resep-luar')],
                    ['title' => 'Stok obat dan BHP', 'description' => 'Stok fisik, stok layak pakai, dan item pada atau di bawah batas minimum.', 'href' => route('laporan.stok')],
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
