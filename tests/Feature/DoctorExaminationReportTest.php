<?php

namespace Tests\Feature;

use App\DateRangeFilter;
use App\Exports\PemeriksaanExport;
use App\Models\{Kunjungan, Nakes, Pasien, Pemeriksaan, Poliklinik, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DoctorExaminationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_cards_are_scoped_to_the_doctor_for_general_and_dental_services(): void
    {
        foreach (['umum', 'gigi'] as $type) {
            $user = User::factory()->create(['role' => 'dokter']);
            $doctor = Nakes::create(['kode' => $type, 'nama' => 'Dokter '.$type, 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
            $other = Nakes::create(['kode' => $type.'-other', 'nama' => 'Dokter lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
            $clinic = Poliklinik::create(['kode' => $type, 'nama' => $type, 'jenis' => $type, 'is_active' => true]);
            $patient = Pasien::create(['no_rm' => $type, 'nama' => '=Pasien '.$type]);
            foreach ([$doctor, $other] as $staff) {
                foreach (['draft', 'final', 'waiting'] as $state) {
                    $visit = Kunjungan::create(['no_kunjungan' => $type.$staff->id.$state, 'pasien_id' => $patient->id, 'dokter_id' => $staff->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => $state === 'final' ? 'selesai' : 'pemeriksaan']);
                    if ($state !== 'waiting') {
                        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $staff->id, 'signed_at' => $state === 'final' ? now() : null]);
                    }
                }
            }
            $this->actingAs($user)->get(route('dokter.laporan-pemeriksaan'))->assertInertia(fn (Assert $page) => $page
                ->component('dokter/appointments')->where('isReport', true)->where('visits.total', 2)
                ->where('stats.total', 2)->where('stats.draft', 1)->where('stats.final', 1)->where('stats.patients', 1));
            $this->get(route('dokter.kunjungan'))->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)->where('stats.today', 3)->where('stats.completed', 1));
            $this->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)->where('stats.waiting', 1)->where('stats.inProgress', 1)->where('stats.completed', 1));
            $this->get(route('dokter.laporan-pemeriksaan', ['search' => 'Tidak cocok']))->assertInertia(fn (Assert $page) => $page->where('visits.total', 0)->where('stats.total', 0));
            $this->assertSame(0, (new PemeriksaanExport($doctor->id, new DateRangeFilter(null, today()->subDay()->format('Y-m-d'))))->query()->count());
            Excel::fake();
            $this->get(route('dokter.laporan-pemeriksaan.export', ['dokter_id' => $other->id]))->assertOk();
            Excel::assertDownloaded('laporan-pemeriksaan-'.today()->format('Y-m-d').'.xlsx', function (PemeriksaanExport $export) use ($doctor): bool {
                $rows = $export->query()->get();
                $this->assertCount(2, $rows);
                $this->assertTrue($rows->every(fn ($visit) => $visit->dokter_id === $doctor->id));
                $this->assertStringStartsWith(chr(39).'=', $export->map($rows->first())[3]);

                return true;
            });
        }
    }

    public function test_nurse_cannot_access_doctor_reports(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'perawat']))->get(route('dokter.laporan-pemeriksaan'))->assertForbidden();
        $this->get(route('dokter.laporan-pemeriksaan.export'))->assertForbidden();
    }
}
