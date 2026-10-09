<?php

namespace Tests\Feature;

use App\Models\Nakes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorAccountAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_assign_a_doctor_account_to_an_existing_doctor_profile(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $doctor = Nakes::create([
            'kode' => 'DR-001',
            'nama' => 'dr. Satu',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Akun Dokter Satu',
            'email' => 'dokter-satu@klinik.test',
            'password' => 'password',
            'role' => 'dokter',
            'is_active' => '1',
            'nakes_id' => $doctor->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'dokter-satu@klinik.test',
            'role' => 'dokter',
        ]);
        $this->assertDatabaseHas('nakes', [
            'id' => $doctor->id,
            'user_id' => User::where('email', 'dokter-satu@klinik.test')->value('id'),
        ]);
    }

    public function test_doctor_account_cannot_claim_a_profile_already_linked_to_another_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DR-002',
            'nama' => 'dr. Dua',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Akun Dokter Tiga',
            'email' => 'dokter-tiga@klinik.test',
            'password' => 'password',
            'role' => 'dokter',
            'is_active' => '1',
            'nakes_id' => $doctor->id,
        ])->assertSessionHasErrors('nakes_id');
    }

    public function test_changing_doctor_account_to_admin_unlinks_professional_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DR-ROLE',
            'nama' => 'Dokter Beralih Peran',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('users.update', $doctorUser), [
            'name' => $doctorUser->name,
            'email' => $doctorUser->email,
            'role' => 'admin',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('admin', $doctorUser->fresh()->role);
        $this->assertNull($doctor->fresh()->user_id);
    }
}
