<?php

namespace Tests\Feature;

use App\Models\Poliklinik;
use App\Models\RuangPoli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PoliklinikInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_clinics_and_examination_rooms_inertia_pages(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-poli@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.poliklinik.index', ['search' => 'Umum']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/poliklinik/index')
                ->has('clinics.data', 1)
                ->where('clinics.data.0.name', 'Poli Umum')
            );

        $this->post(route('master.poliklinik.ruang.store', $clinic), ['nama' => 'Ruang 1'])
            ->assertSessionHasNoErrors();
        $room = RuangPoli::query()->where('poliklinik_id', $clinic->id)->firstOrFail();

        $this->get(route('master.poliklinik.show', $clinic))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/poliklinik/show')
                ->where('clinic.name', 'Poli Umum')
                ->where('clinic.rooms.0.name', 'Ruang 1')
            );

        $this->post(route('master.poliklinik.store'), [
            'kode' => 'GIGI',
            'nama' => 'Poli Gigi',
            'jenis' => 'gigi',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('poliklinik', ['kode' => 'GIGI', 'nama' => 'Poli Gigi']);

        $this->delete(route('master.poliklinik.ruang.destroy', $room))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('ruang_poli', ['id' => $room->id]);
    }
}
