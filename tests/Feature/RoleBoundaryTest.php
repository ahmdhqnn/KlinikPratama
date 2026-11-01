<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

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
