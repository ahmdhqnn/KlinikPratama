<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsuransiInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_billing_master_routes_are_retired_and_cannot_create_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('master.asuransi.index'))->assertStatus(410);
        $this->post(route('master.asuransi.store'), [])->assertStatus(410);
        $this->assertDatabaseCount('asuransi', 0);
    }
}
