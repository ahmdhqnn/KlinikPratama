<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Laboratorium;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LaporanInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_reports_render_inertia_data_and_filters(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-report@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Laporan']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261007-0001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'selesai',
            'jenis_pasien' => 'lama',
        ]);
        Obat::create([
            'kode' => '00001',
            'nama' => 'Paracetamol',
            'jenis' => 'obat',
            'satuan_kecil' => 'tablet',
            'stok' => 4,
            'stok_minimum' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('laporan.kunjungan', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('laporan/kunjungan')
            ->where('stats.total', 1)
            ->where('visits.data.0.patient', 'Pasien Laporan')
        );

        $this->get('/laporan/pendapatan')->assertNotFound();
        $this->get('/laporan/utilisasi')->assertNotFound();

        $this->get(route('laporan.stok', ['jenis' => 'obat', 'stok_rendah' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('laporan/stok')
                ->where('stats.lowStockItems', 1)
                ->where('medicines.data.0.name', 'Paracetamol')
            );

        config(['clinic.modules.laboratory' => true]);

        $this->get(route('laporan.laboratorium', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('laporan/laboratorium')
            ->has('results.data', 0)
        );

        $this->get(route('laporan.laboratorium.export'))->assertDownload('laporan-laboratorium-'.today()->toDateString().'.xlsx');
        $stock = $this->get(route('laporan.stok.export', ['jenis' => 'obat', 'stok_rendah' => 1, 'page' => 2]));
        $stock->assertDownload('laporan-stok-'.today()->toDateString().'.xlsx');
        $sheet = IOFactory::load($stock->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('00001', $sheet->getCell('A2')->getValue());
        $this->assertSame(0.0, (float) $sheet->getCell('F2')->getValue());
        $this->assertSame('n', $sheet->getCell('F2')->getDataType());

        $lab = Laboratorium::create(['kode' => 'LAB-UJI', 'nama' => 'Panel Uji']);
        $indicator = $lab->indikator()->create(['nama' => 'Indikator Uji', 'satuan' => 'mg/dL']);
        $lab->hasil()->create(['kunjungan_id' => $visit->id, 'status' => 'selesai'])
            ->detail()->create(['indikator_id' => $indicator->id, 'nilai' => '0', 'keterangan' => '=teks hasil']);
        $laboratory = $this->get(route('laporan.laboratorium.export', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]));
        $sheet = IOFactory::load($laboratory->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Indikator Uji', $sheet->getCell('G2')->getValue());
        $this->assertSame('0', $sheet->getCell('H2')->getValue());
        $this->assertSame('mg/dL', $sheet->getCell('I2')->getValue());
        $this->assertSame('=teks hasil', $sheet->getCell('J2')->getValue());
        $this->assertSame('s', $sheet->getCell('J2')->getDataType());
    }
}
