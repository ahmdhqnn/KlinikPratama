<?php

namespace Tests\Feature;

use App\Models\DepoObat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DepoObatInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_depots_from_the_inertia_page(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-depot@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $depot = DepoObat::create(['kode' => 'FAR', 'nama' => 'Depo Farmasi', 'deskripsi' => 'Utama', 'is_active' => true]);

        $this->actingAs($admin)->get(route('master.depo-obat.index', ['search' => 'FAR']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/depo-obat/index')
                ->has('depots.data', 1)
                ->where('depots.data.0.name', 'Depo Farmasi')
            );

        $this->put(route('master.depo-obat.update', $depot), [
            'kode' => 'FAR',
            'nama' => 'Depo Utama',
            'deskripsi' => 'Penyimpanan utama',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('depo_obat', ['id' => $depot->id, 'nama' => 'Depo Utama']);

        $this->post(route('master.depo-obat.store'), [
            'kode' => 'UGD',
            'nama' => 'Depo UGD',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('depo_obat', ['kode' => 'UGD', 'nama' => 'Depo UGD']);
    }
}
