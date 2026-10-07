<?php

namespace Tests\Feature;

use App\Models\BiayaAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BiayaAdminInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_administration_fees_from_the_inertia_page(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-fee@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $fee = BiayaAdmin::create(['nama' => 'Biaya Surat', 'tarif' => 10000, 'keterangan' => 'Surat keterangan', 'is_active' => true]);

        $this->actingAs($admin)->get(route('master.biaya-admin.index', ['search' => 'Surat']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/biaya-admin/index')
                ->has('fees.data', 1)
                ->where('fees.data.0.name', 'Biaya Surat')
                ->where('fees.data.0.tariff', 10000)
            );

        $this->put(route('master.biaya-admin.update', $fee), [
            'nama' => 'Biaya Surat Medis',
            'tarif' => 15000,
            'keterangan' => 'Layanan surat',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('biaya_admin', ['id' => $fee->id, 'tarif' => 15000]);

        $this->post(route('master.biaya-admin.store'), [
            'nama' => 'Biaya Administrasi',
            'tarif' => 5000,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('biaya_admin', ['nama' => 'Biaya Administrasi', 'tarif' => 5000]);
    }
}
