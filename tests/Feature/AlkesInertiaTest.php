<?php

namespace Tests\Feature;

use App\Models\Alkes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlkesInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_medical_supplies_from_the_inertia_page(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-alkes@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $item = Alkes::create([
            'kode' => 'ALK-001',
            'nama' => 'Sarung Tangan',
            'satuan' => 'box',
            'stok' => 20,
            'stok_minimum' => 5,
            'harga_beli' => 25000,
            'harga_jual' => 30000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.alkes.index', ['search' => 'ALK-001']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/alkes/index')
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Sarung Tangan')
                ->where('items.data.0.stock', 20)
            );

        $this->put(route('master.alkes.update', $item), [
            'kode' => 'ALK-001',
            'nama' => 'Sarung Tangan Medis',
            'satuan' => 'box',
            'stok' => 18,
            'stok_minimum' => 5,
            'harga_beli' => 25000,
            'harga_jual' => 30000,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('alkes', ['id' => $item->id, 'nama' => 'Sarung Tangan Medis', 'stok' => 18]);
    }
}
