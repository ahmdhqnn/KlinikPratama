<?php

namespace Tests\Feature;

use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasienInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_list_filters_are_combined_and_crud_pages_use_inertia(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-patient@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $insurance = Asuransi::create(['kode' => 'BPJS-X', 'nama' => 'BPJS Kesehatan', 'jenis' => 'bpjs', 'is_active' => true]);
        $otherInsurance = Asuransi::create(['kode' => 'ASR-X', 'nama' => 'Asuransi Lain', 'jenis' => 'perusahaan', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Siti Sehat', 'asuransi_id' => $insurance->id]);
        Pasien::create(['no_rm' => 'RM-000002', 'nama' => 'Siti Sehat Duplikat', 'asuransi_id' => $otherInsurance->id]);

        $this->actingAs($admin)
            ->get(route('pelayanan.pasien.index', ['search' => 'Siti Sehat', 'asuransi_id' => $insurance->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/pasien/index')
                ->where('patients.total', 1)
                ->where('patients.data.0.name', 'Siti Sehat')
                ->where('filters.insuranceId', (string) $insurance->id)
            );

        $this->get(route('pelayanan.pasien.create'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/form')
            ->has('insuranceProviders', 2)
        );

        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/show')
            ->where('patient.name', 'Siti Sehat')
            ->has('patient.visits', 0)
        );

        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/rekam-medis')
            ->where('patient.name', 'Siti Sehat')
            ->has('visits', 0)
        );

        $this->get(route('pelayanan.pasien.edit', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/form')
            ->where('patient.name', 'Siti Sehat')
        );

        $this->put(route('pelayanan.pasien.update', $patient), [
            'nama' => 'Siti Sehat Utama',
            'nik' => '3201234567890001',
            'asuransi_id' => $insurance->id,
        ])->assertRedirect(route('pelayanan.pasien.show', $patient));

        $this->assertDatabaseHas('pasien', ['id' => $patient->id, 'nama' => 'Siti Sehat Utama']);
    }
}
