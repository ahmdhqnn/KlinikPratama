<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationDashboardMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_monitors_all_clinics_while_nurse_keeps_the_assigned_clinic_queue(): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $nurse = User::factory()->create(['role' => 'perawat']);
        $clinic = Poliklinik::create(['kode' => 'MON-UM', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $other = Poliklinik::create(['kode' => 'MON-GI', 'nama' => 'Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        Nakes::create(['kode' => 'MON-N', 'nama' => 'Perawat', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'MON-D', 'nama' => 'Dokter', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        foreach ([$clinic, $other] as $scheduledClinic) {
            JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $scheduledClinic->id, 'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'is_active' => true]);
        }
        $patient = Pasien::create(['no_rm' => 'RM-MON', 'nama' => 'Pasien']);
        foreach ([['selesai', today(), $clinic->id], ['batal', today(), $clinic->id], ['menunggu', today()->subDay(), $clinic->id], ['menunggu', today(), $other->id]] as $index => [$status, $date, $clinicId]) {
            Kunjungan::create(['no_kunjungan' => 'MON-'.$index, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinicId, 'tanggal' => $date, 'status' => $status]);
        }
        foreach (range(1, 3) as $index) {
            Kunjungan::create(['no_kunjungan' => 'LATEST-'.$index, 'pasien_id' => $patient->id,
                'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        }
        $registration = User::factory()->create(['role' => 'pendaftaran']);
        $this->actingAs($registration)->get(route('pendaftaran.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/dashboard')->has('schedules', 2)->where('visits.total', 6)->has('visits.data', 3)->where('visits.lastPage', 2)
            ->where('visits.data.0.number', 'LATEST-3')->where('visits.data.1.number', 'LATEST-2')->where('visits.data.2.number', 'LATEST-1')
            ->where('visits.data.0.date', '26/10/2026')
            ->missing('visits.data.0.triage')->missing('visits.data.0.priority'));
        $this->actingAs($nurse)->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('perawat/dashboard')->has('schedules', 1)->where('schedules.0.clinic', 'Umum')->where('visits.total', 0));
    }
}
