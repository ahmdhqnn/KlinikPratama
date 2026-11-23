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
                ['title' => 'Ringkasan pelayanan', 'description' => 'Jumlah kunjungan menurut periode, poliklinik dan status.', 'href' => route('laporan.kunjungan')],
                ['title' => 'Stok obat dan BHP', 'description' => 'Ketersediaan persediaan dan batas minimum.', 'href' => route('laporan.stok')],
            ]],
        ];

        return Inertia::render('admin/laporan', ['groups' => $groups]);
    }
}
