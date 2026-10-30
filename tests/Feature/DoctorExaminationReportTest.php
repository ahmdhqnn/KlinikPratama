<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\DateRangeFilter;
use App\Exports\DoctorActivityExport;
use App\Exports\PemeriksaanExport;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\TindakanKunjungan;
use App\Models\User;
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
                        $examination = Pemeriksaan::create([
                            'kunjungan_id' => $visit->id,
                            'dokter_id' => $staff->id,
                            'signed_at' => $state === 'final' ? now() : null,
                            'signed_by_user_id' => $state === 'final' && $staff->id === $doctor->id ? $user->id : null,
                        ]);
                        if ($state === 'final' && $staff->id === $doctor->id) {
                            app(ClinicalNoteRecorder::class)->recordFinal($examination, ['anamnesis' => 'Final'], $user->id);
                        }
                    }
                }
            }
            $this->actingAs($user)->get(route('dokter.laporan-pemeriksaan'))->assertInertia(fn (Assert $page) => $page
                ->component('dokter/appointments')->where('isReport', true)->where('visits.total', 2)
                ->where('stats.total', 2)->where('stats.draft', 1)->where('stats.final', 1)->where('stats.patients', 1)
                ->where('visits.data.0.letterUrl', route('persuratan.surat.create', $doctor->kunjungan()->where('status', 'selesai')->firstOrFail())));
            $this->get(route('dokter.kunjungan'))->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)->where('stats.today', 3)->where('stats.completed', 1)
                ->where('visits.data.1.letterUrl', route('persuratan.surat.create', $doctor->kunjungan()->where('status', 'selesai')->firstOrFail())));
            $this->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)->where('stats.waiting', 1)->where('stats.inProgress', 1)->where('stats.completed', 1)
                ->where('todayVisits.total', 2)->where('todayVisits.data.0.status', 'pemeriksaan')
                ->where('visits.data.1.letterUrl', route('persuratan.surat.create', $doctor->kunjungan()->where('status', 'selesai')->firstOrFail())));
            $this->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
                ->where('stats.totalVisits', 3)->where('stats.visitsToday', 3)->where('doctorQueues.examinations', 1)
                ->where('recentVisits.total', 3)->has('recentVisits.data', 3)
                ->where('recentVisits.data.1.letterUrl', route('persuratan.surat.create', $doctor->kunjungan()->where('status', 'selesai')->firstOrFail())));
            $this->get(route('dokter.pasien'))->assertInertia(fn (Assert $page) => $page
                ->where('patients.data.0.letterUrl', route('persuratan.surat.create', $doctor->kunjungan()->where('status', 'selesai')->firstOrFail())));
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
        $this->get(route('dokter.tindakan'))->assertForbidden();
        $this->get(route('dokter.laporan', ['jenis' => 'tindakan']))->assertForbidden();
        $this->get(route('dokter.laporan.export', ['jenis' => 'obat']))->assertForbidden();
    }

    public function test_doctor_activity_reports_show_only_the_doctors_own_visits(): void
    {
        $user = User::factory()->create(['role' => 'dokter']);
        $doctor = Nakes::create(['kode' => 'DR-ACT', 'nama' => 'Dokter Aktif', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $other = Nakes::create(['kode' => 'DR-OTHER', 'nama' => 'Dokter Lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POL-ACT', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-ACT', 'nama' => 'Pasien Aktivitas']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-ACT', 'pasien_id' => $patient->id, 'dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'status' => 'selesai', 'signed_at' => now(), 'signed_by_user_id' => $user->id]);
        app(ClinicalNoteRecorder::class)->recordFinal($examination, ['anamnesis' => 'Pemeriksaan final'], $user->id);
        Kunjungan::create(['no_kunjungan' => 'KNJ-OTHER', 'pasien_id' => $patient->id, 'dokter_id' => $other->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        TindakanKunjungan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'nama_tindakan' => 'Perawatan luka', 'jumlah' => 1, 'execution_role' => 'dokter', 'execution_status' => 'completed']);
        TindakanKunjungan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $other->id, 'nama_tindakan' => 'Tindakan dokter lain', 'jumlah' => 1]);
        $prescription = Resep::create(['no_resep' => 'RSP-ACT', 'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'status' => 'menunggu']);
        $prescription->resepObat()->create(['nama_obat' => 'Paracetamol', 'jumlah' => 6, 'satuan' => 'tablet', 'aturan_pakai' => '3 kali sehari', 'is_resep_luar' => false]);

        $this->actingAs($user)->get('/dokter/laporan/kunjungan')->assertNotFound();
        $this->get(route('dokter.laporan', ['jenis' => 'tindakan']))
            ->assertInertia(fn (Assert $page) => $page->component('dokter/reports')->where('type', 'tindakan')->where('rows.total', 1)->where('rows.data.0.name', 'Perawatan luka')->where('rows.data.0.letterUrl', route('persuratan.surat.create', $visit))->has('exportUrl'));
        $this->get(route('dokter.laporan', ['jenis' => 'obat']))
            ->assertInertia(fn (Assert $page) => $page->where('type', 'obat')->where('rows.total', 1)->where('rows.data.0.name', 'Paracetamol')->has('exportUrl'));
        $this->get(route('dokter.tindakan'))
            ->assertInertia(fn (Assert $page) => $page->component('dokter/treatments')->where('stats.total', 1)->where('stats.visits', 1)->where('stats.completed', 1)->where('actions.data.0.name', 'Perawatan luka')->where('actions.data.0.letterUrl', route('persuratan.surat.create', $visit)));

        Excel::fake();
        $this->get(route('dokter.laporan.export', ['jenis' => 'tindakan']))->assertOk();
        Excel::assertDownloaded('laporan-tindakan-'.today()->format('Y-m-d').'.xlsx', function (DoctorActivityExport $export) use ($doctor): bool {
            $rows = $export->query()->get();
            $this->assertCount(1, $rows);
            $this->assertSame($doctor->id, $rows->first()->dokter_id);
            $this->assertSame('KNJ-ACT', $export->map($rows->first())[1]);

            return true;
        });
        $this->get(route('dokter.laporan.export', ['jenis' => 'obat']))->assertOk();
        Excel::assertDownloaded('laporan-obat-'.today()->format('Y-m-d').'.xlsx', function (DoctorActivityExport $export): bool {
            $this->assertCount(1, $export->query()->get());
            $this->assertSame('Paracetamol', $export->map($export->query()->first())[5]);

            return true;
        });
    }
}
