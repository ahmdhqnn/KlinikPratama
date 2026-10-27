<?php

namespace Tests\Feature;

use App\Exports\ScreeningExport;
use App\Models\{Kunjungan, Nakes, Pasien, Poliklinik, Screening, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class NurseAssessmentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_nurse_reports_and_exports_only_personally_handled_patients_in_the_assigned_clinic(): void
    {
        foreach (['umum', 'gigi'] as $type) {
            $clinic = Poliklinik::create(['kode' => $type, 'nama' => $type, 'jenis' => $type, 'is_active' => true]);
            $user = User::factory()->create(['role' => 'perawat']);
            $nurse = Nakes::create(['kode' => $type, 'nama' => $type, 'kategori' => 'medis', 'jabatan' => $type === 'gigi' ? 'terapis_gigi' : 'perawat', 'user_id' => $user->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
            $colleague = Nakes::create(['kode' => $type.'-other', 'nama' => 'Rekan', 'kategori' => 'medis', 'jabatan' => 'perawat', 'poliklinik_id' => $clinic->id, 'is_active' => true]);
            $patient = Pasien::create(['no_rm' => $type, 'nama' => 'Pasien '.$type]);
            foreach ([$nurse, $colleague] as $staff) {
                $visit = Kunjungan::create(['no_kunjungan' => $type.$staff->id, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
                Screening::create(['kunjungan_id' => $visit->id, 'petugas_id' => $staff->id, 'keluhan' => '=1+1', 'submitted_at' => now()]);
            }
            $this->actingAs($user)->get(route('perawat.laporan-pengkajian', ['poliklinik_id' => 999]))->assertSessionHasErrors('poliklinik_id');
            $this->get(route('perawat.laporan-pengkajian'))->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/screening/history')->where('isReport', true)->where('isDental', $type === 'gigi')
                ->where('screenings.total', 1)->where('screenings.data.0.staff', $type));
            $this->get(route('pelayanan.screening.index'))->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 2)->where('stats.submitted', 2)->where('stats.waiting', 0));
            $foreignClinic = Poliklinik::create(['kode' => $type.'-foreign', 'nama' => 'Poli lain', 'jenis' => $type, 'is_active' => true]);
            $foreignVisit = Kunjungan::create(['no_kunjungan' => $type.'-foreign', 'pasien_id' => $patient->id, 'poliklinik_id' => $foreignClinic->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
            Screening::create(['kunjungan_id' => $foreignVisit->id, 'petugas_id' => $nurse->id, 'submitted_at' => now()]);
            $this->get(route('perawat.laporan-pengkajian', ['poliklinik_id' => $foreignClinic->id]))
                ->assertInertia(fn (Assert $page) => $page->where('screenings.total', 1)->where('screenings.data.0.clinic', $type));
            $this->get(route('perawat.laporan-pengkajian', ['search' => 'Tidak cocok']))
                ->assertInertia(fn (Assert $page) => $page->where('screenings.total', 0));
            $this->assertSame(0, (new ScreeningExport(['tanggal' => today()->subDay()->format('Y-m-d')], $nurse))->query()->count());
            Excel::fake();
            $this->get(route('perawat.laporan-pengkajian.export', ['poliklinik_id' => $foreignClinic->id]))->assertOk();
            Excel::assertDownloaded('laporan-pengkajian-'.$type.'-'.today()->format('Y-m-d').'.xlsx', function (ScreeningExport $export) use ($nurse, $type): bool {
                $rows = $export->query()->get();
                $this->assertCount(1, $rows);
                $this->assertSame($nurse->id, $rows->first()->petugas_id);
                $this->assertSame(chr(39).'=1+1', $export->map($rows->first())[8]);
                $this->assertSame($type === 'gigi', in_array('Lokasi Nyeri Gigi', $export->headings(), true));

                return true;
            });
        }
    }

    public function test_other_roles_cannot_access_the_nurse_report_or_export(): void
    {
        foreach (['pendaftaran', 'dokter', 'farmasi'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['perawat.laporan-pengkajian', 'perawat.laporan-pengkajian.export'] as $route) {
                $response = $this->get(route($route));
                if ($role === 'pendaftaran') {
                    $response->assertRedirect(route('pendaftaran.dashboard'));
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }
}
