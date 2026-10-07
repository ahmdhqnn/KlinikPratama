<?php

namespace Tests\Feature;

use App\Models\Obat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ObatInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_medicine_catalog_and_stock_movements(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-medicine@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $medicine = Obat::create([
            'kode' => 'OBT-001',
            'kode_kfa' => 'KFA-001',
            'nama' => 'Paracetamol 500 mg',
            'satuan_besar' => 'strip',
            'satuan_kecil' => 'tablet',
            'konversi_satuan' => 10,
            'harga_beli' => 5000,
            'harga_jual' => 7000,
            'stok' => 10,
            'stok_minimum' => 5,
            'jenis' => 'obat',
            'is_active' => true,
        ]);
        Obat::create([
            'kode' => 'BHP-LOW',
            'kode_kfa' => 'KFA-LOW',
            'nama' => 'Kasa steril',
            'stok' => 2,
            'stok_minimum' => 5,
            'jenis' => 'bhp',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.obat.index', ['search' => 'KFA-LOW', 'stok_rendah' => '1']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/obat/index')
                ->has('medicines.data', 1)
                ->where('medicines.data.0.name', 'Kasa steril')
                ->where('filters.lowStock', true)
            );

        $this->put(route('master.obat.update', $medicine), [
            'kode' => 'OBT-001',
            'kode_kfa' => 'KFA-001',
            'nama' => 'Paracetamol 500 mg tablet',
            'satuan_besar' => 'strip',
            'satuan_kecil' => 'tablet',
            'konversi_satuan' => 10,
            'harga_beli' => 5000,
            'harga_jual' => 7500,
            'indikasi' => 'Pereda nyeri',
            'kandungan' => 'Paracetamol',
            'stok_minimum' => 6,
            'jenis' => 'obat',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->post(route('master.obat.tambah-stok', $medicine), [
            'jumlah' => 3,
            'keterangan' => 'Koreksi stok opname',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'nama' => 'Paracetamol 500 mg tablet', 'stok' => 13]);
        $this->assertDatabaseHas('stok_mutasi', [
            'obat_id' => $medicine->id,
            'jenis' => 'masuk',
            'jumlah' => 3,
            'stok_sebelum' => 10,
            'stok_sesudah' => 13,
            'keterangan' => 'Koreksi stok opname',
        ]);

        $this->get(route('master.obat.stok', $medicine))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/obat/stok')
                ->where('medicine.stock', 13)
                ->has('movements.data', 1)
                ->where('movements.data.0.stockAfter', 13)
            );

        $this->from(route('master.obat.stok', $medicine))
            ->post(route('master.obat.tambah-stok', $medicine), ['jumlah' => 0])
            ->assertSessionHasErrors('jumlah');
        $this->from(route('master.obat.stok', $medicine))
            ->post(route('master.obat.tambah-stok', $medicine), ['jumlah' => 1.5])
            ->assertSessionHasErrors('jumlah');
        $this->assertDatabaseCount('stok_mutasi', 1);

        $this->post(route('master.obat.store'), [
            'kode' => 'OBT-002',
            'nama' => 'Ibuprofen',
            'konversi_satuan' => 1,
            'harga_beli' => 8000,
            'harga_jual' => 10000,
            'stok' => 20,
            'stok_minimum' => 4,
            'jenis' => 'obat',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('obat', ['kode' => 'OBT-002', 'nama' => 'Ibuprofen', 'stok' => 20]);
    }
}
