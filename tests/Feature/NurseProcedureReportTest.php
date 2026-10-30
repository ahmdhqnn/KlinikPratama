<?php

namespace Tests\Feature;

use App\Exports\NursingProcedureExport;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\NursingProcedureReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class NurseProcedureReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_report_print_and_excel_export_are_scoped_to_the_assigned_clinic_and_follow_filters(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'REP-OWN', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        Nakes::create(['kode' => 'REP-NURSE', 'nama' => 'Perawat Uji', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $foreignClinic = Poliklinik::create(['kode' => 'REP-OTHER', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-REP', 'nama' => '=Nama Pasien']);
        $catalog = Tindakan::create(['kode' => 'TD-REP', 'nama' => 'Perawatan luka', 'kategori' => 'medis', 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-REP', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        $completed = TindakanKunjungan::create([
            'kunjungan_id' => $visit->id, 'tindakan_id' => $catalog->id, 'nama_tindakan' => 'Perawatan luka',
            'jumlah' => 1, 'catatan' => 'Ikuti instruksi dokter', 'execution_role' => 'perawat', 'execution_status' => 'completed',
            'performed_by' => $nurse->id, 'performed_at' => now(), 'execution_result' => 'Perawatan selesai, pasien nyaman.',
        ]);
        TindakanKunjungan::create([
            'kunjungan_id' => $visit->id, 'tindakan_id' => $catalog->id, 'nama_tindakan' => 'Perawatan luka lanjutan',
            'jumlah' => 1, 'execution_role' => 'perawat', 'execution_status' => 'pending',
        ]);
        $foreignPatient = Pasien::create(['no_rm' => 'RM-REP-OTHER', 'nama' => 'Pasien Poli Lain']);
        $foreignVisit = Kunjungan::create(['no_kunjungan' => 'KNJ-REP-OTHER', 'pasien_id' => $foreignPatient->id, 'poliklinik_id' => $foreignClinic->id, 'tanggal' => today(), 'status' => 'tindakan']);
        TindakanKunjungan::create([
            'kunjungan_id' => $foreignVisit->id, 'tindakan_id' => $catalog->id, 'nama_tindakan' => 'Tindakan lintas poli',
            'jumlah' => 1, 'execution_role' => 'perawat', 'execution_status' => 'pending',
        ]);

        $this->actingAs($nurse)->get(route('perawat.laporan-tindakan'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/tindakan/report')
            ->where('clinicName', 'Poli Umum')
            ->where('stats.total', 2)
            ->where('stats.visits', 1)
            ->where('stats.pending', 1)
            ->where('stats.completed', 1)
            ->where('procedures.total', 2)
            ->where('procedures.data.0.visitNumber', 'KNJ-REP'));

        $this->get(route('perawat.laporan-tindakan', ['status' => 'completed', 'search' => 'Perawatan luka']))->assertInertia(fn (Assert $page) => $page
            ->where('stats.total', 1)
            ->where('procedures.total', 1)
            ->where('procedures.data.0.result', 'Perawatan selesai, pasien nyaman.'));

        $this->get(route('perawat.laporan-tindakan.cetak', ['status' => 'completed']))->assertInertia(fn (Assert $page) => $page
            ->where('printMode', true)
            ->where('procedures.total', 1)
            ->where('procedures.data.0.name', 'Perawatan luka'));

        Excel::fake();
        $this->get(route('perawat.laporan-tindakan.export', ['status' => 'completed']))->assertOk();
        Excel::assertDownloaded('laporan-tindakan-perawat-'.today()->format('Y-m-d').'.xlsx', function (NursingProcedureExport $export) use ($clinic, $completed): bool {
            $this->assertSame($clinic->id, $export->query()->firstOrFail()->kunjungan->poliklinik_id);
            $this->assertSame(chr(39).'=Nama Pasien', $export->map($completed->fresh(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter']))[3]);
            $this->assertContains('Hasil pelaksanaan', $export->headings());

            return true;
        });

        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'nursing_procedure.report_view']);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'nursing_procedure.report_print']);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'nursing_procedure.report_export']);
        $this->assertSame(0, (new NursingProcedureReportQuery)->query(['dari' => today()->addDay()->toDateString()], $clinic->id)->count());
    }

    public function test_other_roles_and_unassigned_nurses_cannot_open_the_action_report(): void
    {
        foreach (['pendaftaran', 'dokter', 'farmasi'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['perawat.laporan-tindakan', 'perawat.laporan-tindakan.cetak', 'perawat.laporan-tindakan.export'] as $route) {
                $response = $this->get(route($route));
                if ($role === 'pendaftaran') {
                    $response->assertRedirect(route('pendaftaran.dashboard'));
                } else {
                    $response->assertForbidden();
                }
            }
        }

        $this->actingAs(User::factory()->create(['role' => 'perawat']))
            ->get(route('perawat.laporan-tindakan'))->assertForbidden();
    }
}
