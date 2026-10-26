<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DoctorExaminationStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_does_not_start_examination_and_explicit_start_is_idempotent(): void
    {
        [$doctor, $staff, $visit] = $this->visitContext();
        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))
            ->assertInertia(fn (Assert $page) => $page->where('visit.examination', null)->where('visit.examinationStarted', false));
        $this->assertDatabaseCount('pemeriksaan', 0);
        $this->get(route('pelayanan.pemeriksaan.index'))
            ->assertInertia(fn (Assert $page) => $page->where('visits.data.0.examinationStarted', false));

        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect(route('pelayanan.pemeriksaan.show', $visit))->assertSessionHasNoErrors();
        $examination = $visit->fresh()->pemeriksaan;
        $this->assertSame($staff->id, $examination->dokter_id);
        $this->assertSame('draft', $examination->status);
        $this->assertTrue($examination->started_at->equalTo(now()));
        $this->assertNull($examination->anamnesis);
        $this->assertSame('pemeriksaan', $visit->fresh()->status);
        $startedAt = $examination->started_at->toDateTimeString();
        $examination->update(['anamnesis' => 'Keluhan pasien yang sudah dicatat']);
        $this->travel(5)->minutes();

        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pemeriksaan', 1);
        $this->assertSame($startedAt, $examination->fresh()->started_at->toDateTimeString());
        $this->assertSame('Keluhan pasien yang sudah dicatat', $examination->fresh()->anamnesis);
        $this->assertSame(1, DB::table('clinical_audit_events')->where('action', 'clinical_examination.started')->where('kunjungan_id', $visit->id)->count());
        $this->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page->where('visits.data.0.examinationStarted', true));
        $this->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('recentVisits.data.0.examinationStarted', true)->where('recentVisits.data.0.actionLabel', 'Lanjutkan pemeriksaan'));
        $this->get(route('dokter.kunjungan'))->assertInertia(fn (Assert $page) => $page->where('visits.data.0.examinationStarted', true));
    }

    public function test_start_claims_unassigned_visit_only_for_a_scheduled_doctor(): void
    {
        [$doctor, $staff, $visit] = $this->visitContext();
        $visit->update(['dokter_id' => null]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        $this->assertNull($visit->fresh()->dokter_id);
        $this->assertDatabaseCount('pemeriksaan', 0);
        JadwalDokter::create([
            'dokter_id' => $staff->id, 'poliklinik_id' => $visit->poliklinik_id,
            'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true,
        ]);

        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $visit->fresh()->dokter_id);
        $this->assertDatabaseHas('pemeriksaan', ['kunjungan_id' => $visit->id, 'dokter_id' => $staff->id, 'status' => 'draft']);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'clinical_examination.visit_claim', 'actor_id' => $doctor->id, 'kunjungan_id' => $visit->id]);
    }

    public function test_another_doctor_cannot_take_over_an_assigned_visit(): void
    {
        [, $staff, $visit] = $this->visitContext();
        $other = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create(['kode' => 'OTHER', 'nama' => 'Dokter Lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $other->id, 'is_active' => true]);
        $this->actingAs($other)->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        $this->assertSame($staff->id, $visit->fresh()->dokter_id);
        $this->assertDatabaseCount('pemeriksaan', 0);
    }

    #[TestWith(['menunggu'])]
    #[TestWith(['screening'])]
    #[TestWith(['farmasi'])]
    #[TestWith(['selesai'])]
    #[TestWith(['batal'])]
    public function test_start_rejects_visits_outside_the_examination_stage(string $status): void
    {
        [$doctor, , $visit] = $this->visitContext();
        $visit->update(['status' => $status]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.start', $visit))->assertUnprocessable();
        $this->assertDatabaseCount('pemeriksaan', 0);
        $this->assertSame($status, $visit->fresh()->status);
    }

    public function test_future_visit_cannot_start_before_its_service_date(): void
    {
        [$doctor, , $visit] = $this->visitContext();
        $visit->update(['tanggal' => today()->addDay()]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.start', $visit))->assertUnprocessable();
        $this->assertDatabaseCount('pemeriksaan', 0);
    }

    public function test_final_examination_cannot_be_reopened_by_starting_again(): void
    {
        [$doctor, $staff, $visit] = $this->visitContext();
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $staff->id, 'status' => 'selesai', 'signed_at' => now(), 'anamnesis' => 'Catatan final']);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.start', $visit))->assertUnprocessable();
        $this->assertSame('selesai', $examination->fresh()->status);
        $this->assertSame('Catatan final', $examination->fresh()->anamnesis);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'clinical_examination.started']);
    }

    #[TestWith(['admin'])]
    #[TestWith(['perawat'])]
    #[TestWith(['pendaftaran'])]
    public function test_other_roles_observe_progress_without_starting_the_doctors_examination(string $role): void
    {
        [$doctor, , $visit] = $this->visitContext();
        $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
        if ($role === 'perawat') {
            Nakes::create(['kode' => 'NURSE', 'nama' => 'Perawat', 'kategori' => 'medis', 'jabatan' => 'perawat', 'poliklinik_id' => $visit->poliklinik_id, 'user_id' => $actor->id, 'is_active' => true]);
        }
        $response = $this->actingAs($actor)->post(route('pelayanan.pemeriksaan.start', $visit));
        if ($role === 'pendaftaran') {
            $response->assertRedirect(route('pendaftaran.dashboard'));
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseCount('pemeriksaan', 0);
        $route = $role === 'pendaftaran' ? 'pendaftaran.kunjungan.show' : 'pelayanan.kunjungan.show';
        $this->get(route($route, $visit))->assertInertia(fn (Assert $page) => $page->where('visit.examinationStarted', false));
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($actor)->get(route($route, $visit))->assertInertia(fn (Assert $page) => $page->where('visit.examinationStarted', true));
    }

    /** @return array{User, Nakes, Kunjungan} */
    private function visitContext(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(9, 0));
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter Pemeriksa', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'user_id' => $doctor->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-START', 'nama' => 'Pasien Pemeriksaan']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-START', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $staff->id, 'tanggal' => today(), 'status' => 'pemeriksaan', 'jenis_pasien' => 'lama']);

        return [$doctor, $staff, $visit];
    }
}
