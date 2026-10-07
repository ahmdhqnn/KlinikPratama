<?php

namespace Tests\Feature;

use App\Models\Laboratorium;
use App\Models\PaketTindakan;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaketTindakanInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_package_composition_from_inertia_pages(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-package@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $package = PaketTindakan::create([
            'kode' => 'PKT-001',
            'nama' => 'Paket MCU',
            'deskripsi' => 'Pemeriksaan dasar',
            'tarif' => 250000,
            'is_active' => true,
        ]);
        $treatment = Tindakan::create([
            'kode' => 'TIN-PKT-001',
            'nama' => 'Pemeriksaan umum',
            'kategori' => 'medis',
            'tarif' => 100000,
            'is_active' => true,
        ]);
        $laboratory = Laboratorium::create([
            'kode' => 'LAB-PKT-001',
            'nama' => 'Darah lengkap',
            'tarif' => 200000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.paket-tindakan.index', ['search' => 'PKT-001']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/paket-tindakan/index')
                ->has('packages.data', 1)
                ->where('packages.data.0.name', 'Paket MCU')
                ->where('packages.data.0.itemCount', 0)
            );

        $this->put(route('master.paket-tindakan.update', $package), [
            'kode' => 'PKT-001',
            'nama' => 'Paket MCU Dasar',
            'deskripsi' => 'Pemeriksaan dasar',
            'tarif' => 250000,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->post(route('master.paket-tindakan.item.store', $package), [
            'jenis' => 'tindakan',
            'tindakan_id' => $treatment->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('master.paket-tindakan.item.store', $package), [
            'jenis' => 'lab',
            'laboratorium_id' => $laboratory->id,
        ])->assertSessionHasNoErrors();

        $this->get(route('master.paket-tindakan.show', $package))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/paket-tindakan/show')
                ->where('package.name', 'Paket MCU Dasar')
                ->where('package.standardTariffTotal', 300000)
                ->has('package.items', 2)
                ->where('package.items.0.name', 'Pemeriksaan umum')
                ->where('package.items.1.name', 'Darah lengkap')
            );

        $item = $package->items()->where('jenis', 'lab')->firstOrFail();
        $this->delete(route('master.paket-tindakan.item.destroy', $item))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('paket_tindakan_items', ['id' => $item->id]);

        $this->post(route('master.paket-tindakan.store'), [
            'kode' => 'PKT-002',
            'nama' => 'Paket baru',
            'tarif' => 150000,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('paket_tindakan', ['kode' => 'PKT-002', 'nama' => 'Paket baru']);
    }

    public function test_package_item_requires_a_service_matching_its_type(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-package-validation@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $package = PaketTindakan::create(['kode' => 'PKT-003', 'nama' => 'Paket kosong', 'tarif' => 0, 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('master.paket-tindakan.show', $package))
            ->post(route('master.paket-tindakan.item.store', $package), ['jenis' => 'tindakan'])
            ->assertSessionHasErrors('tindakan_id');

        $this->assertDatabaseCount('paket_tindakan_items', 0);
    }
}
