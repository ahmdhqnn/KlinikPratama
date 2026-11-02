<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_pharmacy_services_and_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach (['pelayanan.farmasi.index', 'farmasi.pemberian-obat.index', 'farmasi.pemberian-obat.create', 'farmasi.resep-luar.index', 'farmasi.laporan', 'farmasi.laporan.pemberian-langsung', 'farmasi.laporan.resep-luar'] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
    }

    public function test_admin_registration_entry_points_are_available_but_inactive_admin_is_denied(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        foreach (['pendaftaran.pendaftaran-baru', 'pendaftaran.kunjungan-per-poli', 'pendaftaran.jadwal-praktik'] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
        $this->get(route('pendaftaran.pendaftaran-lama'))->assertRedirect(route('pendaftaran.pendaftaran-baru', ['tab' => 'registered']));
        $admin->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('pendaftaran.pendaftaran-baru'))->assertForbidden();
        $this->actingAs($admin)->get(route('farmasi.pemberian-obat.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('farmasi.pemberian-obat.store'), [])->assertForbidden();
    }

    public function test_pharmacy_inventory_is_authorized_and_retired_cashier_role_is_denied(): void
    {
        $pharmacist = User::factory()->create([
            'role' => 'farmasi',
            'is_active' => true,
        ]);
        $cashier = User::factory()->create([
            'role' => 'kasir',
            'is_active' => true,
        ]);

        $this->actingAs($pharmacist)
            ->get(route('dashboard'))
            ->assertRedirect(route('farmasi.dashboard'));
        $this->actingAs($pharmacist)
            ->get(route('master.nakes.index'))
            ->assertForbidden();

        $this->actingAs($pharmacist)->get(route('stok.persediaan.index'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.pasien'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.resep-luar.index'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.laporan.resep-luar'))->assertOk();
        $this->actingAs($pharmacist)->get(route('pelayanan.pasien.index'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.pemberian-obat.index'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.pemberian-obat.create'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.laporan'))->assertOk();
        $this->actingAs($pharmacist)->get(route('farmasi.laporan.pemberian-langsung'))->assertOk();
        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertForbidden();
        $this->actingAs($cashier)
            ->get(route('pelayanan.farmasi.index'))
            ->assertForbidden();
        $this->actingAs($cashier)->get(route('farmasi.pemberian-obat.create'))->assertForbidden();
        $this->actingAs($cashier)->get(route('farmasi.resep-luar.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('farmasi.laporan.resep-luar'))->assertForbidden();
        $this->actingAs($cashier)->get(route('pelayanan.pasien.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('farmasi.laporan.pemberian-langsung'))->assertForbidden();
    }
}
