<?php

namespace Tests\Feature;

use App\Models\Kepesertaan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasienInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_membership_categories_are_rendered_without_breaking_patient_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $member = Kepesertaan::factory()->create(['kategori' => 'kontrak', 'jenis_lainnya' => null]);
        Pasien::create([
            'no_rm' => 'RM-LEGACY-001', 'nama' => $member->nama,
            'nik' => $member->nik, 'kepesertaan_id' => $member->id,
        ]);

        $this->actingAs($admin)->get(route('pelayanan.pasien.index', ['kategori' => 'lainnya']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/pasien/index')
                ->where('patients.total', 1)
                ->where('patients.data.0.membership', 'Lainnya · Staff')
            );

        $this->get(route('kepesertaan.show', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->component('kepesertaan/show')
                ->where('member.kategori', 'Lainnya')
                ->where('member.jenisLainnya', 'Staff')
            );
    }

    public function test_patient_list_filters_are_combined_and_crud_pages_use_inertia(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-patient@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $member = Kepesertaan::factory()->create(['nama' => 'Siti Sehat', 'nik' => '3201234567890001']);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Siti Sehat', 'kepesertaan_id' => $member->id, 'nik' => $member->nik]);
        $this->assertSame($member->id, $patient->kepesertaan->id);
        Pasien::create(['no_rm' => 'RM-000002', 'nama' => 'Siti Sehat Duplikat']);

        $this->actingAs($admin)
            ->get(route('pelayanan.pasien.index', ['search' => 'Siti Sehat', 'kategori' => 'asn']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/pasien/index')
                ->where('patients.total', 1)
                ->where('patients.data.0.name', 'Siti Sehat')
                ->where('patients.data.0.membership', 'ASN')
                ->where('filters.kategori', 'asn')
            );

        $this->get(route('pelayanan.pasien.create'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/form')
            ->missing('insuranceProviders')
        );

        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/show')
            ->where('patient.name', 'Siti Sehat')
            ->where('patient.membership', 'ASN')
            ->has('patient.visits', 0)
        );

        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertForbidden();

        $this->get(route('pelayanan.pasien.edit', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/form')
            ->where('patient.name', 'Siti Sehat')
        );

        $this->put(route('pelayanan.pasien.update', $patient), [
            'kepesertaan_id' => $member->id,
            'nama' => 'Siti Sehat Utama',
            'nik' => '3201234567890001',
        ])->assertRedirect(route('pelayanan.pasien.show', $patient));

        $this->assertDatabaseHas('pasien', ['id' => $patient->id, 'nama' => 'Siti Sehat']);

        $newMember = Kepesertaan::factory()->create([
            'nama' => 'Budi Santoso', 'nik' => '3201234567890002', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-02-03', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Anggrek', 'rt' => '004',
        ]);
        $this->post(route('pelayanan.pasien.store'), [
            'kepesertaan_id' => $newMember->id, 'nama' => 'Nama Palsu', 'nik' => '9999999999999999',
            'tanggal_lahir' => '2000-01-01', 'jenis_kelamin' => 'P', 'alamat' => 'Alamat Palsu',
            'telepon' => '081234567890',
        ])->assertRedirect();

        $this->assertDatabaseHas('pasien', [
            'nama' => 'Budi Santoso', 'nik' => '3201234567890002',
            'jenis_kelamin' => 'L', 'alamat' => 'Jl. Anggrek', 'rt' => '004', 'telepon' => '081234567890',
        ]);
        $createdPatient = Pasien::where('nik', '3201234567890002')->firstOrFail();
        $this->assertSame('1990-02-03', $createdPatient->tanggal_lahir?->toDateString());
    }
}
