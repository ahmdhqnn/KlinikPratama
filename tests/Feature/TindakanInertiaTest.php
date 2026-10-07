<?php

namespace Tests\Feature;

use App\Models\Obat;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TindakanInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_treatments_and_their_supplies_from_inertia_pages(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-tindakan@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $treatment = Tindakan::create([
            'kode' => 'TIN-001',
            'kode_icd9' => '86.59',
            'nama' => 'Penjahitan luka',
            'kategori' => 'medis',
            'tarif' => 150000,
            'tarif_dokter' => 75000,
            'tarif_asisten' => 25000,
            'tarif_klinik' => 50000,
            'is_active' => true,
        ]);
        $medicine = Obat::create([
            'kode' => 'BHP-001',
            'nama' => 'Benang jahit',
            'satuan_kecil' => 'pcs',
            'stok' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.tindakan.index', ['search' => 'TIN-001']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/tindakan/index')
                ->has('treatments.data', 1)
                ->where('treatments.data.0.name', 'Penjahitan luka')
                ->where('treatments.data.0.doctorTariff', 75000)
            );

        $this->put(route('master.tindakan.update', $treatment), [
            'kode' => 'TIN-001',
            'kode_icd9' => '86.59',
            'nama' => 'Penjahitan luka sederhana',
            'kategori' => 'medis',
            'poliklinik_id' => null,
            'tarif' => 160000,
            'tarif_dokter' => 80000,
            'tarif_asisten' => 30000,
            'tarif_klinik' => 50000,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan', ['id' => $treatment->id, 'nama' => 'Penjahitan luka sederhana']);

        $this->actingAs($admin)->get(route('master.tindakan.show', $treatment))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/tindakan/show')
                ->where('treatment.name', 'Penjahitan luka sederhana')
                ->has('medicines', 1)
            );

        $this->post(route('master.tindakan.bhp.store', $treatment), [
            'obat_id' => $medicine->id,
            'jumlah' => 2.5,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_bhp', [
            'tindakan_id' => $treatment->id,
            'obat_id' => $medicine->id,
            'jumlah' => 2.5,
        ]);

        $bhp = $treatment->bhp()->firstOrFail();
        $this->delete(route('master.tindakan.bhp.destroy', $bhp))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('tindakan_bhp', ['id' => $bhp->id]);
    }

    public function test_bhp_quantity_must_be_greater_than_zero(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-tindakan-validation@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $treatment = Tindakan::create([
            'kode' => 'TIN-002',
            'nama' => 'Pemeriksaan umum',
            'kategori' => 'medis',
            'is_active' => true,
        ]);
        $medicine = Obat::create([
            'kode' => 'BHP-002',
            'nama' => 'Kasa steril',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('master.tindakan.show', $treatment))
            ->post(route('master.tindakan.bhp.store', $treatment), [
                'obat_id' => $medicine->id,
                'jumlah' => 0,
            ])
            ->assertSessionHasErrors('jumlah');

        $this->assertDatabaseCount('tindakan_bhp', 0);
    }
}
