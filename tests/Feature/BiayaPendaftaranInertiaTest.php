<?php

namespace Tests\Feature;

use App\Models\BiayaPendaftaran;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BiayaPendaftaranInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_registration_fees_for_clinics_and_doctors(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-reg-fee@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-001', 'nama' => 'Dokter Uji', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $fee = BiayaPendaftaran::create([
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'jenis_pasien' => 'baru',
            'tarif' => 20000,
        ]);

        $this->actingAs($admin)->get(route('master.biaya-pendaftaran.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/biaya-pendaftaran/index')
                ->has('fees.data', 1)
                ->where('fees.data.0.clinic', 'Poli Umum')
                ->where('fees.data.0.doctor', 'Dokter Uji')
            );

        $this->put(route('master.biaya-pendaftaran.update', $fee), [
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'jenis_pasien' => 'lama',
            'tarif' => 15000,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('biaya_pendaftaran', ['id' => $fee->id, 'jenis_pasien' => 'lama', 'tarif' => 15000]);

        $this->post(route('master.biaya-pendaftaran.store'), [
            'poliklinik_id' => '',
            'dokter_id' => '',
            'jenis_pasien' => 'baru',
            'tarif' => 25000,
        ])->assertSessionHasNoErrors();
    }
}
