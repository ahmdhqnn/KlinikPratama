<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DentalScheduleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dental_nurse_can_create_update_and_delete_schedules_in_assigned_clinic(): void
    {
        $this->freezeTime();
        [$nurse, $clinic, $doctor] = $this->dentalContext();
        $otherClinic = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        JadwalDokter::create($this->scheduleData($doctor, $otherClinic));

        $this->actingAs($nurse)->get(route('pendaftaran.jadwal-praktik', ['poliklinik_id' => $otherClinic->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)->where('canManageHolidays', false)->where('canSelectClinic', false)
                ->where('filters.poliklinikId', $clinic->id)->has('clinics', 1)->has('schedules.data', 0)
                ->has('doctors', 1));
        $this->post(route('pendaftaran.store-jadwal-praktik'), $this->scheduleData($doctor, $clinic))
            ->assertRedirect()->assertSessionHasNoErrors();
        $schedule = JadwalDokter::where('poliklinik_id', $clinic->id)->firstOrFail();
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $schedule->id, 'poliklinik_id' => $clinic->id, 'hari' => 'senin']);

        $this->put(route('pendaftaran.update-jadwal-praktik', $schedule), [...$this->scheduleData($doctor, $clinic), 'hari' => 'selasa'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $schedule->id, 'hari' => 'selasa']);
        $this->delete(route('pendaftaran.destroy-jadwal-praktik', $schedule))->assertRedirect();
        $this->assertDatabaseMissing('jadwal_dokter', ['id' => $schedule->id]);
        $this->assertDatabaseCount('jadwal_dokter', 1);
    }

    public function test_dental_nurse_cannot_create_move_update_or_delete_another_clinics_schedule(): void
    {
        $this->freezeTime();
        [$nurse, $clinic, $doctor] = $this->dentalContext();
        $otherClinic = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli Gigi Lain', 'jenis' => 'gigi', 'is_active' => true]);
        $foreignSchedule = JadwalDokter::create($this->scheduleData($doctor, $otherClinic));
        $ownSchedule = JadwalDokter::create($this->scheduleData($doctor, $clinic));

        $this->actingAs($nurse)->post(route('pendaftaran.store-jadwal-praktik'), $this->scheduleData($doctor, $otherClinic))->assertNotFound();
        $this->put(route('pendaftaran.update-jadwal-praktik', $foreignSchedule), $this->scheduleData($doctor, $clinic))->assertNotFound();
        $this->put(route('pendaftaran.update-jadwal-praktik', $ownSchedule), $this->scheduleData($doctor, $otherClinic))->assertNotFound();
        $this->delete(route('pendaftaran.destroy-jadwal-praktik', $foreignSchedule))->assertNotFound();
        $this->post(route('pendaftaran.jadwal-praktik.asn'), ['dokter_id' => $doctor->id, 'poliklinik_id' => $otherClinic->id])->assertNotFound();

        $this->assertDatabaseCount('jadwal_dokter', 2);
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $ownSchedule->id, 'poliklinik_id' => $clinic->id]);
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $foreignSchedule->id, 'poliklinik_id' => $otherClinic->id]);
    }

    public function test_dental_nurse_manages_local_exceptions_and_asn_schedules_without_changing_global_holidays(): void
    {
        $this->freezeTime();
        [$nurse, $clinic, $doctor] = $this->dentalContext();
        $data = ['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id];
        $date = today()->addWeek()->toDateString();
        $holidayId = DB::table('hari_libur_praktik')->insertGetId(['tanggal' => $date, 'nama' => 'Libur instansi', 'jenis' => 'instansi', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($nurse)->post(route('pendaftaran.jadwal-praktik.asn'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('jadwal_dokter', 5);
        $this->post(route('pendaftaran.jadwal-praktik.pengecualian.store'), [...$data, 'tanggal' => $date, 'is_active' => false, 'alasan' => 'Dokter berhalangan'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jadwal_khusus_dokter', [...$data, 'tanggal' => $date, 'is_active' => false, 'jam_mulai' => null, 'jam_selesai' => null]);
        $exceptionId = DB::table('jadwal_khusus_dokter')->value('id');
        $this->delete(route('pendaftaran.jadwal-praktik.pengecualian.destroy', $exceptionId))->assertRedirect();
        $this->assertDatabaseMissing('jadwal_khusus_dokter', ['id' => $exceptionId]);
        $this->post(route('pendaftaran.jadwal-praktik.libur.store'), ['tanggal' => $date, 'nama' => 'Diubah', 'jenis' => 'instansi'])->assertForbidden();
        $this->delete(route('pendaftaran.jadwal-praktik.libur.destroy', $holidayId))->assertForbidden();
        $this->assertDatabaseHas('hari_libur_praktik', ['id' => $holidayId, 'nama' => 'Libur instansi']);
    }

    public function test_dental_nurse_cannot_change_exceptions_outside_assigned_clinic(): void
    {
        $this->freezeTime();
        [$nurse, $clinic, $doctor] = $this->dentalContext();
        $otherClinic = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $data = ['dokter_id' => $doctor->id, 'poliklinik_id' => $otherClinic->id, 'tanggal' => today()->addWeek()->toDateString(), 'is_active' => false, 'alasan' => 'Tutup'];
        $exceptionId = DB::table('jadwal_khusus_dokter')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($nurse)->post(route('pendaftaran.jadwal-praktik.pengecualian.store'), $data)->assertNotFound();
        $this->delete(route('pendaftaran.jadwal-praktik.pengecualian.destroy', $exceptionId))->assertNotFound();
        $this->assertDatabaseHas('jadwal_khusus_dokter', ['id' => $exceptionId, 'poliklinik_id' => $otherClinic->id]);
    }

    #[TestWith(['perawat'])]
    public function test_general_nurse_can_view_schedules_but_cannot_manage_them(string $role): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'STAFF', 'nama' => 'Petugas Umum', 'kategori' => 'medis', 'jabatan' => $role, 'poliklinik_id' => $clinic->id, 'user_id' => $user->id, 'is_active' => true]);
        $doctor = $role === 'dokter' ? $staff : Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter Umum', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $data = $this->scheduleData($doctor, $clinic);
        $schedule = JadwalDokter::create($data);

        $this->actingAs($user)->get(route('pendaftaran.jadwal-praktik'))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)->where('canManageHolidays', false)->has('schedules.data', 1));
        $this->post(route('pendaftaran.store-jadwal-praktik'), $data)->assertForbidden();
        $this->put(route('pendaftaran.update-jadwal-praktik', $schedule), [...$data, 'hari' => 'selasa'])->assertForbidden();
        $this->delete(route('pendaftaran.destroy-jadwal-praktik', $schedule))->assertForbidden();
        $this->post(route('pendaftaran.jadwal-praktik.asn'), $data)->assertForbidden();
        $this->post(route('pendaftaran.jadwal-praktik.pengecualian.store'))->assertForbidden();
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $schedule->id, 'hari' => 'senin']);
    }

    public function test_doctor_can_view_filter_and_manage_all_practice_schedules(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-1', 'nama' => 'Dokter Sendiri', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $other = Nakes::create(['kode' => 'DR-2', 'nama' => 'Dokter Lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        JadwalDokter::create($this->scheduleData($doctor, $clinic));
        JadwalDokter::create($this->scheduleData($other, $clinic));

        $this->actingAs($user)->get(route('pendaftaran.jadwal-praktik', ['dokter_id' => $other->id]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.dokterId', $other->id)->has('doctors', 2)->has('schedules.data', 1)
            ->where('canManage', true)->where('canManageHolidays', true)
            ->where('schedules.data.0.doctorId', $other->id));
        $this->get(route('pendaftaran.jadwal-praktik'))->assertInertia(fn (Assert $page) => $page->has('schedules.data', 2));
        $data = [...$this->scheduleData($other, $clinic), 'hari' => 'selasa'];
        $this->post(route('pendaftaran.store-jadwal-praktik'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $schedule = JadwalDokter::where('hari', 'selasa')->firstOrFail();
        $this->put(route('pendaftaran.update-jadwal-praktik', $schedule), [...$data, 'hari' => 'rabu'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $schedule->id, 'hari' => 'rabu']);
        $this->delete(route('pendaftaran.destroy-jadwal-praktik', $schedule))->assertRedirect();
        $this->assertDatabaseMissing('jadwal_dokter', ['id' => $schedule->id]);
        $holiday = ['tanggal' => today()->addWeek()->toDateString(), 'nama' => 'Libur instansi', 'jenis' => 'instansi'];
        $this->post(route('pendaftaran.jadwal-praktik.libur.store'), $holiday)->assertRedirect()->assertSessionHasNoErrors();
        $holidayId = DB::table('hari_libur_praktik')->value('id');
        $this->delete(route('pendaftaran.jadwal-praktik.libur.destroy', $holidayId))->assertRedirect();
        $this->assertDatabaseMissing('hari_libur_praktik', ['id' => $holidayId]);
        $this->get(route('pendaftaran.dashboard'))->assertForbidden();
    }

    /** @return array{0: User, 1: Poliklinik, 2: Nakes} */
    private function dentalContext(): array
    {
        $user = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        Nakes::create(['kode' => 'NURSE', 'nama' => 'Terapis Gigi', 'kategori' => 'medis', 'jabatan' => 'terapis_gigi', 'user_id' => $user->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter Gigi', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'is_active' => true]);

        return [$user, $clinic, $doctor];
    }

    /** @return array<string, int|string> */
    private function scheduleData(Nakes $doctor, Poliklinik $clinic): array
    {
        return ['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'berlaku_mulai' => today()->addWeek()->toDateString()];
    }
}
