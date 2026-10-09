<?php

namespace Tests\Feature;

use App\Exports\UtilisasiExport;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\StokMutasi;
use App\Models\User;
use App\UtilisasiQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UtilizationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_receives_aggregated_utilization_actual_cost_and_no_patient_identifiers(): void
    {
        $manager = User::factory()->create(['role' => 'manajemen', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SECRET', 'nama' => 'Identitas Rahasia', 'nik' => '0000000000000001']);
        $clinic = Poliklinik::create(['kode' => 'PU', 'nama' => 'Umum', 'jenis' => 'umum']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-U1', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai', 'unit_kerja' => 'Biro Umum', 'cost_center' => 'CC-U1']);
        $cancelled = Kunjungan::create(['no_kunjungan' => 'KNJ-U2', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'batal']);
        $exam = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $exam->diagnosa()->create(['kode_icd10' => 'I10', 'nama_diagnosa' => 'Hipertensi', 'jenis' => 'utama']);
        $draft = Pemeriksaan::create(['kunjungan_id' => $cancelled->id, 'status' => 'draft']);
        $draft->diagnosa()->create(['kode_icd10' => 'J00', 'nama_diagnosa' => 'Draf', 'jenis' => 'utama']);
        $medicine = Obat::create(['kode' => 'U-OBT', 'nama' => 'Obat uji', 'jenis' => 'obat', 'harga_jual' => 999999]);
        $supply = Obat::create(['kode' => 'U-BHP', 'nama' => 'BHP uji', 'jenis' => 'bhp']);
        foreach ([[$medicine, 'Farmasi', 'keluar', 3, 100], [$medicine, 'FarmasiRetur', 'retur', 1, 100], [$supply, 'TindakanBhp', 'keluar', 2, 50], [$medicine, 'ResepObat', 'keluar', 100, 999999]] as [$item, $ref, $type, $qty, $cost]) {
            StokMutasi::create(['obat_id' => $item->id, 'jenis' => $type, 'referensi_type' => $ref, 'jumlah' => $qty, 'harga' => $cost]);
        }
        $response = $this->actingAs($manager)->get(route('laporan.utilisasi', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]));
        $response->assertInertia(fn (Assert $page) => $page->component('laporan/utilisasi')
            ->where('report.stats.visits', 1)->where('report.stats.patients', 1)
            ->where('report.stats.medicineCost', 200)->where('report.stats.supplyCost', 100)
            ->where('report.units.0.costCenter', 'CC-U1')->where('report.diagnoses.0.code', 'I10')->has('report.diagnoses', 1));
        $props = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('Identitas Rahasia', $props);
        $this->assertStringNotContainsString('RM-SECRET', $props);
        $this->get(route('pelayanan.pasien.index'))->assertForbidden();
        $this->get(route('kepesertaan.index'))->assertForbidden();
        $this->get(route('laporan.utilisasi.export'))->assertDownload();
        $this->assertDatabaseHas('clinical_audit_events', ['actor_id' => $manager->id, 'action' => 'utilization.export']);
        $this->get(route('laporan.utilisasi', ['dari' => '2026-10-31', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
    }

    public function test_export_neutralizes_excel_formulas_in_source_labels(): void
    {
        $report = app(UtilisasiQuery::class)->report(today()->toDateString(), today()->toDateString());
        $report['units'] = collect([['unit' => '=HYPERLINK("bad")', 'costCenter' => '+123', 'visits' => 1]]);
        $export = new UtilisasiExport($report, today()->toDateString(), today()->toDateString());
        $values = collect($export->array())->flatten();
        $this->assertTrue($values->contains("'=HYPERLINK(\"bad\")"));
        $this->assertTrue($values->contains("'+123"));
    }
}
