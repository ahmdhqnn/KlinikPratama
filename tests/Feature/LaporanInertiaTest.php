<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
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

    public function test_diagnosis_search_matches_code_or_name_and_exports_the_filtered_result(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'DIAG', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-DIAG', 'nama' => 'Pasien Diagnosis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'K-DIAG', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        $examination = $visit->pemeriksaan()->create(['status' => 'selesai']);
        foreach (['J00' => 'Nasofaringitis', 'R50.9' => 'Demam'] as $code => $name) {
            $examination->diagnosa()->create(['kode_icd10' => $code, 'nama_diagnosa' => $name, 'jenis' => 'utama']);
        }
        $this->expandSyntheticDiagnosisPopulation($visit);
        foreach (['J00', 'Nasofaringitis'] as $search) {
            $this->actingAs($admin)->get('/pendaftaran/laporan-top-diagnosa?search='.$search)->assertInertia(fn (Assert $page) => $page
                ->where('summary.diagnosisCount', 1)->where('summary.caseCount', 5)->where('diagnoses.data.0.code', 'J00')->where('filters.search', $search));
        }
        $response = $this->get('/pendaftaran/laporan-top-diagnosa/export?search=J00');
        $response->assertDownload();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(2, $sheet->getHighestDataRow());
        $this->assertContains('J00', $sheet->toArray()[1]);
    }

    public function test_schedule_search_matches_doctor_or_clinic_without_returning_other_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        foreach (['Target', 'Pembanding'] as $name) {
            $clinic = Poliklinik::create(['kode' => $name, 'nama' => 'Poli '.$name, 'jenis' => 'umum', 'is_active' => true]);
            $doctor = Nakes::create(['kode' => $name, 'nama' => 'Dokter '.$name, 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
            JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'berlaku_mulai' => today()->toDateString()]);
        }
        foreach (['Dokter Target', 'Poli Target'] as $search) {
            $this->actingAs($admin)->get('/pendaftaran/jadwal-praktik?search='.urlencode($search))->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', $search)->where('schedules.total', 1)->has('schedules.data', 1));
        }
        $this->get('/pendaftaran/jadwal-praktik?search=tidak-ada')->assertInertia(fn (Assert $page) => $page->where('schedules.total', 0));
    }

    public function test_report_search_filters_rows_totals_and_excel_consistently(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'SEARCH', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        foreach (['Target', 'Pembanding'] as $index => $name) {
            $patient = Pasien::create(['no_rm' => 'RM-SEARCH-'.$index, 'nama' => 'Pasien '.$name]);
            Kunjungan::create(['no_kunjungan' => 'K-SEARCH-'.$index, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai', 'jenis_pasien' => 'lama']);
            Obat::create(['kode' => 'ITEM-'.$index, 'nama' => 'Obat '.$name, 'jenis' => 'obat', 'stok' => 0, 'stok_minimum' => 5, 'is_active' => true]);
        }

        $this->actingAs($admin)->get('/laporan/kunjungan')->assertInertia(fn (Assert $page) => $page->component('laporan/agregat')->where('rows.0.total', 2)->missing('visits'));
        $this->get('/laporan/stok?search=Target&jenis=obat&stok_rendah=1')->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'Target')->where('stats.totalItems', 1)->where('stats.lowStockItems', 1)
            ->has('medicines.data', 1)->where('medicines.data.0.name', 'Obat Target'));

        foreach (['kunjungan' => ['A2', 'Poli Umum'], 'stok' => ['A2', 'ITEM-0']] as $report => [$cell, $expected]) {
            $response = $this->get('/laporan/'.$report.'/export?search=Target');
            $response->assertDownload();
            $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
            $this->assertSame($expected, $sheet->getCell($cell)->getValue());
            $this->assertSame(2, $sheet->getHighestDataRow());
        }
        $this->get('/laporan/kunjungan?search=tidak-ada')->assertInertia(fn (Assert $page) => $page->where('rows.0.total', 2)->missing('visits'));
        $this->get('/laporan/stok?search=tidak-ada')->assertInertia(fn (Assert $page) => $page->where('stats.totalItems', 0)->has('medicines.data', 0));
        foreach (['stok'] as $report) {
            $this->from('/laporan/'.$report)->get('/laporan/'.$report.'?search='.str_repeat('a', 101))->assertSessionHasErrors('search');
        }
    }

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
            ->component('laporan/agregat')->where('rows.0.total', 1)->missing('visits')
        );

        $visit->update(['unit_kerja' => 'Unit Uji', 'kategori_peserta' => 'asn', 'hak_layanan_snapshot' => ['status_kepegawaian' => 'aktif']]);
        $this->get(route('laporan.kunjungan', [
            'kelompok' => 'unit', 'poli' => 'umum', 'kategori' => 'asn',
            'status_kepegawaian' => 'aktif', 'dari' => today()->toDateString(), 'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)->where('rows.0.status', 'Unit Uji')->where('rows.0.total', 1)->missing('visits')
        );

        $this->get('/laporan/pendapatan')->assertNotFound();
        $this->get('/laporan/utilisasi')->assertNotFound();

        $this->get(route('laporan.stok', ['jenis' => 'obat', 'stok_rendah' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('laporan/stok')
                ->where('stats.lowStockItems', 1)
                ->where('medicines.data.0.name', 'Paracetamol')
            );

        $stock = $this->get(route('laporan.stok.export', ['jenis' => 'obat', 'stok_rendah' => 1, 'page' => 2]));
        $stock->assertDownload('laporan-stok-'.today()->toDateString().'.xlsx');
        $sheet = IOFactory::load($stock->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('00001', $sheet->getCell('A2')->getValue());
        $this->assertSame(0.0, (float) $sheet->getCell('F2')->getValue());
        $this->assertSame('n', $sheet->getCell('F2')->getDataType());

    }
}
