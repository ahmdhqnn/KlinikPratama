<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_and_cashier_roles_only_reach_their_work_queue(): void
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
            ->assertRedirect(route('pelayanan.farmasi.index'));
        $this->actingAs($pharmacist)
            ->get(route('master.nakes.index'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertRedirect(route('pelayanan.kasir.index'));
        $this->actingAs($cashier)
            ->get(route('pelayanan.farmasi.index'))
            ->assertForbidden();
    }
}
