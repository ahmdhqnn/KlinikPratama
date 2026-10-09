<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_routes_cannot_create_bills_or_accept_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('pelayanan.kasir.index'))->assertStatus(410);
        $this->post('/pelayanan/kasir/999', ['bayar' => 100000, 'metode_bayar' => 'tunai'])->assertNotFound();
        $this->assertDatabaseCount('tagihan', 0);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }
}
