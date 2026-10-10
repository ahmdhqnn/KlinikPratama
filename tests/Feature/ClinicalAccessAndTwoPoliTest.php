<?php

namespace Tests\Feature;

use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClinicalAccessAndTwoPoliTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonclinical_roles_cannot_open_clinical_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SEC-1', 'nama' => 'Pasien Rahasia', 'riwayat_alergi' => 'Penisilin']);

        $this->actingAs($admin)->get(route('pelayanan.pasien.rekam-medis', $patient))->assertForbidden();
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'medical_record.view', 'patient_id' => $patient->id]);
        $this->get(route('pelayanan.pemeriksaan.index'))->assertForbidden();
        $this->get(route('pelayanan.screening.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->missing('recentVisits')
        );
        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page
            ->where('patient.allergies', null)
            ->where('permissions.viewRme', false)
        );

        $this->actingAs($registration)->get(route('pelayanan.pasien.rekam-medis', $patient))->assertForbidden();
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'medical_record.view', 'patient_id' => $patient->id]);
        $this->get(route('pelayanan.pasien.export'))->assertRedirect(route('pendaftaran.dashboard'));
    }

    public function test_doctor_sees_only_assigned_visits_and_cannot_change_demographics(): void
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'D-SEC-1', 'nama' => 'Dokter Umum', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true]);
        $otherDoctor = Nakes::create(['kode' => 'D-SEC-2', 'nama' => 'Dokter Gigi', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'PU', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SEC-2', 'nama' => 'Pasien Bersama']);
        $otherPatient = Pasien::create(['no_rm' => 'RM-SEC-3', 'nama' => 'Pasien Lain']);
        Kunjungan::create(['no_kunjungan' => 'KNJ-SEC-1', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Kunjungan::create(['no_kunjungan' => 'KNJ-SEC-2', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $otherDoctor->id, 'tanggal' => today(), 'status' => 'selesai']);
        Kunjungan::create(['no_kunjungan' => 'KNJ-SEC-3', 'pasien_id' => $otherPatient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $otherDoctor->id, 'tanggal' => today(), 'status' => 'selesai']);

        $this->actingAs($doctorUser)->get(route('pelayanan.pasien.index'))->assertInertia(fn (Assert $page) => $page
            ->where('patients.total', 1)
            ->where('patients.data.0.name', 'Pasien Bersama')
        );
        $previousPage = route('pelayanan.pasien.index');
        $this->get(route('pelayanan.pasien.rekam-medis', $patient), ['HTTP_REFERER' => $previousPage])->assertInertia(fn (Assert $page) => $page
            ->has('visits', 1)
            ->where('visits.0.number', 'KNJ-SEC-1')
            ->where('backUrl', $previousPage)
        );
        $this->assertDatabaseHas('clinical_audit_events', [
            'actor_id' => $doctorUser->id,
            'patient_id' => $patient->id,
            'action' => 'medical_record.view',
        ]);
        $this->get(route('pelayanan.pasien.rekam-medis', $otherPatient))->assertForbidden();
        $this->get(route('pelayanan.pasien.edit', $patient))->assertForbidden();
    }

    public function test_registration_accepts_only_active_general_and_dental_clinics(): void
    {
        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        Poliklinik::create(['kode' => 'PU', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $dental = Poliklinik::create(['kode' => 'PG', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $other = Poliklinik::create(['kode' => 'KIA', 'nama' => 'Poli KIA', 'jenis' => 'kia', 'is_active' => true]);
        $inactive = Poliklinik::create(['kode' => 'PG-LAMA', 'nama' => 'Poli Gigi Lama', 'jenis' => 'gigi', 'is_active' => false]);
        $patient = Pasien::create(['no_rm' => 'RM-POLI-1', 'nama' => 'Pasien Uji']);

        $member = Kepesertaan::factory()->create(['nama' => $patient->nama]);
        $patient->update(['kepesertaan_id' => $member->id, 'nik' => $member->nik]);
        $this->actingAs($registration)->get(route('pendaftaran.pendaftaran-lama'))->assertInertia(fn (Assert $page) => $page
            ->has('clinics', 2)
            ->where('clinics.0.name', 'Poli Gigi')
            ->where('clinics.1.name', 'Poli Umum')
        );

        foreach ([$other, $inactive] as $disallowedClinic) {
            $this->post(route('pendaftaran.store-pasien-lama'), [
                'pasien_id' => $patient->id,
                'poliklinik_id' => $disallowedClinic->id,
                'jenis_bayar' => 'umum',
            ])->assertSessionHasErrors('poliklinik_id');
        }

        $this->assertDatabaseCount('kunjungan', 0);

        $this->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $patient->id,
            'poliklinik_id' => $dental->id,
            'jenis_bayar' => 'umum',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kunjungan', ['pasien_id' => $patient->id, 'poliklinik_id' => $dental->id]);
    }

    public function test_general_visit_endpoint_rejects_other_clinic(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $other = Poliklinik::create(['kode' => 'KIA', 'nama' => 'Poli KIA', 'jenis' => 'kia', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-POLI-2', 'nama' => 'Pasien Uji']);

        $this->actingAs($admin)->post(route('pelayanan.kunjungan.store'), [
            'pasien_id' => $patient->id,
            'poliklinik_id' => $other->id,
            'tanggal' => today()->toDateString(),
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ])->assertSessionHasErrors('poliklinik_id');

        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_legacy_clinic_visit_remains_visible_after_registration_is_restricted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $legacyClinic = Poliklinik::create(['kode' => 'KIA', 'nama' => 'Poli KIA', 'jenis' => 'kia', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-LEGACY-1', 'nama' => 'Pasien Historis']);
        Kunjungan::create([
            'no_kunjungan' => 'KNJ-LEGACY-1',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $legacyClinic->id,
            'tanggal' => today(),
            'status' => 'selesai',
        ]);

        $this->actingAs($admin)->get(route('pelayanan.kunjungan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.total', 1)
            ->where('visits.data.0.clinic', 'Poli KIA')
        );

        $this->get(route('pelayanan.kunjungan.create'))->assertInertia(fn (Assert $page) => $page
            ->has('clinics', 0)
        );
    }

    public function test_demo_seeder_refuses_production_environment(): void
    {
        app()->detectEnvironment(static fn (): string => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DatabaseSeeder berisi akun dan data contoh');

        (new DatabaseSeeder)->run();
    }

    public function test_demo_seeder_marks_unavailable_services_inactive(): void
    {
        (new DatabaseSeeder)->run();

        $this->assertDatabaseHas('poliklinik', ['kode' => 'PU', 'is_active' => true]);
        $this->assertDatabaseHas('poliklinik', ['kode' => 'PG', 'is_active' => true]);
        $this->assertDatabaseHas('poliklinik', ['kode' => 'KIA', 'is_active' => false]);
        $this->assertDatabaseHas('poliklinik', ['kode' => 'LAB', 'is_active' => false]);
        $this->assertDatabaseHas('depo_obat', ['kode' => 'UGD', 'is_active' => false]);
        $this->assertDatabaseHas('farmasi', ['status' => 'selesai']);
        $this->assertDatabaseHas('obat', ['kode' => 'OBT001', 'stok' => 90]);
    }
}
