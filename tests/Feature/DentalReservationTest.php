<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DentalReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dental_patient_can_register_and_receive_a_non_reservation_ticket_today(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);

        $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-20',
        ])->assertSessionHasNoErrors();

        $visit = Kunjungan::sole();
        $this->assertSame('2026-10-20', $visit->tanggal->toDateString());
        $this->assertSame('menunggu', $visit->status);
        $this->get(route('pendaftaran.cetak-antrian', $visit))->assertInertia(fn (Assert $page) => $page->where('ticket.isReservation', false));
    }

    public function test_dental_patient_still_needs_a_doctor_scheduled_today(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();

        $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-20',
        ])->assertSessionHasErrors('dokter_id');

        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_dental_reservation_uses_the_selected_future_date_and_prevents_duplicates(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();
        $data = ['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-21'];

        $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), $data)->assertSessionHasNoErrors();

        $visit = Kunjungan::sole();
        $this->assertSame('2026-10-21', $visit->tanggal->toDateString());
        $this->assertSame('menunggu', $visit->status);
        $this->assertSame($doctor->id, $visit->dokter_id);
        $this->get(route('pendaftaran.cetak-antrian', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('ticket.isReservation', true)->where('ticket.date', 'Rabu, 21 Oktober 2026'));
        $this->get(route('pendaftaran.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 0)->where('followUp.upcoming', 1)->where('followUp.overdue', 0));

        $this->post(route('pendaftaran.store-pasien-lama'), $data)->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('kunjungan', 1);
    }

    public function test_future_reservation_cannot_be_screened_early(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $visit = Kunjungan::create(['no_kunjungan' => 'RES-FUTURE', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-21', 'status' => 'menunggu']);

        $this->actingAs($admin)->post(route('pelayanan.screening.store', $visit), [])->assertUnprocessable();

        $this->assertDatabaseCount('screening', 0);
        $this->assertSame('menunggu', $visit->fresh()->status);
    }

    public function test_general_clinic_still_accepts_today(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData('umum');
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);

        $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-20', Kunjungan::sole()->tanggal->toDateString());
    }

    public function test_pruning_keeps_current_and_open_ended_schedules_and_patient_history(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();
        $expired = JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'senin', 'berlaku_mulai' => '2026-09-01', 'berlaku_sampai' => '2026-10-19', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        $current = JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'selasa', 'berlaku_mulai' => '2026-09-01', 'berlaku_sampai' => '2026-10-20', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        $history = Kunjungan::create(['no_kunjungan' => 'RES-HISTORY', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-19', 'status' => 'selesai']);
        DB::table('jadwal_khusus_dokter')->insert(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'tanggal' => '2026-10-19', 'is_active' => false, 'alasan' => 'Cuti']);

        $this->artisan('clinic:prune-expired-schedules', ['--dry-run' => true])->assertSuccessful();
        $this->assertModelExists($expired);
        $this->artisan('clinic:prune-expired-schedules')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($current);
        $this->assertModelExists($history);
        $this->assertDatabaseCount('jadwal_dokter', 2);
        $this->assertDatabaseCount('jadwal_khusus_dokter', 0);
    }

    public function test_registration_uses_the_wib_date_after_local_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 17:30:00', 'UTC'));
        [$user, $clinic, $doctor, $patient] = $this->registrationData('umum');

        $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('2026-10-21', Kunjungan::sole()->tanggal->toDateString());
        $this->get(route('pendaftaran.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 1)->where('followUp.upcoming', 0));
    }

    public function test_dental_reservation_can_be_moved_forward_to_today_when_doctor_is_scheduled(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        $visit = Kunjungan::create(['no_kunjungan' => 'RES-MOVE', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-21', 'status' => 'menunggu']);

        $this->actingAs($user)->put(route('pendaftaran.update-kunjungan', $visit), [
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-20',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('2026-10-20', $visit->fresh()->tanggal->toDateString());
    }

    public function test_existing_dental_reservation_can_be_edited_on_its_service_date(): void
    {
        $this->travelTo('2026-10-21 09:00:00');
        [$user, $clinic, $doctor, $patient] = $this->registrationData();
        $visit = Kunjungan::create(['no_kunjungan' => 'RES-EDIT', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-21', 'status' => 'menunggu']);

        $this->actingAs($user)->get(route('pendaftaran.edit-kunjungan', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('today', '2026-10-21')->where('clinics.0.type', 'gigi')
            ->where('doctorsUrl', route('pendaftaran.dokter.terjadwal')));
        $this->put(route('pendaftaran.update-kunjungan', $visit), [
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => '2026-10-21', 'catatan' => 'Pasien mengonfirmasi kehadiran.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Pasien mengonfirmasi kehadiran.', $visit->fresh()->catatan);
        $this->assertSame('2026-10-21', $visit->fresh()->tanggal->toDateString());
    }

    /** @return array{User, Poliklinik, Nakes, Pasien} */
    private function registrationData(string $type = 'gigi'): array
    {
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'RES', 'nama' => 'Poli '.ucfirst($type), 'jenis' => $type, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-RES', 'nama' => 'Dokter Reservasi', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $member = Kepesertaan::factory()->create(['verified_by' => $user->id]);
        $patient = Pasien::create(['no_rm' => 'RM-RES', 'nama' => $member->nama, 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'rabu', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);

        return [$user, $clinic, $doctor, $patient];
    }
}
