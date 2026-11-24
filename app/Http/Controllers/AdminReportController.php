<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function index(): Response
    {
        $groups = [
            ['title' => 'Pelayanan dan persediaan', 'description' => 'Ringkasan operasional tanpa rincian klinis pasien.', 'reports' => [
                ['title' => 'Ringkasan pelayanan', 'description' => 'Rekap kunjungan per poliklinik dan status, dengan filter periode dan ekspor Excel.', 'href' => route('laporan.kunjungan')],
                ['title' => 'Stok obat dan BHP', 'description' => 'Posisi stok obat dan BHP, batas minimum, serta ekspor Excel.', 'href' => route('laporan.stok')],
            ]],
            ['title' => 'Kepesertaan dan pelayanan klinis', 'description' => 'Rekap agregat dengan filter periode dan ekspor Excel.', 'reports' => [
                ['title' => 'Kunjungan per unit kerja', 'description' => 'Unit kerja tercatat pada data kunjungan.', 'href' => route('laporan.kunjungan', ['kelompok' => 'unit'])],
                ['title' => 'Kategori peserta', 'description' => 'Distribusi kunjungan menurut kategori peserta tercatat.', 'href' => route('laporan.kunjungan', ['kelompok' => 'kepesertaan'])],
                ['title' => 'Diagnosis terbanyak', 'description' => 'Rekap kode ICD-10 dengan batas agregasi yang berlaku.', 'href' => route('pendaftaran.laporan-top-diagnosa')],
                ['title' => 'Pengkajian dan pemeriksaan', 'description' => 'Pilih pengkajian dikirim atau pemeriksaan ditandatangani.', 'href' => route('laporan.kunjungan', ['kelompok' => 'pengkajian'])],
                ['title' => 'Tindakan terlaksana', 'description' => 'Jumlah kunjungan dengan tindakan yang tercatat sudah dilakukan.', 'href' => route('laporan.kunjungan', ['kelompok' => 'tindakan'])],
                ['title' => 'Rujukan keluar', 'description' => 'Jumlah kunjungan dengan surat rujukan keluar.', 'href' => route('laporan.kunjungan', ['kelompok' => 'rujukan'])],
            ]],
        ];

        return Inertia::render('admin/laporan', ['groups' => $groups]);
    }
}
